<?php
/**
 * Settings for the shop-page compact variation picker (new in 1.6.0).
 *
 * Adds a sub-section under WooCommerce → Settings → Products → "Shop swatches".
 * Feature flag defaults to ENABLED on first install so the new picker is visible
 * out-of-the-box on upgrade; shop owners can toggle it off from the same screen.
 *
 * The single-product buy box is NOT controlled from here — these settings only
 * affect the archive / shop-grid picker introduced in 1.6.0.
 *
 * Storage strategy: each setting is its own `etucart_vs_shop_*` option so
 * WooCommerce's native settings-save pipeline (WC_Admin_Settings::save_fields)
 * round-trips cleanly for checkboxes, numbers and multiselects. Nested
 * bracket IDs (foo[bar]) do not survive WC's save path — it update_option()s
 * the literal string key, not the nested subkey.
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Etucart_VS_Settings' ) ) :

class Etucart_VS_Settings {

	/** Settings sub-section slug inside WC Settings → Products. */
	public const SECTION_ID = 'etucart_vs_shop_pick';

	/** Individual option keys, one per field. */
	public const OPT_ENABLED             = 'etucart_vs_shop_enabled';
	public const OPT_MAX_VISIBLE         = 'etucart_vs_shop_max_visible';
	public const OPT_SHOW_PRICE          = 'etucart_vs_shop_show_price';
	public const OPT_APPLY_SHOP          = 'etucart_vs_shop_apply_shop';
	public const OPT_APPLY_CATEGORY      = 'etucart_vs_shop_apply_category';
	public const OPT_APPLY_TAG           = 'etucart_vs_shop_apply_tag';
	public const OPT_APPLY_SEARCH        = 'etucart_vs_shop_apply_search';
	public const OPT_APPLY_RELATED       = 'etucart_vs_shop_apply_related';
	public const OPT_EXCLUDED_CATEGORIES = 'etucart_vs_shop_excluded_categories';

	/** Single-product buy box options (new in 1.6.1). */
	public const OPT_PDP_HIDE_OOS        = 'etucart_vs_pdp_hide_oos';

	/** Archive / shop-grid OOS hiding (new in 1.6.6). */
	public const OPT_SHOP_HIDE_OOS       = 'etucart_vs_shop_hide_oos';

	/** Archive / shop-grid: skip pre-selecting any variation (new in 1.7.4). */
	public const OPT_SHOP_NO_PRESELECT   = 'etucart_vs_shop_no_preselect';

	/** Archive / shop-grid: hide the attribute label row e.g. "Size:" (1.7.8). */
	public const OPT_SHOP_HIDE_ATTR_LABELS = 'etucart_vs_shop_hide_attr_labels';

	/** Archive / shop-grid: hide the "selected option" text row (1.7.9). */
	public const OPT_SHOP_HIDE_SELECTED    = 'etucart_vs_shop_hide_selected';

	public function register(): void {
		add_filter( 'woocommerce_get_sections_products', [ $this, 'add_section' ] );
		add_filter( 'woocommerce_get_settings_products', [ $this, 'add_settings' ], 10, 2 );
	}

	public static function bool( string $option_key, string $default = 'yes' ): bool {
		// Most flags default to 'yes' (the feature's core toggle is OPT_ENABLED).
		// A few fields want to default OFF (e.g. OPT_SHOW_PRICE, which is opt-in).
		// Callers pass 'no' for those.
		return 'yes' === \Freeman\Core\Modules\VariationSwatches\Settings_Reader::get( $option_key, $default );
	}

	public static function max_visible(): int {
		$v = absint( \Freeman\Core\Modules\VariationSwatches\Settings_Reader::get( self::OPT_MAX_VISIBLE, 5 ) );
		if ( $v < 1 )  $v = 1;
		if ( $v > 50 ) $v = 50;
		return $v;
	}

	public static function excluded_category_ids(): array {
		$raw = \Freeman\Core\Modules\VariationSwatches\Settings_Reader::get( self::OPT_EXCLUDED_CATEGORIES, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		return array_values( array_filter( array_map( 'absint', $raw ) ) );
	}

	/**
	 * Should the compact picker render on the *current* archive request?
	 */
	public static function should_apply_on_current_archive(): bool {
		if ( ! self::bool( self::OPT_ENABLED ) ) {
			return false;
		}
		if ( is_admin() ) {
			return false;
		}
		if ( function_exists( 'is_shop' ) && is_shop() && self::bool( self::OPT_APPLY_SHOP ) ) {
			return true;
		}
		if ( function_exists( 'is_product_category' ) && is_product_category() && self::bool( self::OPT_APPLY_CATEGORY ) ) {
			return true;
		}
		if ( function_exists( 'is_product_tag' ) && is_product_tag() && self::bool( self::OPT_APPLY_TAG ) ) {
			return true;
		}
		if ( function_exists( 'is_search' ) && is_search() && self::bool( self::OPT_APPLY_SEARCH ) ) {
			return true;
		}
		// Cart / checkout / account are never product loops — keep them off.
		if ( ( function_exists( 'is_cart' )         && is_cart() )
			|| ( function_exists( 'is_checkout' )    && is_checkout() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() )
		) {
			return false;
		}
		// Single-product page: any loop rendered here is Related / Upsells /
		// Cross-sells / "Recently viewed" etc. Controlled by its own toggle so
		// a shop owner can disable just the PDP loops if they want to.
		if ( is_singular( 'product' ) ) {
			return self::bool( self::OPT_APPLY_RELATED );
		}
		// Any other loop context (shop fallback, shortcode grid on a page, an
		// Elementor products widget, block-based grid on home, etc.).
		return true;
	}

	public function add_section( array $sections ): array {
		$sections[ self::SECTION_ID ] = __( 'Shop swatches', 'freeman-core' );
		return $sections;
	}

	public function add_settings( array $settings, string $current_section ): array {
		if ( self::SECTION_ID !== $current_section ) {
			return $settings;
		}

		// Build product-category choices for the excluded-categories multi.
		$category_choices = [];
		$terms            = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		] );
		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$category_choices[ (string) $term->term_id ] = $term->name;
			}
		}

		return [
			[
				'title' => __( 'Shop-page variation picker', 'freeman-core' ),
				'type'  => 'title',
				'desc'  => __( 'Compact inline color / size picker shown on shop and archive pages instead of the default "Choose options" button. The single-product buy box is not affected by these settings.', 'freeman-core' ),
				'id'    => 'etucart_vs_shop_pick_title',
			],
			[
				'title'   => __( 'Enable on shop / archive pages', 'freeman-core' ),
				'desc'    => __( 'Replace the default "Choose options" link with the compact picker.', 'freeman-core' ),
				'id'      => self::OPT_ENABLED,
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			[
				'title'             => __( 'Options visible before "+N"', 'freeman-core' ),
				'desc'              => __( 'Per attribute. Anything above this count collapses behind a "+N" reveal pill.', 'freeman-core' ),
				'id'                => self::OPT_MAX_VISIBLE,
				'type'              => 'number',
				'default'           => 5,
				'custom_attributes' => [ 'min' => '1', 'max' => '50', 'step' => '1' ],
			],
			[
				'title'   => __( 'Show price', 'freeman-core' ),
				'desc'    => __( 'Show "החל מ: ₪X" above the Add to cart button (updates to the selected variation\'s price once all attributes are picked). When on, also hides WooCommerce\'s default range price (e.g. ₪20 – ₪100) on the same card so the two displays don\'t fight.', 'freeman-core' ),
				'id'      => self::OPT_SHOW_PRICE,
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			[
				'title'         => __( 'Apply on', 'freeman-core' ),
				'desc'          => __( 'Shop page', 'freeman-core' ),
				'id'            => self::OPT_APPLY_SHOP,
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => 'start',
			],
			[
				'desc'          => __( 'Product category pages', 'freeman-core' ),
				'id'            => self::OPT_APPLY_CATEGORY,
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => '',
			],
			[
				'desc'          => __( 'Product tag pages', 'freeman-core' ),
				'id'            => self::OPT_APPLY_TAG,
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => '',
			],
			[
				'desc'          => __( 'Search results', 'freeman-core' ),
				'id'            => self::OPT_APPLY_SEARCH,
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => '',
			],
			[
				'desc'          => __( 'Related / Upsells / Cross-sells on product pages', 'freeman-core' ),
				'id'            => self::OPT_APPLY_RELATED,
				'type'          => 'checkbox',
				'default'       => 'yes',
				'checkboxgroup' => 'end',
			],
			[
				'title'   => __( 'Exclude categories', 'freeman-core' ),
				'desc'    => __( 'Products in these categories keep the default "Choose options" link.', 'freeman-core' ),
				'id'      => self::OPT_EXCLUDED_CATEGORIES,
				'type'    => 'multiselect',
				'options' => $category_choices,
				'class'   => 'wc-enhanced-select',
				'default' => [],
				'css'     => 'min-width: 350px;',
			],
			[
				'title'   => __( 'Hide out-of-stock options', 'freeman-core' ),
				'desc'    => __( 'On shop / archive cards, only show attribute options (colors, sizes, etc.) that have at least one in-stock variation. Sold-out options are removed from the picker entirely. Turn off to keep showing every option and let customers see the sold-out ones too.', 'freeman-core' ),
				'id'      => self::OPT_SHOP_HIDE_OOS,
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			[
				'title'   => __( 'No pre-selected variation on archive', 'freeman-core' ),
				'desc'    => __( 'Render every shop / archive picker with nothing pre-selected. The customer must actively click a swatch. Ignores both the product editor\'s manual defaults and the auto-cheapest pick — applies on shop / category / search / loop contexts only. The single-product page is unaffected.', 'freeman-core' ),
				'id'      => self::OPT_SHOP_NO_PRESELECT,
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			[
				'title'   => __( 'Hide attribute labels', 'freeman-core' ),
				'desc'    => __( 'On shop / archive cards, hide the attribute label row (e.g. "Size:" / "Colour:"). The swatches themselves are usually self-explanatory and the labels add visual noise to the card. The currently-selected value text stays. PDP buy-box is unaffected.', 'freeman-core' ),
				'id'      => self::OPT_SHOP_HIDE_ATTR_LABELS,
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			[
				'title'   => __( 'Hide selected-option text', 'freeman-core' ),
				'desc'    => __( 'On shop / archive cards, hide the "Choose an option" / "{selected value}" text row that sits above the swatches. The swatches\' active state already shows what\'s picked. PDP buy-box is unaffected.', 'freeman-core' ),
				'id'      => self::OPT_SHOP_HIDE_SELECTED,
				'type'    => 'checkbox',
				'default' => 'yes',
			],
			[
				'type' => 'sectionend',
				'id'   => 'etucart_vs_shop_pick_title',
			],

			// ---- Single-product buy box (PDP) --------------------------------
			[
				'title' => __( 'Product page buy box', 'freeman-core' ),
				'type'  => 'title',
				'desc'  => __( 'These settings only affect the variation buy box on the single product page.', 'freeman-core' ),
				'id'    => 'etucart_vs_pdp_title',
			],
			[
				'title'   => __( 'Hide out-of-stock options', 'freeman-core' ),
				'desc'    => __( 'On the product page, only show variation options (colors, sizes, etc.) that have at least one in-stock variation. Options that are completely sold out won\'t appear at all. Off by default — out-of-stock options are shown greyed with a strike-through so customers can see what sizes/colors this product normally comes in. OOS hiding happens on shop / archive cards instead (see the setting in the section above).', 'freeman-core' ),
				'id'      => self::OPT_PDP_HIDE_OOS,
				'type'    => 'checkbox',
				'default' => 'no',
			],
			[
				'type' => 'sectionend',
				'id'   => 'etucart_vs_pdp_title',
			],
		];
	}
}

endif; // class_exists Etucart_VS_Settings
