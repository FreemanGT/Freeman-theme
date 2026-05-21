<?php
/**
 * Freeman Core plugin bootstrap.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton plugin controller.
 */
final class Plugin {

	const VERSION = '1.12.15';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Module registry.
	 *
	 * @var Module_Registry|null
	 */
	private $registry = null;

	/**
	 * Settings hub (admin menu).
	 *
	 * @var Settings_Hub|null
	 */
	private $hub = null;

	/**
	 * Migrations runner.
	 *
	 * @var Migrations|null
	 */
	private $migrations = null;

	/**
	 * Legacy importer.
	 *
	 * @var Legacy_Importer|null
	 */
	private $importer = null;

	/**
	 * Whether boot() ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Return the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Activation hook.
	 */
	public static function on_activate() {
		$self = self::instance();
		$self->init_services();

		// Seed default enabled modules on first activation.
		if ( false === get_option( 'freeman_core_modules', false ) ) {
			$defaults = array();
			foreach ( $self->registry->discover() as $id => $module ) {
				$defaults[ $id ] = true;
			}
			update_option( 'freeman_core_modules', $defaults );
		}

		// Let modules run their own activation (cron, rewrite rules, tables).
		foreach ( $self->registry->discover() as $module ) {
			$module->on_activate();
		}

		$self->migrations->run();

		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook.
	 */
	public static function on_deactivate() {
		$self = self::instance();
		$self->init_services();

		foreach ( $self->registry->discover() as $module ) {
			$module->on_deactivate();
		}

		flush_rewrite_rules();
	}

	/**
	 * Main boot sequence, invoked on plugins_loaded.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->init_services();
		$this->load_textdomain();

		$this->migrations->maybe_run();

		// Discover + boot enabled modules. Any thrown exception gets logged
		// *and* stashed in a transient so the dashboard can surface it —
		// otherwise a broken module silently disappears.
		$failures = array();
		foreach ( $this->registry->discover() as $module ) {
			if ( $module->is_enabled() && $module->dependencies_met() ) {
				try {
					$module->boot();
				} catch ( \Throwable $e ) {
					Logger::log( 'Module boot failed: ' . $module->id() . ' — ' . $e->getMessage() );
					$failures[ $module->id() ] = array(
						'label'   => $module->label(),
						'message' => $e->getMessage(),
					);
				}
			}
		}
		if ( ! empty( $failures ) ) {
			set_transient( 'freeman_core_boot_failures', $failures, HOUR_IN_SECONDS );
		} else {
			delete_transient( 'freeman_core_boot_failures' );
		}

		// Admin-only components.
		if ( is_admin() ) {
			$this->hub->boot();
			( new \Freeman\Core\Admin\Dashboard( $this ) )->boot();
			$this->importer->boot();
			( new Settings_Tools() )->boot();
		}

		$this->booted = true;

		do_action( 'freeman_core/booted', $this );
	}

	/**
	 * Minimal boot path used by uninstall.php.
	 */
	public function boot_for_uninstall() {
		$this->init_services();
		$this->registry->discover();
	}

	/**
	 * Instantiate the core services exactly once.
	 */
	private function init_services() {
		if ( null === $this->registry ) {
			$this->registry = new Module_Registry();
		}
		if ( null === $this->hub ) {
			$this->hub = new Settings_Hub( $this->registry );
		}
		if ( null === $this->migrations ) {
			$this->migrations = new Migrations( $this->registry );
		}
		if ( null === $this->importer ) {
			$this->importer = new Legacy_Importer( $this->registry );
		}
	}

	/**
	 * Load text-domain.
	 */
	private function load_textdomain() {
		load_plugin_textdomain( 'freeman-core', false, dirname( FREEMAN_CORE_BASENAME ) . '/languages' );
	}

	/**
	 * Module registry accessor.
	 *
	 * @return Module_Registry
	 */
	public function registry() {
		$this->init_services();
		return $this->registry;
	}

	/**
	 * Settings hub accessor.
	 *
	 * @return Settings_Hub
	 */
	public function hub() {
		$this->init_services();
		return $this->hub;
	}

	/**
	 * Migrations accessor.
	 *
	 * @return Migrations
	 */
	public function migrations() {
		$this->init_services();
		return $this->migrations;
	}

	/**
	 * Legacy importer accessor.
	 *
	 * @return Legacy_Importer
	 */
	public function importer() {
		$this->init_services();
		return $this->importer;
	}
}
