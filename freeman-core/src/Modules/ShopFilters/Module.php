<?php
/**
 * Shop Filters module.
 *
 * Faceted AJAX product filters for shop / category pages, built on a background
 * index. Phase 6.1 (this version) ships only the foundation: the index table,
 * the background indexer, and an admin "Reindex now" tool. Nothing renders on
 * the storefront yet — the shortcode, facet engine and UI arrive in later
 * phases. The module is disabled by default and, when enabled, does nothing
 * until the indexer feature flag is turned on.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

use Freeman\Core\Core\Module_Base;
use Freeman\Core\Core\Feature_Flags;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'shop_filters';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Shop Filters', 'freeman-core' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Faceted, context-aware product filters for shop and category pages, backed by a lightweight background index. Foundation only in this version (index + indexer); the storefront UI ships in later phases.', 'freeman-core' );
	}

	/**
	 * Boot. Registers the wp-cron fallback recurrence unconditionally, then —
	 * only when the indexer flag is on — wires the background indexer and the
	 * admin reindex tool. Flag off = nothing attaches (additive / reversible).
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) );

		if ( ! Feature_Flags::is_enabled( 'shop_filters', 'indexer' ) ) {
			return;
		}

		$indexer = new Indexer();
		$indexer->register_hooks();
		$indexer->ensure_scheduled();

		if ( is_admin() ) {
			( new Admin_Page( $indexer ) )->boot();
		}
	}

	/**
	 * Register the 5-minute recurrence the wp-cron fallback path uses. (When
	 * Action Scheduler is available the indexer uses that instead and this is
	 * unused but harmless.)
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public function register_cron_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		if ( ! isset( $schedules[ Indexer::CRON_SCHEDULE ] ) ) {
			$schedules[ Indexer::CRON_SCHEDULE ] = array(
				'interval' => Indexer::SWEEP_INTERVAL,
				'display'  => __( 'Every 5 minutes (Freeman Shop Filters)', 'freeman-core' ),
			);
		}
		return $schedules;
	}

	/**
	 * On deactivation — clear the indexer's scheduled events and queue.
	 */
	public function on_deactivate() {
		( new Indexer() )->unschedule();
	}

	/**
	 * On uninstall — delete the module's options, clear scheduling, and drop the
	 * index table (the data is a pure derived cache with no user value).
	 */
	public function on_uninstall() {
		parent::on_uninstall();
		( new Indexer() )->unschedule();
		Database::drop();
	}
}
