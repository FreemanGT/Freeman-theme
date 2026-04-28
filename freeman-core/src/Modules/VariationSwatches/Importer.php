<?php
/**
 * Legacy importer for etucart-variation-swatches.
 *
 * The module re-uses the same option keys (`etucart_vs_shop_*`, `etucart_vs_pdp_*`)
 * so admins keep their existing settings. Import is therefore a detect-only
 * operation — we just advertise that the settings will be reused.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariationSwatches;

use Freeman\Core\Core\Base_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
final class Importer extends Base_Importer {

	const LEGACY_PLUGIN_FILE = 'etucart-variation-swatches/etucart-variation-swatches.php';

	/**
	 * Import — nothing to copy because option keys are identical.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function import() {
		return array(
			'ok'      => true,
			'message' => __( 'Variation Swatches settings preserved (option keys unchanged).', 'freeman-core' ),
		);
	}
}
