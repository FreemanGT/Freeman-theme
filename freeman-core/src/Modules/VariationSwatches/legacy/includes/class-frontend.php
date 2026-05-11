<?php
/**
 * Frontend: swap the variable-product add-to-cart template, register a
 * shortcode, and enqueue assets. We leverage WooCommerce's native
 * variations_form so stock/price/availability logic remains untouched.
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Etucart_VS_Frontend' ) ) :

class Etucart_VS_Frontend {

	/**
	 * Tracks whether the template has already been rendered on the page
	 * (so the shortcode is idempotent on single-product pages).
	 *
	 * @var int[]
	 */
	private $rendered = [];

	public function register(): void {
		// Replace the default variable add-to-cart renderer everywhere.
		add_action( 'init', [ $this, 'swap_variable_add_to_cart' ], 20 );

		// Register shortcode.
		add_shortcode( 'etucart_buy_box', [ $this, 'shortcode' ] );

		// Assets — registered late so our CSS is printed after the theme's.
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 9999 );

		// Filter attribute label so the attribute "title" is always the human name.
		// (Core already handles this, but some themes override — this is a safety net.)
		add_filter( 'woocommerce_attribute_label', [ $this, 'filter_attribute_label' ], 10, 3 );
	}

	/**
	 * Remove default variable + simple add-to-cart actions and register our
	 * replacement. Grouped / external products intentionally keep the WC
	 * default template (they have fundamentally different UX — grouped
	 * renders a child-product table, external renders a single "Buy on X"
	 * link with no quantity, so neither maps cleanly onto the Freeman
	 * buy box).
	 */
	public function swap_variable_add_to_cart(): void {
		remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
		add_action( 'woocommerce_variable_add_to_cart', [ $this, 'render_for_current_product' ], 30 );

		remove_action( 'woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30 );
		add_action( 'woocommerce_simple_add_to_cart', [ $this, 'render_for_current_product' ], 30 );

		// Suppress WC's default PDP price render for variable AND simple
		// products — both buy-box templates emit their own `.etucart-pdp-price`
		// line (variation-buy-box.php flips it between "starting from {min}" and
		// the picked variation's price; simple-buy-box.php renders the static
		// price). Leaving WC's price action in place would duplicate it (and for
		// variable products, create the "two prices that drift apart" failure
		// mode the QA flagged). Grouped / external products keep WC's price
		// line — they use the native WC template, not our buy box.
		//
		// Hooked at priority 9 on `woocommerce_single_product_summary` so it
		// runs immediately before WC's default `woocommerce_template_single_price`
		// (priority 10). This fires during EVERY render of the single-product
		// summary, not just main-page requests — so quick-view modals (WPC
		// Quick View, WooSQ, YITH Quick View, etc.) that AJAX-inject the
		// summary template get the same de-duplication. The previous `wp`
		// hook only ran on real product pages and missed the modal context.
		add_action( 'woocommerce_single_product_summary', [ $this, 'maybe_suppress_pdp_price' ], 9 );

		// 1.7.11 added an `is_rtl()`-based filter that forced
		// currency-on-right; reverted in 1.7.12 — sites should rely on
		// WooCommerce's own "Currency position" setting (Settings → General),
		// which the runtime `wc_price()` already respects. Pricing is shop-
		// specific: a Hebrew shop that wants "₪149.90" should be honoured.
	}

	/**
	 * Remove `woocommerce_template_single_price` from the single-product
	 * summary for variable AND simple products — both render their own
	 * `.etucart-pdp-price` line via our buy-box templates, so WC's price
	 * action would only duplicate it. Grouped / external products keep WC's
	 * price line untouched (they use the native WC add-to-cart template).
	 *
	 * Hooked on `woocommerce_single_product_summary` priority 9 so it
	 * fires during every summary render — main PDP, Elementor preview,
	 * AJAX quick-view modal, [product_page] shortcode, etc. The
	 * `is_product()` gate that previously scoped this to real product
	 * pages was the cause of the doubled-price bug inside quick-view
	 * modals (where `is_product()` returns false).
	 */
	public function maybe_suppress_pdp_price(): void {
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_type( [ 'variable', 'simple' ] ) ) {
			return;
		}
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	}

	public function render_for_current_product(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$this->render_buy_box( $product );
	}

	/**
	 * Shortcode entry point. Supports:
	 *   [etucart_buy_box]            — uses the current product on a single-product page
	 *   [etucart_buy_box id="123"]   — forces a specific product
	 */
	public function shortcode( $atts = [] ): string {
		$atts = shortcode_atts( [
			'id' => 0,
		], is_array( $atts ) ? $atts : [], 'etucart_buy_box' );

		$product_id = absint( $atts['id'] );

		if ( ! $product_id ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id = $product->get_id();
			} else {
				$product_id = (int) get_queried_object_id();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		ob_start();
		$this->render_buy_box( $product );
		return (string) ob_get_clean();
	}

	/**
	 * Render the buy box for a product. Falls back to the default WC template
	 * for non-variable products so we don't break simple/grouped/external.
	 */
	public function render_buy_box( WC_Product $product ): void {
		$pid = $product->get_id();
		if ( isset( $this->rendered[ $pid ] ) ) {
			return;
		}
		$this->rendered[ $pid ] = 1;

		$this->enqueue_assets();

		if ( $product->is_type( 'variable' ) ) {
			$this->render_variable( $product );
		} elseif ( $product->is_type( 'simple' ) ) {
			$this->render_simple( $product );
		} else {
			// Grouped / external / other product types — keep the native WC experience.
			woocommerce_template_single_add_to_cart();
		}
	}

	/**
	 * Render the Freeman buy box for a simple product. Uses the same
	 * `.etucart-buy-box` shell as the variable template (so all existing
	 * CSS + the Buy Now JS delegate apply verbatim) but omits the
	 * variations scaffolding — there is no `variations_form` class, no
	 * hidden <select>s, no `wc-variation-selection-needed` state. The
	 * submit buttons start enabled so WC processes the POST like any
	 * other simple add-to-cart.
	 */
	private function render_simple( WC_Product $product ): void {
		$template_vars = [
			'product' => $product,
		];

		wc_get_template(
			'simple-buy-box.php',
			$template_vars,
			'etucart-variation-swatches/',
			ETUCART_VS_DIR . 'templates/'
		);
	}

	private function render_variable( WC_Product $product ): void {
		// WC_Product_Variable::get_available_variations() is the authoritative
		// list that WC's own variations.js consumes — using it keeps stock /
		// availability / price logic identical to core.
		/** @var WC_Product_Variable $product */
		$available_variations = $product->get_available_variations();
		$attributes           = $product->get_variation_attributes();
		$selected_attributes  = $product->get_default_attributes();

		// --- Hide out-of-stock options (PDP setting, 1.6.1; default flipped to OFF in 1.6.6) ---
		// When the "Hide out-of-stock options" setting is on, remove any
		// attribute value whose every matching variation is out of stock /
		// not purchasable. The filter runs BEFORE the template sees the data,
		// so both the visible swatches AND the hidden <select>s agree — WC's
		// own variations.js then has a smaller universe to match against,
		// stock/price logic is unchanged for the remaining variations.
		//
		// As of 1.6.6 this defaults to OFF on the PDP: the single-product page
		// now shows every option (sold-out ones are greyed with a strike-through),
		// and OOS hiding moved to the shop / archive picker instead. Pass 'no'
		// as the fallback so never-saved installs inherit the new default.
		if ( class_exists( 'Etucart_VS_Settings' )
			&& Etucart_VS_Settings::bool( Etucart_VS_Settings::OPT_PDP_HIDE_OOS, 'no' )
		) {
			list( $attributes, $available_variations ) = Etucart_VS_Plugin::filter_in_stock_only(
				$attributes,
				$available_variations
			);
		}

		$template_vars = [
			'product'              => $product,
			'attributes'           => $attributes,
			'available_variations' => $available_variations,
			'selected_attributes'  => $selected_attributes,
		];

		// Allow theme overrides via /woocommerce/etucart-variation-swatches/variation-buy-box.php
		wc_get_template(
			'variation-buy-box.php',
			$template_vars,
			'etucart-variation-swatches/',
			ETUCART_VS_DIR . 'templates/'
		);
	}

	public function register_assets(): void {
		$fs_base  = ETUCART_VS_DIR . '../assets/';
		$url_base = ETUCART_VS_URL . 'assets/';
		// ETUCART_VS_DIR ends with 'legacy/'; assets live one dir up.
		$fs_base  = dirname( rtrim( ETUCART_VS_DIR, '/' ) ) . '/assets/';
		$pick     = array( '\\Freeman\\Core\\Core\\Module_Base', 'pick_min_url' );

		wp_register_style(
			'freeman-core',
			call_user_func( $pick, $fs_base, $url_base, 'css/etucart-swatches.css' ),
			[],
			ETUCART_VS_VERSION
		);
		// Hard dependency on WC's variation script — it is what actually runs
		// `check_variations`, disables unavailable option elements, and fires
		// the `found_variation` / `reset_data` events our JS listens to for
		// out-of-stock grey-out. Without it, OOS detection silently fails.
		wp_register_script(
			'freeman-core',
			call_user_func( $pick, $fs_base, $url_base, 'js/etucart-swatches.js' ),
			[ 'jquery', 'wc-add-to-cart-variation' ],
			ETUCART_VS_VERSION,
			true
		);
		// Resolve the "Buy Now" destination. We default to WooCommerce's
		// configured checkout URL, but expose a filter so FunnelKit users
		// (or anyone with a custom checkout funnel) can point Buy Now at
		// a specific funnel URL instead. FunnelKit Checkout typically
		// sets the WC checkout page to its own funnel page, so
		// wc_get_checkout_url() already returns the correct URL — but the
		// filter is there if further customisation is needed.
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
		$checkout_url = (string) apply_filters( 'etucart_vs_checkout_url', $checkout_url );

		wp_localize_script( 'freeman-core', 'EtucartVS', [
			'checkoutUrl' => esc_url_raw( $checkout_url ),
			'i18n' => [
				'choose'       => __( 'Choose an option', 'freeman-core' ),
				'notAvailable' => __( 'This combination is not available', 'freeman-core' ),
				'addedToCart'  => __( 'נוסף לעגלה', 'freeman-core' ),
			],
		] );

		// Always enqueue on the frontend. This matters for quick-view modals
		// (WPC QuickView, WooSQ, etc.) which AJAX-inject our template into a
		// shop/category/home page — at that point wp_enqueue_scripts has
		// already finished, and an AJAX response can't add <link>/<script>
		// tags retroactively. By enqueuing unconditionally on every frontend
		// page we guarantee our CSS + JS (and WC's variation script, which
		// OOS detection depends on) are already on the parent page whenever a
		// quick-view reveals a variable product.
		//
		// Performance: the combined assets are small (~30 KB), and the CSS
		// only targets `.etucart-buy-box` selectors, so pages without the
		// form pay no rendering cost.
		if ( ! is_admin() ) {
			// Force WC's variation script onto every frontend page. WC only
			// auto-enqueues it on single variable-product pages; on shop /
			// category / home we need it for quick-view modals. Enqueuing a
			// registered handle is a no-op if it's already enqueued.
			if ( wp_script_is( 'wc-add-to-cart-variation', 'registered' ) ) {
				wp_enqueue_script( 'wc-add-to-cart-variation' );
			}
			wp_enqueue_style( 'freeman-core' );
			wp_enqueue_script( 'freeman-core' );
		}
	}

	/**
	 * Legacy helper retained for backward compat — the assets are now
	 * enqueued unconditionally by register_assets(). Calling this is a
	 * harmless no-op when the handles are already enqueued.
	 */
	private function enqueue_assets(): void {
		wp_enqueue_style( 'freeman-core' );
		wp_enqueue_script( 'freeman-core' );
	}

	/**
	 * Ensure attribute labels are resolved to their human-readable name
	 * (Hebrew title), never the raw slug.
	 */
	public function filter_attribute_label( $label, $name, $product = null ) {
		// Only step in when the label is empty or looks like the raw slug —
		// otherwise core already resolved it correctly and we'd risk infinite
		// recursion against ourselves via resolve_attribute_label().
		$looks_like_slug = ( '' === (string) $label )
			|| ( is_string( $name ) && 0 === strcasecmp( (string) $label, (string) $name ) )
			|| ( is_string( $name ) && 0 === strpos( (string) $name, 'pa_' ) && 0 === strcasecmp( (string) $label, substr( (string) $name, 3 ) ) );

		if ( ! $looks_like_slug ) {
			return $label;
		}

		// Avoid recursion: resolve_attribute_label() itself calls
		// wc_attribute_label() — pass the existing label through when it's
		// already non-empty, otherwise fall through to the taxonomy lookup.
		$taxonomy = is_string( $name ) && 0 === strpos( $name, 'pa_' ) ? $name : '';
		if ( '' !== $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$tax = get_taxonomy( $taxonomy );
			if ( $tax ) {
				if ( ! empty( $tax->labels->singular_name ) ) {
					return $tax->labels->singular_name;
				}
				if ( ! empty( $tax->label ) ) {
					return $tax->label;
				}
			}
		}

		if ( '' !== $taxonomy && function_exists( 'wc_get_attribute_taxonomy_by_name' ) ) {
			$row = wc_get_attribute_taxonomy_by_name( substr( $taxonomy, 3 ) );
			if ( $row && ! empty( $row->attribute_label ) ) {
				return $row->attribute_label;
			}
		}

		return $label;
	}
}

endif; // class_exists Etucart_VS_Frontend
