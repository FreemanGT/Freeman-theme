<?php
/**
 * The contract every Freeman Core module implements.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Module interface.
 */
interface Module_Interface {

	/**
	 * Short, stable identifier used in option keys and URLs. snake_case.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Human-readable label for the admin UI.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * One-sentence description for the Dashboard card.
	 *
	 * @return string
	 */
	public function description();

	/**
	 * Declared dependencies. Supported keys: woocommerce, elementor, php.
	 *
	 * @return array<string,string|bool>
	 */
	public function dependencies();

	/**
	 * Whether the module is enabled in settings.
	 *
	 * @return bool
	 */
	public function is_enabled();

	/**
	 * Whether the declared dependencies are satisfied.
	 *
	 * @return bool
	 */
	public function dependencies_met();

	/**
	 * Register WP hooks. Only called when is_enabled() AND dependencies_met().
	 */
	public function boot();

	/**
	 * Plugin activation handler for this module.
	 */
	public function on_activate();

	/**
	 * Plugin deactivation handler for this module.
	 */
	public function on_deactivate();

	/**
	 * Uninstall handler for this module.
	 */
	public function on_uninstall();

	/**
	 * Declarative settings schema; Settings_Hub auto-renders it.
	 *
	 * @return array
	 */
	public function settings_schema();

	/**
	 * Health-check status for the Dashboard. Returns one of:
	 *   ['level' => 'green'|'amber'|'red', 'message' => string]
	 *
	 * @return array{level:string,message:string}
	 */
	public function health();
}
