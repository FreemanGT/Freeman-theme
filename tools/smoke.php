<?php
/**
 * Offline smoke test: load each Module class through the same PSR-4 paths
 * Freeman Core uses at runtime, verify it implements Module_Interface and
 * exposes the minimum required API surface.
 *
 * Run:
 *     php tools/smoke.php
 */

// Stubs so the module files can parse without WordPress loaded.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/' );
}
if ( ! defined( 'FREEMAN_CORE_FILE' ) ) {
    define( 'FREEMAN_CORE_FILE', __DIR__ . '/../freeman-core/freeman-core.php' );
}
if ( ! defined( 'FREEMAN_CORE_PATH' ) ) {
    define( 'FREEMAN_CORE_PATH', dirname( FREEMAN_CORE_FILE ) . '/' );
}
if ( ! defined( 'FREEMAN_CORE_URL' ) ) {
    define( 'FREEMAN_CORE_URL', 'https://example.test/wp-content/plugins/freeman-core/' );
}
if ( ! defined( 'FREEMAN_CORE_BASENAME' ) ) {
    define( 'FREEMAN_CORE_BASENAME', 'freeman-core/freeman-core.php' );
}
if ( ! defined( 'FREEMAN_CORE_VERSION' ) ) {
    define( 'FREEMAN_CORE_VERSION', '1.0.0' );
}

// Minimal WP function stubs.
$stubs = array(
    'add_action', 'add_filter', 'apply_filters', 'do_action', 'remove_action',
    'register_activation_hook', 'register_deactivation_hook', 'register_uninstall_hook',
    'plugin_dir_path', 'plugin_dir_url', 'plugin_basename',
    'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_style', 'wp_register_script',
    'wp_localize_script', 'wp_add_inline_script',
    'get_option', 'update_option', 'delete_option', 'add_option',
    'wp_schedule_event', 'wp_next_scheduled', 'wp_clear_scheduled_hook',
    'wp_upload_dir',
    'load_plugin_textdomain', 'load_child_theme_textdomain',
    'esc_html__', 'esc_attr__', 'esc_html', 'esc_attr', 'esc_url', 'esc_textarea', 'wp_kses_post',
    '__', '_e', 'esc_html_e', 'esc_attr_e',
    'admin_url', 'home_url', 'site_url', 'rest_url',
    'is_admin', 'is_rtl', 'current_user_can',
    'wp_verify_nonce', 'check_admin_referer', 'check_ajax_referer', 'wp_create_nonce', 'wp_nonce_field',
    'sanitize_text_field', 'sanitize_email', 'sanitize_key', 'esc_url_raw',
    'trailingslashit', 'untrailingslashit',
    'deactivate_plugins', 'delete_plugins',
    'register_rest_route', 'rest_ensure_response',
    'wp_safe_redirect', 'wp_send_json_success', 'wp_send_json_error',
    'get_transient', 'set_transient', 'delete_transient',
    'add_rewrite_rule', 'flush_rewrite_rules', 'get_query_var',
    'current_time', 'date_i18n', 'wp_date',
);
foreach ( $stubs as $fn ) {
    if ( ! function_exists( $fn ) ) {
        eval( "function {$fn}() { return null; }" );
    }
}
if ( ! function_exists( 'get_plugins' ) ) {
    eval( 'function get_plugins() { return array(); }' );
}
if ( ! function_exists( 'is_plugin_active' ) ) {
    eval( 'function is_plugin_active( $x ) { return false; }' );
}

// PSR-4 autoloader mirroring composer.json: Freeman\\Core\\ → src/.
spl_autoload_register(
    function ( $class ) {
        $prefix = 'Freeman\\Core\\';
        if ( strpos( $class, $prefix ) !== 0 ) {
            return;
        }
        $relative = substr( $class, strlen( $prefix ) );
        $path     = FREEMAN_CORE_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }
);

$modules = glob( FREEMAN_CORE_PATH . 'src/Modules/*/Module.php' );
$pass    = 0;
$fail    = 0;
foreach ( $modules as $file ) {
    $dir   = basename( dirname( $file ) );
    $class = 'Freeman\\Core\\Modules\\' . $dir . '\\Module';

    try {
        if ( ! class_exists( $class ) ) {
            echo "FAIL  $dir — class $class not loaded\n";
            $fail++;
            continue;
        }
        $instance = new $class();
        if ( ! $instance instanceof \Freeman\Core\Core\Module_Interface ) {
            echo "FAIL  $dir — $class does not implement Module_Interface\n";
            $fail++;
            continue;
        }
        $required = array( 'id', 'label', 'description', 'settings_schema' );
        foreach ( $required as $method ) {
            if ( ! method_exists( $instance, $method ) ) {
                throw new RuntimeException( "missing method: $method" );
            }
        }
        $id    = $instance->id();
        $label = $instance->label();
        echo "OK    $dir — $class → id='$id' label='$label'\n";
        $pass++;
    } catch ( Throwable $e ) {
        echo "FAIL  $dir — " . $e->getMessage() . "\n";
        $fail++;
    }
}

// Importer sanity: every Importer must extend Base_Importer and detect()
// must return the array{installed,active,file} shape. Catches regressions
// from the Base_Importer refactor.
$importers = glob( FREEMAN_CORE_PATH . 'src/Modules/*/Importer.php' );
$imp_pass  = 0;
$imp_fail  = 0;
foreach ( $importers as $file ) {
    $dir   = basename( dirname( $file ) );
    $class = 'Freeman\\Core\\Modules\\' . $dir . '\\Importer';

    try {
        if ( ! class_exists( $class ) ) {
            echo "FAIL  $dir::Importer — class $class not loaded\n";
            $imp_fail++;
            continue;
        }
        $i = new $class();
        if ( ! $i instanceof \Freeman\Core\Core\Base_Importer ) {
            echo "FAIL  $dir::Importer — does not extend Base_Importer\n";
            $imp_fail++;
            continue;
        }
        $result = $i->detect();
        // Accept both the new typed DTO and the legacy array shape (for
        // any 3rd-party importer that hasn't migrated yet).
        $coerced = \Freeman\Core\Core\Detection_Result::from( $result );
        if ( null === $coerced ) {
            echo "FAIL  $dir::Importer — detect() returned bad shape\n";
            $imp_fail++;
            continue;
        }
        echo "OK    $dir::Importer — detect() → file='" . $coerced->file . "'\n";
        $imp_pass++;
    } catch ( Throwable $e ) {
        echo "FAIL  $dir::Importer — " . $e->getMessage() . "\n";
        $imp_fail++;
    }
}

echo "\n";
echo "Modules:   $pass passed, $fail failed\n";
echo "Importers: $imp_pass passed, $imp_fail failed\n";
exit( ( $fail + $imp_fail ) > 0 ? 1 : 0 );
