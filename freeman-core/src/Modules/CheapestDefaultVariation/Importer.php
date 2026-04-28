<?php
/**
 * Cheapest Default Variation — legacy importer.
 *
 * The original was a single-file mu-plugin / standalone snippet with no
 * options. We detect either (a) a standalone plugin folder named
 * `auto-default-cheapest-variation` or (b) the sentinel function the legacy
 * snippet defined, and report it. Nothing to migrate.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\CheapestDefaultVariation;

use Freeman\Core\Core\Base_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
final class Importer extends Base_Importer {

	const LEGACY_PLUGIN_FILE = 'auto-default-cheapest-variation/auto-default-cheapest-variation.php';

	/**
	 * Treat the sentinel function the snippet defined as an "installed"
	 * marker even when the plugin folder doesn't exist.
	 */
	protected function detect_extra_installed() {
		return function_exists( 'cdw_default_cheapest_variation' );
	}

	/**
	 * Same for active — the snippet runs as soon as the sentinel is defined.
	 */
	protected function detect_extra_active() {
		return function_exists( 'cdw_default_cheapest_variation' );
	}

	/**
	 * Import (no-op: nothing to migrate).
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function import() {
		return array(
			'ok'      => true,
			'message' => __( 'Cheapest-variation snippet adopted; nothing to migrate.', 'freeman-core' ),
		);
	}
}
