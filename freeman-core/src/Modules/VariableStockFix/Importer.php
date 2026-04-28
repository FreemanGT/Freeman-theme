<?php
/**
 * Legacy importer for woo-variable-stock-fix.
 *
 * The legacy plugin stored no persistent options — only a cron hook
 * (`vpsf_daily_audit`) which we clear on import so it doesn't double-run.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariableStockFix;

use Freeman\Core\Core\Base_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
final class Importer extends Base_Importer {

	const LEGACY_PLUGIN_FILE = 'woo-variable-stock-fix/woo-variable-stock-fix.php';
	const LEGACY_CRON_HOOK   = 'vpsf_daily_audit';

	/**
	 * Import — clear the legacy cron.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function import() {
		wp_clear_scheduled_hook( self::LEGACY_CRON_HOOK );
		return array(
			'ok'      => true,
			'message' => __( 'Variable Stock Fix migrated — legacy daily audit cron cleared.', 'freeman-core' ),
		);
	}

	/**
	 * Delete legacy options (none). We re-clear the cron defensively.
	 */
	public function delete_legacy_options() {
		wp_clear_scheduled_hook( self::LEGACY_CRON_HOOK );
	}
}
