<?php
/**
 * Shop Filters — storefront label resolver.
 *
 * Every user-facing string in the filter panel is overridable from the
 * Freeman → Shop Filters settings page, so the site owner can set Hebrew (or any
 * wording) without code (§4.2 — English defaults, locale-specific opt-in). Each
 * label is stored under its own option (`freeman_core_shop_filters_label_<key>`,
 * the same name Settings_Hub writes for the matching `label_<key>` field); an
 * unset / blank option falls back to the English default here, so flag-off
 * behaviour is byte-identical to before this phase.
 *
 * This map is the single source of truth: the module's settings_schema() builds
 * its text fields from defaults(), and the templates resolve text through get().
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Label resolver.
 */
final class Labels {

	const OPTION_PREFIX = 'freeman_core_shop_filters_label_';

	/**
	 * Canonical label map: short key => [ admin field label, storefront default ].
	 * The defaults reproduce the strings the templates shipped with in 6.3b.
	 *
	 * @return array<string,array{label:string,default:string}>
	 */
	public static function defaults() {
		return array(
			'toggle'         => array(
				'label'   => __( 'Mobile filter button', 'freeman-core' ),
				'default' => __( 'Filter sizes & prices', 'freeman-core' ),
			),
			'panel_title'    => array(
				'label'   => __( 'Drawer title', 'freeman-core' ),
				'default' => __( 'Filter', 'freeman-core' ),
			),
			'panel_aria'     => array(
				'label'   => __( 'Drawer accessible name', 'freeman-core' ),
				'default' => __( 'Filter products', 'freeman-core' ),
			),
			'close'          => array(
				'label'   => __( 'Close button label', 'freeman-core' ),
				'default' => __( 'Close', 'freeman-core' ),
			),
			'categories'     => array(
				'label'   => __( 'Categories heading', 'freeman-core' ),
				'default' => __( 'Categories', 'freeman-core' ),
			),
			'price'          => array(
				'label'   => __( 'Price heading', 'freeman-core' ),
				'default' => __( 'Price', 'freeman-core' ),
			),
			'sort'           => array(
				'label'   => __( 'Sort heading', 'freeman-core' ),
				'default' => __( 'Sort by', 'freeman-core' ),
			),
			'categories_aria' => array(
				'label'   => __( 'Categories accessible name', 'freeman-core' ),
				'default' => __( 'Product categories', 'freeman-core' ),
			),
			'clear_all'      => array(
				'label'   => __( 'Clear-all button', 'freeman-core' ),
				'default' => __( 'Clear all', 'freeman-core' ),
			),
			'apply'          => array(
				'label'   => __( 'Apply button (mobile)', 'freeman-core' ),
				'default' => __( 'Apply filters', 'freeman-core' ),
			),
			'clear'          => array(
				'label'   => __( 'Clear button (mobile)', 'freeman-core' ),
				'default' => __( 'Clear', 'freeman-core' ),
			),
			'count_singular' => array(
				'label'   => __( 'Result count, singular (use %d)', 'freeman-core' ),
				'default' => __( '%d product', 'freeman-core' ),
			),
			'count_plural'   => array(
				'label'   => __( 'Result count, plural (use %d)', 'freeman-core' ),
				'default' => __( '%d products', 'freeman-core' ),
			),
		);
	}

	/**
	 * Resolve a label by short key. Returns the saved override when non-empty,
	 * otherwise the English default.
	 *
	 * @param string $key Short key (e.g. 'clear_all').
	 * @return string
	 */
	public static function get( $key ) {
		$key      = (string) $key;
		$defaults = self::defaults();
		$default  = isset( $defaults[ $key ] ) ? (string) $defaults[ $key ]['default'] : '';

		$value = (string) get_option( self::OPTION_PREFIX . $key, '' );
		return '' !== trim( $value ) ? $value : $default;
	}

	/**
	 * Format the result-count line, choosing singular / plural by count.
	 *
	 * @param int $count Product count.
	 * @return string
	 */
	public static function count_text( $count ) {
		$count    = (int) $count;
		$template = ( 1 === $count ) ? self::get( 'count_singular' ) : self::get( 'count_plural' );
		return sprintf( $template, $count );
	}
}
