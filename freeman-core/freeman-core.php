<?php
/**
 * Plugin Name:       Freeman Core
 * Plugin URI:        https://freemandigital.com/freeman-core
 * Description:       Unified WooCommerce functionality for the Freeman Theme. Hosts eight independently togglable modules: Variation Swatches, Restock Notify, Variable Stock Fix, Product Feed, Infinite Scroll, Cheapest Default Variation, Category Slider, Product Slider. Owns all data and business logic so features survive a theme switch.
 * Version:           1.10.14
 * Author:            Freeman Digital
 * Author URI:        https://freemandigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       freeman-core
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   9.5
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

define( 'FREEMAN_CORE_VERSION',  '1.10.14' );
define( 'FREEMAN_CORE_FILE',     __FILE__ );
define( 'FREEMAN_CORE_PATH',     plugin_dir_path( __FILE__ ) );
define( 'FREEMAN_CORE_URL',      plugin_dir_url( __FILE__ ) );
define( 'FREEMAN_CORE_BASENAME', plugin_basename( __FILE__ ) );
define( 'FREEMAN_CORE_ASSETS',   FREEMAN_CORE_URL . 'assets' );

/**
 * PSR-4 autoloader (dependency-free, no Composer required at runtime).
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Freeman\\Core\\';
		$base   = FREEMAN_CORE_PATH . 'src/';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = $base . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

// Declare HPOS + Cart/Checkout Blocks compatibility — must be before
// WooCommerce inspects compatible plugins.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FREEMAN_CORE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FREEMAN_CORE_FILE, true );
		}
	}
);

// Activation / deactivation hooks delegate to the plugin.
register_activation_hook( __FILE__, array( '\Freeman\Core\Core\Plugin', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( '\Freeman\Core\Core\Plugin', 'on_deactivate' ) );

// Boot on plugins_loaded so WooCommerce is ready.
add_action(
	'plugins_loaded',
	static function () {
		\Freeman\Core\Core\Plugin::instance()->boot();
	},
	5
);
