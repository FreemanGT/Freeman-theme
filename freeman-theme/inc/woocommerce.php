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
