<?php
/**
 * Plugin Name: Freeman Digital
 * Plugin URI: https://freeman.digital
 * Description: All-in-one WordPress & WooCommerce optimization — database indexes, query tuning, autoload optimizer, security hardening, speed tuning, transient management, and bloat removal. Built for Elementor + WooCommerce stores.
 * Version: 1.7.3
 * Author: Freeman Digital
 * Author URI: https://freeman.digital
 * License: GPL v3
 * Text Domain: freeman-digital
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

if (!defined('ABSPATH')) exit;

define('FD_VERSION', '1.7.3');
define('FD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FD_PLUGIN_FILE', __FILE__);
define('FD_OPT', 'fd_settings');

// Load core classes
require_once FD_PLUGIN_DIR . 'includes/class-fd-core.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-admin.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-indexes.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-query-optimizer.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-woocommerce.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-security.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-speed.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-database.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-bloat.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-autoload.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-frontend.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-profiler.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-admin-cache.php';
require_once FD_PLUGIN_DIR . 'includes/class-fd-activity-log.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once FD_PLUGIN_DIR . 'includes/class-fd-cli.php';
}

// Load translations from the bundled /languages directory (falls back to WP language packs).
add_action('init', function () {
    load_plugin_textdomain('freeman-digital', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Boot
add_action('plugins_loaded', function () {
    FD_Core::get_instance();
}, 5);

// Activation
register_activation_hook(__FILE__, function () {
    if (!get_option(FD_OPT)) {
        update_option(FD_OPT, FD_Core::get_defaults());
    }
    if (!wp_next_scheduled('fd_daily_maintenance')) {
        // Schedule for 3 AM in the site's configured timezone, always in the future.
        // strtotime('03:00') returns today 3am in server local time, which may be in the past
        // (WP-Cron fires immediately) AND wrong timezone. Use wp_timezone() + "tomorrow".
        try {
            $tz = wp_timezone();
            $next = new DateTime('tomorrow 03:00', $tz);
            $timestamp = $next->getTimestamp();
        } catch (Exception $e) {
            // Fallback: 24 hours from now
            $timestamp = time() + DAY_IN_SECONDS;
        }
        wp_schedule_event($timestamp, 'daily', 'fd_daily_maintenance');
    }
    // Create profiler table
    FD_Profiler::maybe_create_table();
});

// Deactivation
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('fd_daily_maintenance');
});
