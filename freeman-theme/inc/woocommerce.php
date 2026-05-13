<?php
/**
 * Theme-level WooCommerce tweaks.
 *
 * Kept minimal — the heavy lifting is inside Freeman Core modules.
 *
 * @package FreemanTheme
 */

defined( 'ABSPATH' ) || exit;

// Declare HPOS + Cart/Checkout Blocks compatibility at the theme level so
// admins don't see the incompatibility notice. Core declares the same; doing
// it twice is a no-op but safe.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// Mobile-columns override for product archives. Emission is gated on the
// Customizer setting being explicitly 1/2/3/4 — the 'default' sentinel
// skips entirely, preserving existing behaviour. `!important` is acceptable
// because the rule only ships when the admin has opted in; `display: grid`
// is forced because some loops render as flex/block which would make
// grid-template-columns a no-op.
add_action(
	'wp_head',
	static function () {
		if ( ! function_exists( 'is_post_type_archive' ) ) {
			return;
		}
		$is_product_archive = is_post_type_archive( 'product' )
			|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
		if ( ! $is_product_archive ) {
			return;
		}
		$cols = get_theme_mod( 'freeman_shop_cols_mobile', 'default' );
		if ( ! in_array( $cols, array( '1', '2', '3', '4' ), true ) ) {
			return;
		}
		printf(
			'<style id="freeman-shop-cols-mobile">@media (max-width:767px){.woocommerce ul.products,.woocommerce ul.products.elementor-grid{display:grid !important;grid-template-columns:repeat(%d,minmax(0,1fr)) !important;}}</style>' . "\n",
			(int) $cols
		);
	}
);
