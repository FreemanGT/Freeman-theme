<?php
/**
 * Theme-level Customizer controls.
 *
 * @package FreemanTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize the mobile-columns Customizer value.
 *
 * Whitelist: '1'|'2'|'3'|'4'|'default'. Anything else collapses to 'default'
 * (the no-override sentinel). The Customizer's `choices` array is UI only —
 * validation has to happen here because the value lands in inline CSS.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function freeman_theme_sanitize_shop_cols_mobile( $value ) {
	$allowed = array( '1', '2', '3', '4', 'default' );
	$value   = is_scalar( $value ) ? (string) $value : '';
	return in_array( $value, $allowed, true ) ? $value : 'default';
}

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		$wp_customize->add_setting(
			'freeman_shop_cols_mobile',
			array(
				'default'           => 'default',
				'type'              => 'theme_mod',
				'capability'        => 'edit_theme_options',
				'transport'         => 'refresh',
				'sanitize_callback' => 'freeman_theme_sanitize_shop_cols_mobile',
			)
		);

		$wp_customize->add_control(
			'freeman_shop_cols_mobile',
			array(
				'label'       => __( 'Mobile columns', 'freeman-theme' ),
				'description' => __( 'Products per row on phones (≤767px) on shop, product category / tag, and product search archives. "Default" leaves theme + WooCommerce defaults untouched.', 'freeman-theme' ),
				'section'     => 'woocommerce_product_catalog',
				'type'        => 'select',
				'choices'     => array(
					'default' => __( 'Default (don\'t override)', 'freeman-theme' ),
					'1'       => '1',
					'2'       => '2',
					'3'       => '3',
					'4'       => '4',
				),
			)
		);
	}
);
