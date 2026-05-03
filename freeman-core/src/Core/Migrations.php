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

		// 1.11.21 — Wave 2.2 / sub-PR 4a: copy the 14 etucart_vs_*
		// VariationSwatches settings into their freeman_core_variation_swatches_*
		// counterparts so the new Settings_Hub admin page (gated behind the
		// settings_hub feature flag) has values to render. Never deletes the
		// legacy keys; never overwrites an already-set new key.
		if ( version_compare( $previous_version, '1.11.21', '<' ) ) {
			$this->migrate_variation_swatches_settings_to_hub();
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

	/**
	 * 1.11.21 Wave 2.2 / 4a — copy etucart_vs_* settings into the
	 * freeman_core_variation_swatches_* namespace.
	 *
	 * Idempotent: a new key that already has a value is never overwritten,
	 * so re-running this method is a no-op (and the version-gate in
	 * run_one_shot_migrations() prevents re-runs anyway via DB_VERSION_OPTION).
	 *
	 * Never deletes legacy keys — they remain as the writable surface for
	 * the legacy WC Settings → Products tab during the transition (per the
	 * §4.5 zero-downtime migration decision).
	 */
	private function migrate_variation_swatches_settings_to_hub() {
		$pairs = array(
			'etucart_vs_shop_enabled'             => 'freeman_core_variation_swatches_shop_enabled',
			'etucart_vs_shop_max_visible'         => 'freeman_core_variation_swatches_shop_max_visible',
			'etucart_vs_shop_show_price'          => 'freeman_core_variation_swatches_shop_show_price',
			'etucart_vs_shop_apply_shop'          => 'freeman_core_variation_swatches_shop_apply_shop',
			'etucart_vs_shop_apply_category'      => 'freeman_core_variation_swatches_shop_apply_category',
			'etucart_vs_shop_apply_tag'           => 'freeman_core_variation_swatches_shop_apply_tag',
			'etucart_vs_shop_apply_search'        => 'freeman_core_variation_swatches_shop_apply_search',
			'etucart_vs_shop_apply_related'       => 'freeman_core_variation_swatches_shop_apply_related',
			'etucart_vs_shop_excluded_categories' => 'freeman_core_variation_swatches_shop_excluded_categories',
			'etucart_vs_pdp_hide_oos'             => 'freeman_core_variation_swatches_pdp_hide_oos',
			'etucart_vs_shop_hide_oos'            => 'freeman_core_variation_swatches_shop_hide_oos',
			'etucart_vs_shop_no_preselect'        => 'freeman_core_variation_swatches_shop_no_preselect',
			'etucart_vs_shop_hide_attr_labels'    => 'freeman_core_variation_swatches_shop_hide_attr_labels',
			'etucart_vs_shop_hide_selected'       => 'freeman_core_variation_swatches_shop_hide_selected',
		);

		$sentinel = '__FR_NOT_SET__'; // Non-`freeman_*` so it stays out of baseline-options-declared.txt; in-memory only.
		foreach ( $pairs as $legacy_key => $new_key ) {
			$existing_new = get_option( $new_key, $sentinel );
			if ( $sentinel !== $existing_new ) {
				continue;
			}
			$legacy_val = get_option( $legacy_key, $sentinel );
			if ( $sentinel === $legacy_val ) {
				continue;
			}
			update_option( $new_key, $legacy_val );
		}
	}
}
