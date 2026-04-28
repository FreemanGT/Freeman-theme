<?php
/**
 * Legacy importer for wc-product-feed.
 *
 * Copies the `wcpf_options` setting into Freeman module options, migrates the
 * last-generated timestamp, and clears the legacy cron + query var.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ProductFeed;

use Freeman\Core\Core\Base_Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
final class Importer extends Base_Importer {

	const LEGACY_PLUGIN_FILE = 'wc-product-feed/wc-product-feed.php';

	/**
	 * Import.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function import() {
		$legacy = get_option( 'wcpf_options' );
		if ( is_array( $legacy ) ) {
			if ( isset( $legacy['instant_update'] ) ) {
				update_option( 'freeman_core_product_feed_instant_update', $legacy['instant_update'] ? 1 : 0, false );
			}
			if ( isset( $legacy['hourly_fallback'] ) ) {
				update_option( 'freeman_core_product_feed_hourly_fallback', $legacy['hourly_fallback'] ? 1 : 0, false );
			}
		}
		$last = get_option( 'wcpf_last_generated' );
		if ( $last ) {
			update_option( Module::OPT_LAST_GEN, $last, false );
		}
		wp_clear_scheduled_hook( 'wcpf_hourly_cron' );
		wp_clear_scheduled_hook( 'wcpf_async_generate' );

		return array(
			'ok'      => true,
			'message' => __( 'Product Feed settings migrated — legacy cron cleared.', 'freeman-core' ),
		);
	}

	/**
	 * Delete legacy options.
	 */
	public function delete_legacy_options() {
		delete_option( 'wcpf_options' );
		delete_option( 'wcpf_last_generated' );
		wp_clear_scheduled_hook( 'wcpf_hourly_cron' );
		wp_clear_scheduled_hook( 'wcpf_async_generate' );
	}
}
