<?php
/**
 * Orchestrates schema/option migrations across Core + every module that owns
 * a custom table. Modules register schemas by implementing a get_schema()
 * method on a sibling Database class; Migrations looks those up by convention.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Migrations runner.
 */
final class Migrations {

	const DB_VERSION_OPTION = 'freeman_core_db_version';

	/**
	 * Registry reference.
	 *
	 * @var Module_Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Module_Registry $registry Registry.
	 */
	public function __construct( Module_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Run migrations if the stored DB version is behind the plugin version.
	 */
	public function maybe_run() {
		$installed = get_option( self::DB_VERSION_OPTION, '0' );
		if ( version_compare( $installed, FREEMAN_CORE_VERSION, '<' ) ) {
			$this->run( $installed );
		}
	}

	/**
	 * Run migrations unconditionally. Safe to call repeatedly (dbDelta is
	 * idempotent; one-shot migrations are version-gated).
	 *
	 * @param string|null $previous_version Stored DB version before this run.
	 *                                      Defaults to the current option value.
	 */
	public function run( $previous_version = null ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( null === $previous_version ) {
			$previous_version = get_option( self::DB_VERSION_OPTION, '0' );
		}

		foreach ( $this->registry->discover() as $module ) {
			$id            = $module->id();
			$reflection    = new \ReflectionClass( $module );
			$module_ns     = $reflection->getNamespaceName();
			$database_cls  = $module_ns . '\\Database';

			if ( class_exists( $database_cls ) && is_callable( array( $database_cls, 'install' ) ) ) {
				try {
					call_user_func( array( $database_cls, 'install' ) );
				} catch ( \Throwable $e ) {
					Logger::log( 'Migrations: ' . $id . ' install failed: ' . $e->getMessage(), 'error' );
				}
			}
		}

		$this->run_one_shot_migrations( $previous_version );

		update_option( self::DB_VERSION_OPTION, FREEMAN_CORE_VERSION );
	}

	/**
	 * Version-gated one-shot migrations. Each block runs at most once per
	 * install (because the DB_VERSION_OPTION is bumped at the end of run()).
	 *
	 * @param string $previous_version Stored DB version before this run.
	 */
	private function run_one_shot_migrations( $previous_version ) {
		// 1.9.0 — hook/option rename for ProductFeed (N-02) and
		// VariableStockFix (N-03). See AUDIT-2026-04.md.
		if ( version_compare( $previous_version, '1.9.0', '<' ) ) {
			$this->migrate_to_1_9_0();
		}
	}

	/**
	 * 1.9.0 rename migration. Idempotent: safe if run twice.
	 */
	private function migrate_to_1_9_0() {
		// (a) Copy the VariableStockFix debounce queue to its canonical key,
		//     then drop the legacy key.
		$old_queue = get_option( 'freeman_vpsf_debounce_queue', null );
		if ( null !== $old_queue ) {
			if ( false === get_option( 'freeman_core_variable_stock_fix_debounce_queue', false ) ) {
				update_option( 'freeman_core_variable_stock_fix_debounce_queue', $old_queue, false );
			}
			delete_option( 'freeman_vpsf_debounce_queue' );
		}

		// (b) Reschedule the ProductFeed hourly cron under its canonical hook
		//     name. The legacy hook name is also handled at runtime by an
		//     add_action() shim (Module::boot) so any in-flight events still
		//     fire — but new schedules need to use the new name.
		if ( $ts = wp_next_scheduled( 'freeman_productfeed_hourly' ) ) {
			wp_unschedule_event( $ts, 'freeman_productfeed_hourly' );
		}
		if ( ! wp_next_scheduled( 'freeman_core_product_feed_hourly' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'freeman_core_product_feed_hourly' );
		}

		// (c) Same for the VariableStockFix daily audit cron.
		if ( $ts = wp_next_scheduled( 'freeman_vpsf_daily_audit' ) ) {
			wp_unschedule_event( $ts, 'freeman_vpsf_daily_audit' );
		}
		if ( ! wp_next_scheduled( 'freeman_core_variable_stock_fix_daily_audit' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'freeman_core_variable_stock_fix_daily_audit' );
		}

		// (d) Flush rewrite rules so the cached rule for /product-feed maps
		//     onto the canonical query var (`freeman_core_product_feed`)
		//     instead of the legacy one. Server.php still registers the
		//     legacy query var as a one-release alias for safety.
		flush_rewrite_rules( false );
	}
}
