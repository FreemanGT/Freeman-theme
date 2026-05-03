<?php
/**
 * PHPUnit bootstrap for freeman-core.
 *
 * Stubs enough of WordPress that every class under `Freeman\Core\` can
 * instantiate + run its public methods in isolation. Sits alongside
 * tools/smoke.php (which does a simpler module instantiation check);
 * this file adds the missing pieces for real unit assertions.
 */

declare(strict_types=1);

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
	define( 'FREEMAN_CORE_VERSION', '1.5.0' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// ---------------------------------------------------------------------------
// Smart stubs that must pass data through to the caller (identity-ish).
// These get defined BEFORE the blanket null-return loop so they win.
// ---------------------------------------------------------------------------

// Minimal hook registry. Backs apply_filters / do_action / add_filter / add_action
// so tests can assert filter mutations and action firings. With no listeners
// registered, apply_filters returns the input value unchanged and do_action is a
// no-op — preserving the behavior of the previous identity/null stubs.
$GLOBALS['fr_hooks'] = $GLOBALS['fr_hooks'] ?? array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $cb, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['fr_hooks'][ $tag ][] = array( 'cb' => $cb, 'args' => (int) $accepted_args );
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		$args = func_get_args();
		array_shift( $args ); // drop $tag
		if ( empty( $GLOBALS['fr_hooks'][ $tag ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['fr_hooks'][ $tag ] as $h ) {
			$slice    = array_slice( $args, 0, $h['args'] );
			$slice[0] = $value;
			$value    = call_user_func_array( $h['cb'], $slice );
			$args[0]  = $value;
		}
		return $value;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $cb, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $tag, $cb, $priority, $accepted_args );
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag ) {
		$args = func_get_args();
		array_shift( $args );
		if ( empty( $GLOBALS['fr_hooks'][ $tag ] ) ) {
			return;
		}
		foreach ( $GLOBALS['fr_hooks'][ $tag ] as $h ) {
			call_user_func_array( $h['cb'], array_slice( $args, 0, $h['args'] ) );
		}
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $v ) { return is_scalar( $v ) ? (string) $v : ''; }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $v ) { return is_scalar( $v ) ? (string) $v : ''; }
}
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $v ) { return filter_var( (string) $v, FILTER_VALIDATE_EMAIL ) ?: ''; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $v ) { return (string) $v; }
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $v ) { return (string) $v; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $v ) { return (string) $v; }
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $v ) { return (string) $v; }
}
if ( ! function_exists( '__' ) ) {
	function __( $v ) { return (string) $v; }
}
if ( ! function_exists( '_e' ) ) {
	function _e( $v ) { echo (string) $v; }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.test' . (string) $path; }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $f ) { return (string) $f; }
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $v ) { return (string) $v; }
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array(
			'basedir' => sys_get_temp_dir() . '/fr-uploads',
			'baseurl' => 'https://example.test/wp-content/uploads',
			'path'    => sys_get_temp_dir() . '/fr-uploads',
			'url'     => 'https://example.test/wp-content/uploads',
			'subdir'  => '',
			'error'   => false,
		);
	}
}

// Option + transient store (so Security::rate_limit, module options etc. are testable).
$GLOBALS['fr_opts']        = $GLOBALS['fr_opts']        ?? array();
$GLOBALS['fr_transients']  = $GLOBALS['fr_transients']  ?? array();
$GLOBALS['fr_term_meta']   = $GLOBALS['fr_term_meta']   ?? array();
$GLOBALS['fr_terms']       = $GLOBALS['fr_terms']       ?? array();
$GLOBALS['fr_attachments'] = $GLOBALS['fr_attachments'] ?? array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $k, $d = false ) { return $GLOBALS['fr_opts'][ $k ] ?? $d; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $k, $v, $autoload = null ) {
		if ( ! empty( $GLOBALS['fr_update_option_fail_keys'] ) && in_array( $k, $GLOBALS['fr_update_option_fail_keys'], true ) ) {
			return false;
		}
		$GLOBALS['fr_opts'][ $k ] = $v;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $k ) { unset( $GLOBALS['fr_opts'][ $k ] ); return true; }
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $k, $v ) { if ( ! isset( $GLOBALS['fr_opts'][ $k ] ) ) { $GLOBALS['fr_opts'][ $k ] = $v; return true; } return false; }
}

// Term-meta store + smart get_term_meta / update_term_meta / delete_term_meta
// (Wave 2.2 / 4b — promoted from inline test stubs after the dumb stub races
// in CategorySliderHooksTest etc. broke 4b's image-helper tests). The dumb
// stubs in those test files are guarded by `function_exists` and become
// no-ops once these load.
if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $key, $single = true ) {
		$row = $GLOBALS['fr_term_meta'][ $term_id ][ $key ] ?? '';
		return $single ? $row : ( '' === $row ? array() : array( $row ) );
	}
}
if ( ! function_exists( 'update_term_meta' ) ) {
	function update_term_meta( $term_id, $key, $value ) {
		$GLOBALS['fr_term_meta'][ $term_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_term_meta' ) ) {
	function delete_term_meta( $term_id, $key ) {
		unset( $GLOBALS['fr_term_meta'][ $term_id ][ $key ] );
		return true;
	}
}

// get_terms — backward-compat with tests that set $GLOBALS['fr_get_terms_return']
// (CategorySliderHooksTest etc.); falls back to the 4b path that honors a
// taxonomy + meta_query EXISTS filter when fr_get_terms_return is unset.
if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		if ( isset( $GLOBALS['fr_get_terms_return'] ) ) {
			return $GLOBALS['fr_get_terms_return'];
		}
		$tax = is_array( $args ) ? ( $args['taxonomy'] ?? '' ) : '';
		$want_meta = '';
		if ( is_array( $args ) && isset( $args['meta_query'][0]['key'] ) ) {
			$want_meta = (string) $args['meta_query'][0]['key'];
		}
		$ids = $GLOBALS['fr_terms'][ $tax ] ?? array();
		if ( empty( $ids ) ) {
			return array();
		}
		if ( '' === $want_meta ) {
			return $ids;
		}
		$matches = array();
		foreach ( $ids as $tid ) {
			if ( ! empty( $GLOBALS['fr_term_meta'][ $tid ][ $want_meta ] ) ) {
				$matches[] = $tid;
			}
		}
		return $matches;
	}
}

// wp_get_attachment_image_src — fixture-driven; returns false when no URL
// is registered for the (id, size) pair. Compatible with the dumb-stub
// CategorySliderHooksTest version (always-false) when fr_attachments is unset.
if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
	function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail' ) {
		$url = $GLOBALS['fr_attachments'][ $attachment_id ][ $size ] ?? '';
		if ( '' === $url ) {
			return false;
		}
		return array( $url, 100, 100, false );
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $k ) { $row = $GLOBALS['fr_transients'][ $k ] ?? null; if ( ! $row ) return false; if ( $row['exp'] > 0 && $row['exp'] < time() ) { unset( $GLOBALS['fr_transients'][ $k ] ); return false; } return $row['v']; }
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $k, $v, $t = 0 ) { $GLOBALS['fr_transients'][ $k ] = array( 'v' => $v, 'exp' => $t ? time() + $t : 0 ); return true; }
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $k ) { unset( $GLOBALS['fr_transients'][ $k ] ); return true; }
}
if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins() { return array(); }
}
if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( $x ) { return false; }
}

// Smart get_query_var: reads from $GLOBALS['fr_query_vars'] so tests can drive
// rewrite-dependent code paths (e.g. ProductFeed Server::serve_feed). Defaults
// to '' which matches WP behavior for missing query vars.
if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( $key, $default = '' ) {
		return $GLOBALS['fr_query_vars'][ $key ] ?? $default;
	}
}

// Smart get_locale: reads from $GLOBALS['fr_locale'] so tests can drive locale-
// dependent code (e.g. RestockNotify::seed_locale_defaults). Defaults to en_US.
if ( ! function_exists( 'get_locale' ) ) {
	function get_locale() {
		return $GLOBALS['fr_locale'] ?? 'en_US';
	}
}

// Smart wp_localize_script: captures the localize payload into
// $GLOBALS['fr_localize_calls'] keyed by $object_name so tests can assert
// the JS-side data (handle, object name, payload). Doesn't render anything.
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $l10n ) {
		$GLOBALS['fr_localize_calls'][] = array(
			'handle'      => $handle,
			'object_name' => $object_name,
			'l10n'        => $l10n,
		);
		return true;
	}
}

// Page-type predicates — read from $GLOBALS['fr_page_type'] so tests can
// drive frontend code paths (e.g. Frontend::should_enqueue_here heuristic).
// Each defaults false. Set $GLOBALS['fr_page_type'] = 'product' (etc.) once
// per test; the predicates below match against it.
foreach (
	array(
		'is_product'          => 'product',
		'is_shop'             => 'shop',
		'is_product_taxonomy' => 'product_taxonomy',
		'is_cart'             => 'cart',
		'is_checkout'         => 'checkout',
		'is_account_page'     => 'account',
	) as $fn => $kind
) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return ( \$GLOBALS['fr_page_type'] ?? '' ) === '{$kind}'; }" );
	}
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_types = '' ) {
		$current = $GLOBALS['fr_singular_type'] ?? '';
		if ( '' === $current ) {
			return false;
		}
		if ( '' === $post_types || array() === $post_types ) {
			return true;
		}
		$wanted = (array) $post_types;
		return in_array( $current, $wanted, true );
	}
}
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['fr_doing_ajax'] );
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $cb ) {
		$GLOBALS['fr_shortcodes'][ $tag ] = $cb;
		return true;
	}
}
if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( $defaults, $atts, $shortcode = '' ) {
		$atts = is_array( $atts ) ? $atts : array();
		return array_merge( (array) $defaults, $atts );
	}
}
if ( ! function_exists( 'has_shortcode' ) ) {
	function has_shortcode( $content, $tag ) {
		return false !== strpos( (string) $content, '[' . $tag );
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) {
		return $GLOBALS['fr_get_post_return'] ?? null;
	}
}
if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post = null ) {
		if ( is_object( $post ) && isset( $post->post_type ) ) {
			return $post->post_type;
		}
		return $GLOBALS['fr_get_post_type_return'] ?? '';
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		return $GLOBALS['fr_post_meta'][ $id ][ $key ] ?? '';
	}
}
if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return $GLOBALS['fr_queried_object_id'] ?? 0;
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return $GLOBALS['fr_the_id'] ?? 0;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action ) {
		return 'nonce-' . md5( (string) $action );
	}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['fr_safe_redirect_calls'][] = array( 'location' => $location, 'status' => $status );
		return true;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}

// Smart wp_mail: captures the most recent send into $GLOBALS['fr_wp_mail_calls']
// instead of dispatching real mail. Tests can inspect the captured payload to
// assert email contents (subject, body, recipient, headers).
if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
		$GLOBALS['fr_wp_mail_calls'][] = array(
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
		);
		return $GLOBALS['fr_wp_mail_return'] ?? true;
	}
}

// Lightweight implementations of common WP helpers the new RestockNotify
// Email class touches. Each is guarded so tests that ship their own richer
// stub (e.g. snapshots) win.
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		return array_merge( (array) $defaults, $args );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = '', $url = '' ) {
		if ( is_array( $key ) ) {
			$args = $key;
			$url  = '' === $value ? ( $GLOBALS['fr_home_url'] ?? 'https://example.test' ) : $value;
		} else {
			$args = array( $key => $value );
		}
		$qs = http_build_query( $args );
		$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		return $url . $sep . $qs;
	}
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( $v ) {
		return (bool) filter_var( (string) $v, FILTER_VALIDATE_EMAIL );
	}
}
if ( ! function_exists( 'wc_get_page_permalink' ) ) {
	function wc_get_page_permalink( $page = 'shop' ) {
		return ( $GLOBALS['fr_home_url'] ?? 'https://example.test' ) . '/' . (string) $page;
	}
}
if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $id ) {
		return $GLOBALS['fr_attachment_url'] ?? '';
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $key = '' ) {
		if ( 'name' === $key ) {
			return $GLOBALS['fr_blogname'] ?? 'Test Site';
		}
		return $GLOBALS['fr_home_url'] ?? 'https://example.test';
	}
}

// ---------------------------------------------------------------------------
// Blanket null-return stubs for every other WP function used in the codebase.
// Only runs for functions NOT already defined above.
// ---------------------------------------------------------------------------

$stubs = array(
	'remove_action', 'remove_filter',
	'register_activation_hook', 'register_deactivation_hook', 'register_uninstall_hook',
	'plugin_dir_path', 'plugin_dir_url',
	'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_style', 'wp_register_script',
	'wp_localize_script', 'wp_add_inline_script',
	'wp_schedule_event', 'wp_next_scheduled', 'wp_clear_scheduled_hook', 'wp_schedule_single_event',
	'wp_upload_dir', 'wp_mkdir_p',
	'load_plugin_textdomain', 'load_child_theme_textdomain',
	'is_admin', 'is_rtl', 'current_user_can',
	'wp_verify_nonce', 'check_admin_referer', 'check_ajax_referer', 'wp_create_nonce', 'wp_nonce_field',
	'esc_url_raw',
	'deactivate_plugins', 'delete_plugins',
	'register_rest_route', 'rest_ensure_response',
	'wp_safe_redirect', 'wp_send_json_success', 'wp_send_json_error',
	'add_rewrite_rule', 'flush_rewrite_rules',
	'current_time', 'date_i18n', 'wp_date',
	'site_url', 'rest_url',
);
foreach ( $stubs as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return null; }" );
	}
}

// Minimal $wpdb stub: returns option keys matching the freeman_core_/freeman_digital_
// prefixes from the in-memory option store. Settings_Tools::freeman_option_keys()
// is its only consumer in the test suite.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new class {
		public $options = 'wp_options';
		public function get_col( $sql ) {
			$keys = array_keys( $GLOBALS['fr_opts'] ?? array() );
			return array_values( array_filter( $keys, static function ( $k ) {
				return strpos( $k, 'freeman_core_' ) === 0 || strpos( $k, 'freeman_digital_' ) === 0;
			} ) );
		}
	};
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $v, $opts = 0 ) { return json_encode( $v, $opts ); }
}
if ( ! function_exists( '__return_true' ) ) {
	function __return_true() { return true; }
}
if ( ! function_exists( '__return_false' ) ) {
	function __return_false() { return false; }
}
if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers() {}
}

// PSR-4 autoloader mirroring the plugin's own.
spl_autoload_register(
	static function ( $class ) {
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
