<?php
/**
 * Shop Filters public AJAX endpoint.
 *
 * Action `freeman_core_shop_filters_query` (admin-AJAX per decision §5.5 — no
 * REST). Public: registered for both logged-in and logged-out visitors, guarded
 * by a nonce + a per-IP rate limit. Only wired when the frontend feature flag
 * is on (Module::boot()), so flag-off leaves no public surface.
 *
 * The handler echoes JSON, so it is exercised by live QA; the registration
 * wiring and the response-shaping algebra it delegates to (Query_Builder pure
 * statics) are unit-tested.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

use Freeman\Core\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Public AJAX handler.
 */
final class Ajax {

	const ACTION = 'freeman_core_shop_filters_query';
	const NONCE  = 'freeman_core_shop_filters_query';

	/**
	 * Register the public query action (logged-in + logged-out).
	 */
	public function register() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_query' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_query' ) );
	}

	/**
	 * Handle a filter query: validate, build the response, send JSON.
	 */
	public function handle_query() {
		Security::verify_ajax_nonce( self::NONCE, '_ajax_nonce' );

		if ( ! Security::rate_limit( 'shop_filters_query', 30, 60 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please slow down.', 'freeman-core' ) ), 429 );
		}

		$request  = Security::sanitize_recursive( wp_unslash( $_REQUEST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$response = ( new Query_Builder() )->query( is_array( $request ) ? $request : array() );

		wp_send_json_success( $response );
	}
}
