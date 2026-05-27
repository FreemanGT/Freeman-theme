<?php
/**
 * Base class every module extends.
 *
 * Provides safe defaults + option/setting helpers so modules stay small.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract module base.
 */
abstract class Module_Base implements Module_Interface {

	/**
	 * Prefix for all option keys. Always results in
	 * freeman_core.<module_id>.<setting>.
	 *
	 * @var string
	 */
	protected $option_prefix = 'freeman_core';

	/**
	 * Human label. Must be set by child.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Description. Must be set by child.
	 *
	 * @return string
	 */
	abstract public function description();

	/**
	 * Module unique id.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Boot hook.
	 */
	abstract public function boot();

	/**
	 * Default: no external dependencies.
	 *
	 * @return array
	 */
	public function dependencies() {
		return array( 'woocommerce' => true );
	}

	/**
	 * Whether the module is enabled in settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$modules = get_option( 'freeman_core_modules', array() );
		if ( ! is_array( $modules ) ) {
			return false;
		}
		return isset( $modules[ $this->id() ] ) ? (bool) $modules[ $this->id() ] : false;
	}

	/**
	 * Dependencies satisfied?
	 *
	 * @return bool
	 */
	public function dependencies_met() {
		$deps = $this->dependencies();

		if ( ! empty( $deps['woocommerce'] ) && ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		if ( ! empty( $deps['elementor'] ) && ! did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
			return false;
		}
		if ( ! empty( $deps['php'] ) && version_compare( PHP_VERSION, $deps['php'], '<' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Activation no-op. Override as needed.
	 */
	public function on_activate() {}

	/**
	 * Deactivation no-op. Override as needed.
	 */
	public function on_deactivate() {}

	/**
	 * Register hooks that must stay active even when the module is disabled —
	 * platform contracts (e.g. WP privacy export/erase) that must keep covering
	 * already-persisted data. No-op by default. Called for every discovered
	 * module from Plugin::boot(), regardless of is_enabled().
	 */
	public function register_persistent_hooks() {}

	/**
	 * Uninstall default: delete all of this module's options.
	 */
	public function on_uninstall() {
		global $wpdb;
		$like = $wpdb->esc_like( $this->option_name( '' ) ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);
	}

	/**
	 * Empty settings schema by default.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array();
	}

	/**
	 * Modules whose settings still live in a legacy admin menu (because they
	 * predate Settings_Hub and we preserve the menu for familiarity) can
	 * override this to return the admin URL the Dashboard "Settings" button
	 * should point to. Empty string = no external settings surface.
	 *
	 * @return string
	 */
	public function legacy_settings_url() {
		return '';
	}

	/**
	 * Default health is green if enabled.
	 *
	 * @return array{level:string,message:string}
	 */
	public function health() {
		if ( ! $this->is_enabled() ) {
			return array( 'level' => 'amber', 'message' => __( 'Disabled', 'freeman-core' ) );
		}
		if ( ! $this->dependencies_met() ) {
			return array( 'level' => 'red', 'message' => __( 'Missing dependency', 'freeman-core' ) );
		}
		return array( 'level' => 'green', 'message' => __( 'Active', 'freeman-core' ) );
	}

	/* -----------------------------------------------------------------
	 * Option helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Build the canonical option name for a setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public function option_name( $key ) {
		return $this->option_prefix . '_' . $this->id() . '_' . $key;
	}

	/**
	 * Get a module setting with fallback to schema default.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if nothing is in the DB and no schema default.
	 * @return mixed
	 */
	public function get_option( $key, $default = null ) {
		$value = get_option( $this->option_name( $key ), null );
		if ( null !== $value ) {
			return $value;
		}
		$schema = $this->settings_schema();
		if ( isset( $schema[ $key ]['default'] ) ) {
			return $schema[ $key ]['default'];
		}
		return $default;
	}

	/**
	 * Update a module setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value New value.
	 * @return bool
	 */
	public function update_option( $key, $value ) {
		return update_option( $this->option_name( $key ), $value );
	}

	/**
	 * Delete a module setting.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public function delete_option( $key ) {
		return delete_option( $this->option_name( $key ) );
	}

	/**
	 * Convenience getter for module URL.
	 *
	 * @param string $path Optional path suffix.
	 * @return string
	 */
	public function asset_url( $path = '' ) {
		return FREEMAN_CORE_URL . 'src/Modules/' . $this->module_folder() . '/assets/' . ltrim( $path, '/' );
	}

	/**
	 * Pick the .min.{ext} variant of an asset when SCRIPT_DEBUG is off and
	 * the minified sibling actually exists on disk. Falls back to the plain
	 * file otherwise — so a module still works before the build step has
	 * ever been run (dev installs, `git clone` + activate).
	 *
	 * @param string $path Relative path under assets/ (e.g. 'js/foo.js').
	 * @return string Absolute asset URL.
	 */
	public function asset_min_url( $path ) {
		$folder  = $this->module_folder();
		$fs_base = FREEMAN_CORE_PATH . 'src/Modules/' . $folder . '/assets/';
		$url     = $this->asset_url( $path );
		return self::pick_min_url( $fs_base, $this->asset_url( '' ), $path, $url );
	}

	/**
	 * Pick the minified variant of an asset when SCRIPT_DEBUG is off and
	 * the minified sibling exists on disk. Usable from legacy code that
	 * doesn't have a Module_Base instance — pass the filesystem base
	 * directory and URL base explicitly.
	 *
	 * @param string $fs_base       Filesystem base (must end with /).
	 * @param string $url_base      URL base (must end with /).
	 * @param string $path          Relative asset path (e.g. 'js/foo.js').
	 * @param string $fallback_url  URL to use when no .min sibling exists
	 *                              (usually url_base . path).
	 * @return string
	 */
	public static function pick_min_url( $fs_base, $url_base, $path, $fallback_url = '' ) {
		if ( '' === $fallback_url ) {
			$fallback_url = $url_base . ltrim( $path, '/' );
		}
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $fallback_url;
		}
		$ext = pathinfo( $path, PATHINFO_EXTENSION );
		if ( 'js' !== $ext && 'css' !== $ext ) {
			return $fallback_url;
		}
		$min_path = preg_replace( '/\.(js|css)$/', '.min.$1', $path );
		$fs_path  = rtrim( $fs_base, '/' ) . '/' . ltrim( $min_path, '/' );
		if ( is_readable( $fs_path ) ) {
			return rtrim( $url_base, '/' ) . '/' . ltrim( $min_path, '/' );
		}
		return $fallback_url;
	}

	/**
	 * Infers this module's folder name from its class name.
	 *
	 * @return string
	 */
	protected function module_folder() {
		$reflection = new \ReflectionClass( $this );
		$parts      = explode( '\\', $reflection->getName() );
		// ..\Modules\<Folder>\Module
		$count = count( $parts );
		if ( $count >= 2 ) {
			return $parts[ $count - 2 ];
		}
		return '';
	}

	/**
	 * Templates live inside the module folder; this resolves the full path.
	 *
	 * @param string $template Template name (e.g. 'form.php').
	 * @return string
	 */
	protected function template_path( $template ) {
		return FREEMAN_CORE_PATH . 'src/Modules/' . $this->module_folder() . '/templates/' . ltrim( $template, '/' );
	}

	/**
	 * Load a module template, allowing theme overrides at
	 * freeman/<module_id>/<template>.
	 *
	 * @param string $template Template file.
	 * @param array  $vars     Variables to extract into scope.
	 */
	protected function load_template( $template, array $vars = array() ) {
		$override = locate_template( 'freeman/' . $this->id() . '/' . $template );
		$file     = $override ? $override : $this->template_path( $template );
		if ( ! is_readable( $file ) ) {
			return;
		}
		if ( ! empty( $vars ) ) {
			extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		}
		include $file;
	}
}
