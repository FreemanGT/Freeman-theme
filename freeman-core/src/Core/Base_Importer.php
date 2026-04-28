<?php
/**
 * Shared base for every legacy Importer. Handles detection via the standard
 * `get_plugins()` + `is_plugin_active()` pattern; subclasses only need to
 * define `LEGACY_PLUGIN_FILE` and implement `import()`.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Base importer.
 */
abstract class Base_Importer {

	/**
	 * Plugin basename, e.g. `foo/foo.php`. Subclasses MUST declare this.
	 */
	const LEGACY_PLUGIN_FILE = '';

	/**
	 * Detect whether the legacy plugin is installed / active.
	 *
	 * Returns a typed Detection_Result — it also behaves like the legacy
	 * array shape via ArrayAccess, so older call-sites that read
	 * `$result['installed']` still work.
	 *
	 * @return Detection_Result
	 */
	public function detect() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all  = get_plugins();
		$file = (string) static::LEGACY_PLUGIN_FILE;

		return new Detection_Result(
			isset( $all[ $file ] ) || $this->detect_extra_installed(),
			is_plugin_active( $file ) || $this->detect_extra_active(),
			$file
		);
	}

	/**
	 * Extra "installed" probe for legacy code that isn't always shipped as a
	 * plugin folder (e.g. the cheapest-variation snippet that may be pasted
	 * into functions.php as a sentinel function).
	 *
	 * @return bool
	 */
	protected function detect_extra_installed() {
		return false;
	}

	/**
	 * Extra "active" probe. Mirrors detect_extra_installed().
	 *
	 * @return bool
	 */
	protected function detect_extra_active() {
		return false;
	}

	/**
	 * Migrate legacy options / tables / cron into the module.
	 *
	 * @return array{ok:bool,message:string}
	 */
	abstract public function import();

	/**
	 * Scrub legacy options. Default: no-op (many modules deliberately keep
	 * the legacy keys so the module reads them in place).
	 *
	 * @return void
	 */
	public function delete_legacy_options() {
		// Intentionally empty.
	}
}
