<?php
/**
 * Faithful activation simulation — mirrors WP's activation flow.
 *
 * WP does, roughly:
 *   1. include the plugin file (runs top-level code, autoloader registration, add_action calls, register_activation_hook)
 *   2. calls the activation callback
 */

if ( false === (int) ini_get( 'error_reporting' ) ) {
	// preserve caller's setting (build.sh silences warnings)
} else {
	error_reporting( E_ALL );
}
ini_set( 'display_errors', '1' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/fake-wp/' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', __DIR__ . '/../' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$stub_list = array(
	'add_action', 'remove_action', 'add_filter', 'remove_filter',
	'do_action',
	'register_activation_hook', 'register_deactivation_hook', 'register_uninstall_hook',
	'plugin_basename',
	'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_style', 'wp_register_script',
	'wp_localize_script', 'wp_add_inline_script',
	'wp_schedule_event', 'wp_next_scheduled', 'wp_clear_scheduled_hook', 'wp_schedule_single_event',
	'wp_upload_dir',
	'load_plugin_textdomain', 'load_child_theme_textdomain',
	'esc_html__', 'esc_attr__', 'esc_html', 'esc_attr', 'esc_url', 'esc_textarea', 'wp_kses_post', 'esc_url_raw', 'esc_js',
	'_e', 'esc_html_e', 'esc_attr_e',
	'admin_url', 'home_url', 'site_url', 'rest_url',
	'is_admin', 'is_rtl', 'current_user_can', 'wp_is_mobile',
	'wp_verify_nonce', 'check_admin_referer', 'check_ajax_referer', 'wp_create_nonce', 'wp_nonce_field',
	'sanitize_text_field', 'sanitize_email', 'sanitize_key',
	'trailingslashit', 'untrailingslashit',
	'deactivate_plugins', 'delete_plugins',
	'register_rest_route', 'rest_ensure_response',
	'wp_safe_redirect', 'wp_send_json_success', 'wp_send_json_error',
	'get_transient', 'set_transient', 'delete_transient',
	'add_rewrite_rule', 'flush_rewrite_rules', 'get_query_var',
	'current_time', 'date_i18n', 'wp_date',
	'wp_mkdir_p', 'wp_json_encode',
	'did_action', 'has_action', 'doing_action',
	'wp_generate_password',
	'wp_parse_args',
	'absint',
	'locate_template',
	'is_plugin_active',
	'get_plugins',
	'wp_strip_all_tags',
	'wp_send_json',
);

foreach ( $stub_list as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return null; }" );
	}
}

// more tailored stubs
if ( ! function_exists( 'plugin_dir_path' ) ) {
	eval( 'function plugin_dir_path($f){return rtrim(dirname($f),"/")."/";}' );
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	eval( 'function plugin_dir_url($f){return "https://example.test/wp-content/plugins/freeman-core/";}' );
}
if ( ! function_exists( '__' ) ) {
	eval( 'function __($s,$d=null){return $s;}' );
}
if ( ! function_exists( 'apply_filters' ) ) {
	eval( 'function apply_filters($tag,$value){$args=func_get_args();return $args[1];}' );
}
if ( ! function_exists( 'trailingslashit' ) ) {
	// (already stubbed). Replace with proper version.
}

$options = array();
if ( ! function_exists( 'get_option' ) ) {
	eval( 'function get_option($k,$d=false){global $options; return array_key_exists($k,$options)?$options[$k]:$d;}' );
}
if ( ! function_exists( 'update_option' ) ) {
	eval( 'function update_option($k,$v){global $options; $options[$k]=$v; return true;}' );
}
if ( ! function_exists( 'delete_option' ) ) {
	eval( 'function delete_option($k){global $options; unset($options[$k]); return true;}' );
}
if ( ! function_exists( 'add_option' ) ) {
	eval( 'function add_option($k,$v){global $options; if(!isset($options[$k])){$options[$k]=$v;} return true;}' );
}

// Hooks registry so register_activation_hook is retrievable.
$GLOBALS['__activation_callbacks'] = array();
if ( function_exists( 'register_activation_hook' ) ) {
	// Override via eval - not possible. Instead, manually call Plugin::on_activate below.
}

// wpdb stub
class StubWPDB {
	public $prefix = 'wp_';
	public $options = 'wp_options';
	public function esc_like( $s ) { return $s; }
	public function prepare( $q, ...$a ) { return $q; }
	public function query( $q ) { return 0; }
	public function get_var( $q ) { return 0; }
	public function get_row( $q ) { return null; }
	public function get_results( $q ) { return array(); }
	public function insert( $t, $d ) { return 1; }
	public function update( $t, $d, $w ) { return 1; }
	public function delete( $t, $w ) { return 1; }
	public function get_charset_collate() { return ''; }
}
$wpdb = new StubWPDB();
$GLOBALS['wpdb'] = $wpdb;

// Create a fake ABSPATH wp-admin/includes/upgrade.php stub
@mkdir( ABSPATH . 'wp-admin/includes/', 0755, true );
if ( ! file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
	file_put_contents( ABSPATH . 'wp-admin/includes/upgrade.php', "<?php function dbDelta(\$q){ return array(); } " );
}
if ( ! file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
	file_put_contents( ABSPATH . 'wp-admin/includes/plugin.php', "<?php function get_plugins(){return array();} function is_plugin_active(\$f){return false;}" );
}

echo "=== Including plugin file ===\n";
require_once __DIR__ . '/../freeman-core/freeman-core.php';
echo "plugin file loaded OK\n\n";

echo "=== Calling activation ===\n";
try {
	\Freeman\Core\Core\Plugin::on_activate();
	echo "activation OK\n";
} catch ( \Throwable $e ) {
	echo "FATAL: " . get_class( $e ) . ": " . $e->getMessage() . "\n";
	echo $e->getTraceAsString() . "\n";
	exit( 1 );
}

echo "\n=== Calling plugins_loaded boot (is_admin=false) ===\n";
try {
	\Freeman\Core\Core\Plugin::instance()->boot();
	echo "boot (frontend) OK\n";
} catch ( \Throwable $e ) {
	echo "FATAL in boot: " . get_class( $e ) . ": " . $e->getMessage() . "\n";
	echo $e->getTraceAsString() . "\n";
	exit( 1 );
}

exit( 0 );
