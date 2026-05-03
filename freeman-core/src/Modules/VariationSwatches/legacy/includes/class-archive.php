<?php
/**
 * Archive / shop-grid integration (new in 1.6.0).
 *
 * Replaces the default "Choose options" link rendered by WooCommerce for
 * variable products inside an archive loop with a compact inline variation
 * picker (color swatches, size pills, "+N" reveal, Add-to-cart) that
 * submits via AJAX and never requires leaving the grid.
 *
 * Design intent:
 *   - ZERO impact on the single-product buy box. We only hook the archive
 *     loop filter; single-product templates, cart, checkout and admin are
 *     untouched by construction.
 *   - Self-contained matching: we do NOT depend on wc-add-to-cart-variation.js
 *     because a grid of 24 variable products would mean 24 variations_form
 *     instances, each embedding full WC payloads. Instead we emit a compact
 *     JSON per card and run our own matcher in vanilla JS.
 *   - Feature-flagged: controlled entirely by Etucart_VS_Settings. If the
 *     setting is off (or the current archive is excluded), the filter
 *     returns early and the default WC link renders unchanged.
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Etucart_VS_Archive' ) ) :

class Etucart_VS_Archive {

	/**
	 * Marks that at least one picker was rendered on the page, so asset
	 * enqueuing can be lazy / targeted when possible.
	 *
	 * @var bool
	 */
	private $rendered_any = false;

	/**
	 * In-request cache of prepared per-product data, keyed by product ID.
	 * Building this is O(variations), so we memoise in case the same product
	 * renders twice (e.g. in a "related products" strip on the same page).
	 *
	 * @var array<int,array>
	 */
	private $prepared_cache = [];

	public function register(): void {
		// Primary hook: WC emits the "Choose options" anchor via this filter
		// for variable products in the loop. We replace it with our picker.
		add_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'maybe_replace_loop_link' ], 20, 3 );

		// Enqueue our assets on any frontend request where the picker could
		// appear. This is a narrow superset of archives; pages without a loop
		// pay only the cost of two static file requests (~15 KB combined).
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 9998 );

		// AJAX add-to-cart endpoint — WC's built-in ?wc-ajax=add_to_cart is
		// scoped to simple products (it doesn't honour variation_id or the
		// variation[] attribute map), so we expose our own endpoint that
		// handles variable products correctly and returns WC fragments so
		// cart drawers refresh automatically.
		add_action( 'wc_ajax_etucart_shop_add_to_cart',         [ $this, 'ajax_add_to_cart' ] );
		add_action( 'wc_ajax_nopriv_etucart_shop_add_to_cart',  [ $this, 'ajax_add_to_cart' ] );

		// Loop price replacement (1.7.6): swap WC's default `template_loop_price`
		// for our own callback that skips rendering when the picker will
		// supply its own "starting from" line — otherwise we'd stack
		// WC's "₪20 – ₪100" range *above* the picker's price, and they
		// drift apart on selection because only the picker's line updates.
		add_action( 'wp', [ $this, 'maybe_swap_loop_price_action' ] );
	}

	/**
	 * Replace the loop price action so variable-product cards where our
	 * picker is active don't double-render the price.
	 */
	public function maybe_swap_loop_price_action(): void {
		if ( is_admin() ) {
			return;
		}
		if ( ! Etucart_VS_Settings::bool( Etucart_VS_Settings::OPT_SHOW_PRICE, 'yes' ) ) {
			return; // shop owner explicitly disabled our price line; leave WC's intact.
		}
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		add_action( 'woocommerce_after_shop_loop_item_title', [ $this, 'render_loop_price_or_skip' ], 10 );
	}

	/**
	 * Loop price renderer: skip for variable products where our picker
	 * will render the price; defer to WC's default for everything else.
	 */
	public function render_loop_price_or_skip(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( $product->is_type( 'variable' )
			&& Etucart_VS_Settings::should_apply_on_current_archive()
			&& ! $this->is_product_excluded( $product )
		) {
			return; // picker owns the price line
		}
		woocommerce_template_loop_price();
	}

	/**
	 * Is this product in the OPT_EXCLUDED_CATEGORIES list?
	 *
	 * @param WC_Product $product
	 * @return bool
	 */
	private function is_product_excluded( WC_Product $product ): bool {
		$excluded = Etucart_VS_Settings::excluded_category_ids();
		if ( empty( $excluded ) ) {
			return false;
		}
		return ! empty( array_intersect( $excluded, (array) $product->get_category_ids() ) );
	}

	/* ------------------------------------------------------------------ *
	 * Loop link replacement
	 * ------------------------------------------------------------------ */

	/**
	 * @param string     $html    Default WC-rendered <a class="button…">.
	 * @param WC_Product $product Current product in the loop.
	 * @param array      $args    Loop link args.
	 *
	 * @return string HTML to render in place of the default link.
	 */
	public function maybe_replace_loop_link( $html, $product, $args ) {
		if ( ! $product instanceof WC_Product ) {
			return $html;
		}
		// Grouped / external products keep the WC default link — grouped
		// would need child pickers and external is "Buy on X" with no
		// quantity to add, neither maps cleanly onto the Freeman card.
		if ( ! $product->is_type( 'variable' ) && ! $product->is_type( 'simple' ) ) {
			return $html;
		}
		if ( ! Etucart_VS_Settings::should_apply_on_current_archive() ) {
			return $html;
		}

		// Respect the excluded-categories list.
		$excluded = Etucart_VS_Settings::excluded_category_ids();
		if ( ! empty( $excluded ) ) {
			$product_cat_ids = $product->get_category_ids();
			if ( array_intersect( $excluded, (array) $product_cat_ids ) ) {
				return $html;
			}
		}

		if ( $product->is_type( 'simple' ) ) {
			$prepared = $this->prepare_simple_product_data( $product );
			if ( empty( $prepared ) ) {
				return $html;
			}
			$markup = $this->render_simple_template( $product, $prepared );
			if ( '' === $markup ) {
				return $html;
			}
			$this->rendered_any = true;
			return $markup;
		}

		$prepared = $this->prepare_product_data( $product );
		if ( empty( $prepared ) || empty( $prepared['attrs'] ) ) {
			// Variable product with no usable attribute data — fall back to
			// the default link so the user still has a way to buy it.
			return $html;
		}

		$markup = $this->render_template( $product, $prepared );
		if ( '' === $markup ) {
			return $html;
		}

		$this->rendered_any = true;
		return $markup;
	}

	/**
	 * Build the compact data payload for one simple product. Smaller and
	 * much cheaper than prepare_product_data() — no variations traversal,
	 * no attribute resolution, no term lookups. We reuse the same nonce
	 * key + ajax endpoint so the AJAX handler can validate requests from
	 * either picker flavour with identical code.
	 */
	private function prepare_simple_product_data( WC_Product $product ): array {
		$pid = $product->get_id();
		if ( isset( $this->prepared_cache[ $pid ] ) ) {
			return $this->prepared_cache[ $pid ];
		}

		$min_qty = (int) $product->get_min_purchase_quantity();
		if ( $min_qty < 1 ) {
			$min_qty = 1;
		}
		$max_qty = (int) $product->get_max_purchase_quantity();

		$data = [
			'pid'            => $pid,
			'is_purchasable' => $product->is_purchasable() && $product->is_in_stock(),
			'min_qty'        => $min_qty,
			'max_qty'        => $max_qty, // -1 means no limit
			'step'           => 1,
			'price_html'     => (string) $product->get_price_html(),
			'product_url'    => $product->get_permalink(),
			'cart_url'       => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'ajax_url'       => \WC_AJAX::get_endpoint( 'etucart_shop_add_to_cart' ),
			'nonce'          => wp_create_nonce( 'etucart_vs_shop_' . $pid ),
		];

		$this->prepared_cache[ $pid ] = $data;
		return $data;
	}

	/**
	 * Render the simple-product picker template to string.
	 */
	private function render_simple_template( WC_Product $product, array $prepared ): string {
		$template_vars = [
			'product'    => $product,
			'prepared'   => $prepared,
			// Honour the same "show price" toggle as the variable picker so
			// shop owners have a single setting controlling both flavours.
			'show_price' => Etucart_VS_Settings::bool( Etucart_VS_Settings::OPT_SHOW_PRICE, 'yes' ),
		];

		ob_start();
		wc_get_template(
			'shop-simple-pick.php',
			$template_vars,
			'etucart-variation-swatches/',
			ETUCART_VS_DIR . 'templates/'
		);
		return (string) ob_get_clean();
	}

	/**
	 * Build the compact data payload for one variable product. The shape is
	 * documented at the top of `shop-variation-pick.php`.
	 *
	 * Cached per request in $this->prepared_cache and across requests in a
	 * transient keyed by product id + WC's product cache version + user tax
	 * context. WC bumps the `product` transient version on every product /
	 * variation save (`WC_Cache_Helper::invalidate_cache_group`), so the
	 * cache self-invalidates — no extra hook wiring needed.
	 *
	 * The nonce and ajax_url are overlaid fresh after the cache read: nonces
	 * are session-scoped and must not be cached across users.
	 */
	private function prepare_product_data( WC_Product $product ): array {
		$pid = $product->get_id();
		if ( isset( $this->prepared_cache[ $pid ] ) ) {
			return $this->prepared_cache[ $pid ];
		}

		$transient_key = $this->prepared_transient_key( $pid );
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) && isset( $cached['pid'] ) ) {
			$cached['nonce']    = wp_create_nonce( 'etucart_vs_shop_' . $pid );
			$cached['ajax_url'] = \WC_AJAX::get_endpoint( 'etucart_shop_add_to_cart' );
			$cached['cart_url'] = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
			$this->prepared_cache[ $pid ] = $cached;
			return $cached;
		}

		/** @var WC_Product_Variable $variable */
		$variable             = $product;
		$attributes           = $variable->get_variation_attributes();
		$available_variations = $variable->get_available_variations();
		$defaults             = $variable->get_default_attributes();

		if ( empty( $attributes ) ) {
			$this->prepared_cache[ $pid ] = [];
			return [];
		}

		// --- Hide out-of-stock options on shop / archive (1.6.6) --------------
		// When the shop-level "Hide out-of-stock options" setting is on (new in
		// 1.6.6, default ON), drop attribute values whose every matching
		// variation is sold out. The compact grid picker is a terrible place to
		// let customers pick a sold-out variant — they'd get the generic error
		// toast with no explanation. Pruning here keeps the picker showing only
		// what they can actually buy. Runs BEFORE we build the JSON payload so
		// both the visible chips AND the JS availability matcher see the same
		// universe. Stock/price logic for the remaining variations is unchanged.
		if ( class_exists( 'Etucart_VS_Settings' )
			&& Etucart_VS_Settings::bool( Etucart_VS_Settings::OPT_SHOP_HIDE_OOS, 'yes' )
		) {
			list( $attributes, $available_variations ) = Etucart_VS_Plugin::filter_in_stock_only(
				$attributes,
				$available_variations
			);
		}

		// --- Single-variation override (1.7.6) ---------------------------
		// If the product has exactly ONE purchasable variation in stock at this
		// point, the customer has no real choice — showing them an empty picker
		// just makes them click the only option to confirm it. We pre-select
		// that variation's attributes regardless of OPT_SHOP_NO_PRESELECT.
		// `get_available_variations()` already filters to in-stock + purchasable,
		// and the OOS pruning above keeps the list aligned with what's visible.
		$only_attrs = [];
		if ( count( $available_variations ) === 1 ) {
			$only      = reset( $available_variations );
			$only_pair = isset( $only['attributes'] ) && is_array( $only['attributes'] )
				? $only['attributes'] : [];
			foreach ( $only_pair as $key => $value ) {
				$only_attrs[ str_replace( 'attribute_', '', (string) $key ) ] = (string) $value;
			}
		}

		// --- Build attribute option lists (label, is_color, options[]) ----
		// Wave 2.2 / 4b (1.11.24) — when the image-swatches flag is on, every
		// option entry also carries an `img` field (URL or empty). Flag-OFF:
		// no img field emitted; payload byte-identical to pre-1.11.24.
		$image_swatches_on = \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'image_swatches' );

		$attrs_out = [];
		foreach ( $attributes as $attribute_name => $options ) {
			$sanitized  = sanitize_title( $attribute_name );
			$input_name = 'attribute_' . $sanitized;
			$taxonomy   = 0 === strpos( $attribute_name, 'pa_' ) ? $attribute_name : '';
			$is_color   = $taxonomy && Etucart_VS_Plugin::attribute_is_color( $taxonomy );
			$has_images = $image_swatches_on && $taxonomy && Etucart_VS_Plugin::attribute_has_images( $taxonomy );

			$option_items = [];
			foreach ( $options as $option ) {
				$value = (string) $option;
				$name  = $value;
				$hex   = '';
				$img   = '';
				if ( $taxonomy ) {
					$term = get_term_by( 'slug', $value, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$name = $term->name;
						$hex  = Etucart_VS_Plugin::term_color( (int) $term->term_id );
						if ( $image_swatches_on ) {
							$img = Etucart_VS_Plugin::term_image_url( (int) $term->term_id, 'thumbnail' );
						}
					}
				}
				$item = [
					'v'   => $value,
					'n'   => $name,
					'hex' => $hex,
				];
				if ( $image_swatches_on ) {
					$item['img'] = $img;
				}
				$option_items[] = $item;
			}

			// Whether to honour the product's default attributes for the archive
			// preselection. When OPT_SHOP_NO_PRESELECT is on (default in 1.7.4),
			// every archive picker renders with nothing chosen so the customer
			// must actively pick — regardless of whether the default came from
			// the product editor or the Cheapest Default Variation module.
			$honor_defaults = ! ( class_exists( 'Etucart_VS_Settings' )
				&& Etucart_VS_Settings::bool( Etucart_VS_Settings::OPT_SHOP_NO_PRESELECT, 'yes' ) );

			$selected     = '';
			$valid_values = array_map( 'strval', (array) $options );

			// 1) Single-variation override always wins (see comment above).
			if ( ! empty( $only_attrs ) && isset( $only_attrs[ $sanitized ] )
				&& '' !== $only_attrs[ $sanitized ]
				&& in_array( $only_attrs[ $sanitized ], $valid_values, true )
			) {
				$selected = $only_attrs[ $sanitized ];
			} elseif ( $honor_defaults && isset( $defaults[ $sanitized ] ) && '' !== (string) $defaults[ $sanitized ] ) {
				$candidate = (string) $defaults[ $sanitized ];
				if ( in_array( $candidate, $valid_values, true ) ) {
					$selected = $candidate;
				}
			}

			$entry_attr = [
				'name'     => $input_name,
				'label'    => Etucart_VS_Plugin::resolve_attribute_label( $attribute_name, $product ),
				'is_color' => $is_color,
				'selected' => $selected,
				'options'  => $option_items,
			];
			if ( $image_swatches_on ) {
				$entry_attr['has_images'] = $has_images;
			}
			$attrs_out[] = $entry_attr;
		}

		// --- Build the compact variations list -----------------------------
		// Wave 2.2 / 4f (1.11.23) — when the card-image-swap flag is on, attach
		// per-variation image data (src, srcset, sizes) so the JS click handler
		// can swap the card's main image without navigating. Flag-OFF: no
		// image fields emitted; payload byte-identical to pre-PR.
		$swap_card_image = \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'card_image_swap' );

		$variations_out = [];
		foreach ( (array) $available_variations as $v ) {
			if ( ! is_array( $v ) ) {
				continue;
			}
			$variations_out[] = self::build_variation_entry( $v, $product, $swap_card_image );
		}

		// --- "From:" price string (החל מ: ₪X) -----------------------------
		// Show the "from" prefix only when there IS actually a price range
		// across variations. If every variation costs the same (very common
		// for shoes or single-size clothing where size doesn't change price),
		// showing "החל מ:" is misleading, so we emit just the price. WC's
		// get_variation_price() already respects sales, tax display and any
		// dynamic-pricing plugins that filter it, so the comparison is
		// whatever the customer would actually pay.
		$min_price      = (float) $variable->get_variation_price( 'min', true );
		$max_price      = (float) $variable->get_variation_price( 'max', true );
		$has_price_range = ( $max_price > $min_price );
		$from_price_raw  = wc_price( $min_price );

		$data = [
			'pid'             => $pid,
			'attrs'           => $attrs_out,
			'variations'      => $variations_out,
			'from_price'      => $from_price_raw,
			'has_price_range' => $has_price_range,
			'cart_url'        => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'product_url'     => $product->get_permalink(),
			'ajax_url'        => \WC_AJAX::get_endpoint( 'etucart_shop_add_to_cart' ),
			'nonce'           => wp_create_nonce( 'etucart_vs_shop_' . $pid ),
		];

		// Persist to the shared transient before attaching nonce/ajax. Those
		// are per-session and get overlaid on every cache hit above.
		$to_cache = $data;
		unset( $to_cache['nonce'], $to_cache['ajax_url'], $to_cache['cart_url'] );
		set_transient( $transient_key, $to_cache, 6 * HOUR_IN_SECONDS );

		$this->prepared_cache[ $pid ] = $data;
		return $data;
	}

	/**
	 * Build one entry of the JSON variations payload.
	 *
	 * Extracted from prepare_product_data() in 1.11.23 so the new image-payload
	 * branch can be unit-tested without standing up the full `\WC_Product_Variable`
	 * stub stack. Behavior is unchanged from the inline loop body.
	 *
	 * When $with_image is true and the WC variation array carries an `image`
	 * subarray, the entry gets `image_src` / `image_srcset` / `image_sizes`
	 * appended (passing through the `freeman_core/variation_swatches/card_image_payload`
	 * filter). When false, the entry has only id / attrs / in_stock / is_purchasable
	 * / price_html — byte-identical to pre-1.11.23.
	 *
	 * @internal Used by Etucart_VS_Archive only; signature may change without notice.
	 *
	 * @param array       $v          Variation array as returned by
	 *                                WC_Product_Variable::get_available_variations().
	 * @param \WC_Product $product    The variable parent product (passed to the filter).
	 * @param bool        $with_image Whether to attach the image payload.
	 * @return array
	 */
	public static function build_variation_entry( array $v, WC_Product $product, bool $with_image ): array {
		$attrs_map = [];
		if ( isset( $v['attributes'] ) && is_array( $v['attributes'] ) ) {
			foreach ( $v['attributes'] as $k => $val ) {
				$attrs_map[ (string) $k ] = (string) $val;
			}
		}

		$entry = [
			'id'             => isset( $v['variation_id'] ) ? (int) $v['variation_id'] : 0,
			'attrs'          => $attrs_map,
			'in_stock'       => ! empty( $v['is_in_stock'] ),
			'is_purchasable' => ! empty( $v['is_purchasable'] ),
			'price_html'     => isset( $v['price_html'] ) ? (string) $v['price_html'] : '',
		];

		if ( $with_image && isset( $v['image'] ) && is_array( $v['image'] ) ) {
			$image_payload = [
				'image_src'    => isset( $v['image']['url'] ) ? (string) $v['image']['url'] : '',
				'image_srcset' => isset( $v['image']['srcset'] ) ? (string) $v['image']['srcset'] : '',
				'image_sizes'  => isset( $v['image']['sizes'] ) ? (string) $v['image']['sizes'] : '',
			];

			/**
			 * Filter the per-variation image payload before it's serialized into
			 * the picker's data-variations JSON. Lets sites strip fields, plug in
			 * a different srcset, etc.
			 *
			 * @since 1.11.23
			 *
			 * @param array       $image_payload  ['image_src','image_srcset','image_sizes'] — strings.
			 * @param array       $variation      The full variation array as returned by
			 *                                    WC_Product_Variable::get_available_variations().
			 * @param \WC_Product $product        The variable parent product.
			 */
			$image_payload = (array) apply_filters(
				'freeman_core/variation_swatches/card_image_payload',
				$image_payload,
				$v,
				$product
			);

			$entry += $image_payload;
		}

		return $entry;
	}

	/**
	 * Build a transient key that folds the product id together with every
	 * WC option that affects the formatted price HTML we cache. Changing
	 * any of these in WC → Settings → General invalidates the cache so the
	 * archive picker picks up the new format on the next page load.
	 *
	 * The cached blob contains `from_price` (rendered via wc_price()) and
	 * per-variation `price_html` strings — both depend on:
	 *   • currency code           (e.g. ILS → USD)
	 *   • currency position       (left / right / left-space / right-space)
	 *   • decimal separator       (. vs ,)
	 *   • thousand separator      (, vs . vs space)
	 *   • decimal count
	 *   • shop tax-display mode   (excl vs incl tax)
	 * Any of those changing without busting the cache leaves stale HTML
	 * sitting in transients for up to 6 hours — that was the symptom
	 * 1.7.13 was opened for.
	 */
	private function prepared_transient_key( int $pid ): string {
		$wc_ver        = class_exists( '\WC_Cache_Helper' ) ? \WC_Cache_Helper::get_transient_version( 'product' ) : '';
		$display       = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_tax_display_shop', 'excl' ) : 'excl';
		$curr          = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$curr_pos      = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_currency_pos', 'left' ) : 'left';
		$dec_sep       = function_exists( 'wc_get_price_decimal_separator' ) ? (string) wc_get_price_decimal_separator() : '.';
		$thou_sep      = function_exists( 'wc_get_price_thousand_separator' ) ? (string) wc_get_price_thousand_separator() : ',';
		$decimals      = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		// Wave 2.2 / 4f (1.11.23) — fold the card-image-swap flag state into
		// the cache key so flipping the flag implicitly invalidates stale
		// payloads (which were built without per-variation image fields).
		$swap_flag     = \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'card_image_swap' ) ? '1' : '0';
		// Wave 2.2 / 4b (1.11.24) — same trick for the image-swatches flag so
		// flipping it invalidates payloads that were built without per-option
		// image_url fields.
		$image_flag    = \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'image_swatches' ) ? '1' : '0';
		$signature     = implode( '|', [ $pid, $wc_ver, $display, $curr, $curr_pos, $dec_sep, $thou_sep, $decimals, $swap_flag, $image_flag ] );
		return 'freeman_vs_pd_' . md5( $signature );
	}

	/**
	 * Render the picker template to string.
	 */
	private function render_template( WC_Product $product, array $prepared ): string {
		$template_vars = [
			'product'           => $product,
			'prepared'          => $prepared,
			'max_visible'       => Etucart_VS_Settings::max_visible(),
			// OPT_SHOW_PRICE defaults to ON (1.7.6) — variable products without
			// a single, picker-driven price line just leave WC's default range
			// (e.g. ₪20 – ₪100) sitting next to a non-functional picker, which
			// is exactly the price-doesn't-update bug 1.7.6 was opened for.
			'show_price'        => Etucart_VS_Settings::bool( Etucart_VS_Settings::OPT_SHOW_PRICE, 'yes' ),
			'hide_attr_labels'  => Etucart_VS_Settings::bool( Etucart_VS_Settings::OPT_SHOP_HIDE_ATTR_LABELS, 'yes' ),
			'hide_selected'     => Etucart_VS_Settings::bool( Etucart_VS_Settings::OPT_SHOP_HIDE_SELECTED, 'yes' ),
		];

		ob_start();
		wc_get_template(
			'shop-variation-pick.php',
			$template_vars,
			'etucart-variation-swatches/',
			ETUCART_VS_DIR . 'templates/'
		);
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ *
	 * Asset registration
	 * ------------------------------------------------------------------ */

	public function register_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$fs_base  = dirname( rtrim( ETUCART_VS_DIR, '/' ) ) . '/assets/';
		$url_base = ETUCART_VS_URL . 'assets/';
		$pick     = array( '\\Freeman\\Core\\Core\\Module_Base', 'pick_min_url' );

		wp_register_style(
			'etucart-vs-shop',
			call_user_func( $pick, $fs_base, $url_base, 'css/etucart-shop-swatches.css' ),
			[],
			ETUCART_VS_VERSION
		);

		wp_register_script(
			'etucart-vs-shop',
			call_user_func( $pick, $fs_base, $url_base, 'js/etucart-shop-swatches.js' ),
			[],
			ETUCART_VS_VERSION,
			true
		);

		// Wave 2.2 / 4f (1.11.23) — when the card-image-swap flag is on, expose
		// the CSS selector the JS uses to find the card's main image element.
		// Filterable so themes can override the default WooCommerce loop class.
		$card_image_selector = '';
		if ( \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'card_image_swap' ) ) {
			/**
			 * Filter the CSS selector used to locate the card image element
			 * to swap when a swatch is clicked on the shop / archive.
			 *
			 * @since 1.11.23
			 *
			 * @param string $selector Default `.woocommerce-loop-product__link img, .product-thumb img`.
			 */
			$card_image_selector = (string) apply_filters(
				'freeman_core/variation_swatches/card_image_selector',
				'.woocommerce-loop-product__link img, .product-thumb img'
			);
		}

		wp_localize_script( 'etucart-vs-shop', 'EtucartShopVS', [
			'ajaxUrl'            => \WC_AJAX::get_endpoint( 'etucart_shop_add_to_cart' ),
			'cartUrl'            => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'cardImageSelector'  => $card_image_selector,
			'i18n'               => [
				'choose'       => __( 'Choose an option', 'freeman-core' ),
				'notAvailable' => __( 'This combination is not available', 'freeman-core' ),
				'addedToCart'  => __( 'נוסף לעגלה', 'freeman-core' ),
				'addToCart'    => __( 'הוספה לעגלה', 'freeman-core' ),
				'selectOpts'   => __( 'בחר/י אפשרות', 'freeman-core' ),
				'showMore'     => __( '+%d', 'freeman-core' ),
				'showLess'     => __( '−', 'freeman-core' ),
				'fromPrice'    => __( 'החל מ:', 'freeman-core' ),
				'oos'          => __( 'אזל מהמלאי', 'freeman-core' ),
				'unavailable'  => __( 'לא זמין', 'freeman-core' ),
				'errorGeneric' => __( 'שגיאה, נסו שוב', 'freeman-core' ),
				// Toast (1.6.4) — aria labels.
				'close'        => __( 'סגירה', 'freeman-core' ),
				'notices'      => __( 'הודעות חנות', 'freeman-core' ),
			],
		] );

		// Enqueue when the current request is a context we care about. This
		// is broader than "did we render anything?" on purpose — Elementor
		// products widgets, shortcodes and block grids all render AFTER
		// wp_enqueue_scripts has fired, so deferring enqueue until we knew
		// for sure would miss them. The assets are small and CSS is scoped.
		if ( Etucart_VS_Settings::should_apply_on_current_archive() ) {
			wp_enqueue_style( 'etucart-vs-shop' );
			wp_enqueue_script( 'etucart-vs-shop' );
		}
	}

	/* ------------------------------------------------------------------ *
	 * AJAX add-to-cart (replicates WC_AJAX::add_to_cart but for variations)
	 * ------------------------------------------------------------------ */

	/**
	 * Handle the AJAX add-to-cart for a variable product chosen inline on a
	 * shop grid. Returns WC fragments on success so cart drawers and mini
	 * carts refresh automatically without any extra wiring.
	 */
	public function ajax_add_to_cart(): void {
		// We accept either GET or POST from the frontend; treat POST as
		// canonical and fall back for convenience.
		// Honeypot: the shop-pick template renders an empty `_hp` field as
		// hidden markup. The legitimate JS never reads or sends it, so any
		// non-empty value here is a scraping bot that POSTed every input it
		// saw. Reply with the same shape as a stale-nonce failure so the
		// honeypot can't be distinguished from a legitimate session timeout.
		if ( ! empty( $_POST['_hp'] ) ) {
			wp_send_json_error( [
				'message' => __( 'Session expired. Please refresh the page.', 'freeman-core' ),
			], 400 );
		}

		$product_id   = isset( $_POST['product_id']   ) ? absint( $_POST['product_id']   ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$quantity     = isset( $_POST['quantity']     ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1;
		$nonce        = isset( $_POST['nonce']        ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$variation    = isset( $_POST['variation']    ) && is_array( $_POST['variation'] )
			? wp_unslash( $_POST['variation'] )
			: [];

		if ( ! $product_id || ! wp_verify_nonce( $nonce, 'etucart_vs_shop_' . $product_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Session expired. Please refresh the page.', 'freeman-core' ),
			], 400 );
		}

		/**
		 * Fires before the shop-grid add-to-cart handler does any work.
		 *
		 * Return a WP_Error from a callback on this action to short-circuit
		 * the request — membership / B2B plugins should hook here to gate
		 * access for restricted products when the shopper is logged out.
		 *
		 * Silence = allow. The subsequent `woocommerce_add_to_cart_validation`
		 * filter still runs so WC core stock/quota checks apply.
		 *
		 * @param int   $product_id
		 * @param int   $variation_id
		 * @param array $variation
		 */
		$gate = apply_filters( 'freeman_core_variation_swatches_shop_add_to_cart_gate', null, $product_id, $variation_id, $variation );
		$gate = apply_filters_deprecated(
			'freeman/swatches/shop_add_to_cart_gate',
			array( $gate, $product_id, $variation_id, $variation ),
			'1.9.0',
			'freeman_core_variation_swatches_shop_add_to_cart_gate'
		);
		if ( is_wp_error( $gate ) ) {
			wp_send_json_error( [
				'message' => $gate->get_error_message() ?: __( 'This product is not available.', 'freeman-core' ),
			], 403 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			wp_send_json_error( [
				'message' => __( 'Product not available.', 'freeman-core' ),
			], 404 );
		}

		$is_simple_pick = $product->is_type( 'simple' );

		// Variable products still require a resolved variation_id. Simple
		// products must NOT carry one (we ignore any stray value the client
		// might have sent).
		if ( ! $is_simple_pick ) {
			if ( ! $product->is_type( 'variable' ) ) {
				wp_send_json_error( [
					'message' => __( 'Product not available.', 'freeman-core' ),
				], 404 );
			}
			if ( ! $variation_id ) {
				wp_send_json_error( [
					'message' => __( 'This combination is not available.', 'freeman-core' ),
				], 400 );
			}
		} else {
			$variation_id = 0;
		}

		// Sanitize attribute map ( attribute_pa_color => red ). Empty for
		// simple products by design.
		$clean_variation = [];
		if ( ! $is_simple_pick ) {
			foreach ( $variation as $k => $val ) {
				$k = sanitize_text_field( (string) $k );
				if ( '' === $k ) {
					continue;
				}
				$clean_variation[ $k ] = sanitize_title( (string) $val );
			}
		}

		if ( $quantity <= 0 ) {
			$quantity = 1;
		}

		// Clamp simple-product quantity against the product's configured max
		// so a tampered client can't overshoot stock limits. Variable
		// products get their per-variation clamp inside WC validation below.
		if ( $is_simple_pick ) {
			$max_qty = (int) $product->get_max_purchase_quantity();
			if ( $max_qty > 0 && $quantity > $max_qty ) {
				$quantity = $max_qty;
			}
		}

		$passed = apply_filters(
			'woocommerce_add_to_cart_validation',
			true,
			$product_id,
			$quantity,
			$variation_id,
			$clean_variation
		);

		if ( ! $passed ) {
			// WC core notices contain the reason (stock, validation, etc.).
			$notices  = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : [];
			$msg      = '';
			if ( ! empty( $notices ) ) {
				$first = reset( $notices );
				if ( is_array( $first ) && isset( $first['notice'] ) ) {
					$msg = wp_strip_all_tags( $first['notice'] );
				} elseif ( is_string( $first ) ) {
					$msg = wp_strip_all_tags( $first );
				}
				if ( function_exists( 'wc_clear_notices' ) ) {
					wc_clear_notices();
				}
			}
			wp_send_json_error( [
				'message' => $msg ?: __( 'This combination is not available.', 'freeman-core' ),
			], 400 );
		}

		// Simple products: call add_to_cart with just product_id + quantity.
		// Passing 0 as variation_id works in WC core but signalling intent
		// explicitly keeps the call path identical to WC's own simple AJAX
		// add-to-cart endpoint.
		if ( $is_simple_pick ) {
			$cart_item_key = WC()->cart->add_to_cart(
				$product_id,
				$quantity
			);
		} else {
			$cart_item_key = WC()->cart->add_to_cart(
				$product_id,
				$quantity,
				$variation_id,
				$clean_variation
			);
		}

		if ( ! $cart_item_key ) {
			$notices = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : [];
			$msg     = '';
			if ( ! empty( $notices ) ) {
				$first = reset( $notices );
				if ( is_array( $first ) && isset( $first['notice'] ) ) {
					$msg = wp_strip_all_tags( $first['notice'] );
				} elseif ( is_string( $first ) ) {
					$msg = wp_strip_all_tags( $first );
				}
				if ( function_exists( 'wc_clear_notices' ) ) {
					wc_clear_notices();
				}
			}
			wp_send_json_error( [
				'message' => $msg ?: __( 'Could not add to cart.', 'freeman-core' ),
			], 400 );
		}

		// Fire the core action so plugins listening for it (mini-cart,
		// fragments, analytics) get their normal signal.
		do_action(
			'woocommerce_ajax_added_to_cart',
			$product_id
		);

		// Reuse WC's built-in fragments path so theme mini-carts / cart
		// drawers refresh identically to a native add-to-cart.
		if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
			wc_add_to_cart_message( [ $product_id => $quantity ], true );
		}

		/*
		 * Clear queued WC notices before returning the fragments payload (1.6.4).
		 *
		 * Why: when the shop picker adds an item via our AJAX endpoint, our own
		 * toast gives the user local feedback ("נוסף לעגלה"). WC core, plus any
		 * third-party plugin hooking `woocommerce_add_to_cart` /
		 * `woocommerce_ajax_added_to_cart`, can queue a session notice such as
		 * "המוצר נוסף לעגלה. מעבר לסל הקניות →" (X was added. View cart →).
		 * That notice sits in the session and flushes on the NEXT request that
		 * renders `woocommerce-notices-wrapper` — most commonly when the user
		 * clicks through to a product page, producing a phantom "View cart"
		 * link they never expected.
		 *
		 * By clearing notices here, we keep the shop-picker flow entirely
		 * self-contained: our toast is the ONLY confirmation, nothing leaks
		 * into subsequent pageviews. Errors are intentionally cleared in the
		 * error branches above for the same reason — consistency across both
		 * paths.
		 *
		 * This does NOT affect the PDP / single-product buy box: that path
		 * uses WC's native `wc-ajax=add_to_cart` endpoint and its own message
		 * handling, both untouched.
		 */
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		WC_AJAX::get_refreshed_fragments();
	}
}

endif; // class_exists Etucart_VS_Archive
