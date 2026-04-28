<?php
/**
 * Freeman Theme plugin-dependency bootstrap (TGMPA-lite).
 *
 * The theme requires `freeman-core`. Rather than taking an external dependency
 * on TGMPA we ship a self-contained bootstrap that:
 *   1. Detects whether Freeman Core is installed and active.
 *   2. If not installed, shows a dismissible admin notice with an explanation.
 *   3. If installed but not active, shows a one-click activation link.
 *   4. If active but outdated, shows an upgrade notice.
 *
 * Note: we do NOT bundle the Core plugin zip inside the theme. Core is
 * distributed as its own artefact and installed via Plugins → Add New → Upload.
 *
 * @package FreemanTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dependency manager.
 */
final class Freeman_Theme_Plugin_Dependencies {

	const CORE_SLUG     = 'freeman-core/freeman-core.php';
	const CORE_NAME     = 'Freeman Core';
	const NONCE_ACTION  = 'freeman_theme_activate_core';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
		add_action( 'admin_post_freeman_theme_activate_core', array( $this, 'handle_activate' ) );
	}

	/**
	 * Whether the Core plugin is active.
	 *
	 * @return bool
	 */
	public static function is_core_active() {
		return defined( 'FREEMAN_CORE_VERSION' );
	}

	/**
	 * Whether Core is merely installed (but maybe not active).
	 *
	 * @return bool
	 */
	public static function is_core_installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return isset( $plugins[ self::CORE_SLUG ] );
	}

	/**
	 * Version comparison helper.
	 *
	 * @return bool True if Core meets FREEMAN_CORE_MIN_VERSION.
	 */
	public static function is_core_version_ok() {
		if ( ! self::is_core_active() ) {
			return false;
		}
		return version_compare( FREEMAN_CORE_VERSION, FREEMAN_CORE_MIN_VERSION, '>=' );
	}

	/**
	 * Render admin notice if something is off.
	 */
	public function maybe_render_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( self::is_core_active() && self::is_core_version_ok() ) {
			return;
		}

		$message = '';
		$action  = '';

		if ( ! self::is_core_installed() ) {
			$message = sprintf(
				/* translators: %s: plugin name. */
				esc_html__( 'Freeman Theme requires the %s plugin. Please install and activate it.', 'freeman-theme' ),
				'<strong>' . esc_html( self::CORE_NAME ) . '</strong>'
			);
			$action = sprintf(
				'<a href="%s" class="button button-primary">%s</a>',
				esc_url( admin_url( 'plugin-install.php?s=freeman-core&tab=search' ) ),
				esc_html__( 'Install Freeman Core', 'freeman-theme' )
			);
		} elseif ( ! self::is_core_active() ) {
			$message = sprintf(
				/* translators: %s: plugin name. */
				esc_html__( 'Freeman Theme needs the %s plugin to be active.', 'freeman-theme' ),
				'<strong>' . esc_html( self::CORE_NAME ) . '</strong>'
			);
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=freeman_theme_activate_core' ),
				self::NONCE_ACTION
			);
			$action = sprintf(
				'<a href="%s" class="button button-primary">%s</a>',
				esc_url( $url ),
				esc_html__( 'Activate Freeman Core', 'freeman-theme' )
			);
		} else {
			$message = sprintf(
				/* translators: 1: required version, 2: installed version. */
				esc_html__( 'Freeman Theme requires Freeman Core %1$s or later. You have %2$s.', 'freeman-theme' ),
				esc_html( FREEMAN_CORE_MIN_VERSION ),
				esc_html( FREEMAN_CORE_VERSION )
			);
			$action = sprintf(
				'<a href="%s" class="button">%s</a>',
				esc_url( admin_url( 'plugins.php' ) ),
				esc_html__( 'Update plugin', 'freeman-theme' )
			);
		}

		printf(
			'<div class="notice notice-error"><p>%s</p><p>%s</p></div>',
			wp_kses_post( $message ),
			wp_kses( $action, array( 'a' => array( 'href' => array(), 'class' => array() ) ) )
		);
	}

	/**
	 * Handle the "Activate Freeman Core" button.
	 */
	public function handle_activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'freeman-theme' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		$result = activate_plugin( self::CORE_SLUG );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=freeman' ) );
		exit;
	}
}
