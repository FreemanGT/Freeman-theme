<?php
/**
 * Variation Swatches settings — legacy WooCommerce → Settings → Products tab.
 *
 * As of Wave 2.2 / 4g (freeman-core 1.11.45) the editable settings UI has
 * moved to **Freeman → Variation Swatches** (the Settings_Hub page, stored
 * under `freeman_core_variation_swatches_*` and read via {@see
 * \Freeman\Core\Modules\VariationSwatches\Settings_Reader}). This class no
 * longer renders the form. Per Hard Rule #2 the WooCommerce sub-section
 * (`?section=etucart_vs_shop_pick`) is **not** removed — it stays registered
 * so the URL keeps resolving — but `add_settings()` now returns only a short
 * "moved" notice. The `OPT_*` constants and the static read helpers (`bool`,
 * `max_visible`, `excluded_category_ids`, `should_apply_on_current_archive`)
 * are unchanged; they delegate reads to `Settings_Reader`.
 *
 * Hard Rule #3: this `legacy/` edit was approved for Wave 4g (see the
 * roadmap Wave 2.2 / 4g entry). The historic `etucart_vs_*` option keys are
 * never deleted (§4.5 zero-downtime decision).
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

	/**
	 * Wave 2.2 / 4g: the editable form moved to Freeman → Variation Swatches.
	 * The sub-section stays registered (Hard Rule #2 — the URL must keep
	 * resolving) but renders only a short "moved" notice. WooCommerce's save
	 * pipeline has nothing to write here now, so the legacy `etucart_vs_*`
	 * option keys stop being written from this screen (they remain readable
	 * forever via Settings_Reader's legacy fallback — §4.5).
	 */
	public function add_settings( array $settings, string $current_section ): array {
		if ( self::SECTION_ID !== $current_section ) {
			return $settings;
		}

		return [
			[
				'title' => __( 'Variation Swatches settings have moved', 'freeman-core' ),
				'type'  => 'title',
				'desc'  => __( 'These settings are now under Freeman → Variation Swatches.', 'freeman-core' ),
				'id'    => 'etucart_vs_shop_pick_moved',
			],
			[
				'type' => 'sectionend',
				'id'   => 'etucart_vs_shop_pick_moved',
			],
		];
	}
}

endif; // class_exists Etucart_VS_Settings
