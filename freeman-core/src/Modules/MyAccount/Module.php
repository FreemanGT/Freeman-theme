<?php
/**
 * My Account module.
 *
 * Restyles the classic shortcode-based WooCommerce My Account page
 * (`[woocommerce_my_account]`) into the Freeman editorial look — sidebar
 * navigation, serif page titles, mono eyebrows, hairline tables, pill status
 * chips. Markup is left untouched: the module only enqueues a stylesheet on
 * pages where `is_account_page()` is true.
 *
 * No new endpoints, no template overrides, no JS.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\MyAccount;

use Freeman\Core\Core\Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	/**
	 * @return string
	 */
	public function id() {
		return 'my_account';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'My Account', 'freeman-core' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Editorial restyle of the classic shortcode-based WooCommerce My Account page. Adds a sidebar layout, serif headings, mono eyebrows, and hairline tables — no markup or endpoint changes.', 'freeman-core' );
	}

	/**
	 * No settings — pure visual layer.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array();
	}

	/**
	 * Boot.
	 */
	public function boot() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the My Account stylesheet only on the My Account page (and its
	 * endpoints — view-order, edit-address, downloads, etc.).
	 */
	public function enqueue() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		wp_enqueue_style(
			'freeman-core-my-account',
			$this->asset_min_url( 'css/my-account.css' ),
			array(),
			FREEMAN_CORE_VERSION
		);
	}
}
