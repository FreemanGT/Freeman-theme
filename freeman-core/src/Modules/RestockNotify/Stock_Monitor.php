<?php
/**
 * Modern Stock_Monitor for RestockNotify — Wave 2.3b.
 *
 * Replaces the legacy `\RSN_Stock_Monitor` class via `class_alias` in
 * `Module::boot()`. Same instance + static API so legacy callers
 * (`RSN_Admin::handle_actions()` calling `\RSN_Stock_Monitor::manual_notify`)
 * keep working unchanged after the alias resolves.
 *
 * Behavior parity with the legacy class is the goal: WC stock-status /
 * quantity hooks fire `notify()`, which iterates waiting subscribers via the
 * `Subscribers` repository (Wave 2.3a) and dispatches via the modern `Email`
 * class. The `rsn_notification_log` option write — capped at 100 entries — is
 * preserved verbatim so the admin dashboard's "recent notifications" widget
 * keeps working.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\RestockNotify;

defined( 'ABSPATH' ) || exit;

/**
 * Stock_Monitor.
 */
final class Stock_Monitor {

	public function __construct() {
		add_action( 'woocommerce_product_set_stock_status',   array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'woocommerce_product_set_stock',          array( $this, 'on_qty_change' ) );
		add_action( 'woocommerce_variation_set_stock',        array( $this, 'on_qty_change' ) );
	}

	/**
	 * WC stock-status change handler. Fires notifications when status flips
	 * to `'instock'`.
	 *
	 * @param int        $product_id  Product / variation id.
	 * @param string     $stock_status `'instock'`, `'outofstock'`, or `'onbackorder'`.
	 * @param \WC_Product $product     Product object.
	 */
	public function on_status_change( $product_id, $stock_status, $product ) {
		if ( 'instock' === $stock_status ) {
			$this->notify( $product_id, $product );
		}
	}

	/**
	 * WC stock-quantity change handler. Fires notifications when the new
	 * quantity is positive AND the product currently reports as in stock
	 * (filtering out backorder-allowed quirks).
	 *
	 * @param \WC_Product $product Product object.
	 */
	public function on_qty_change( $product ) {
		if ( $product && $product->get_stock_quantity() > 0 && $product->is_in_stock() ) {
			$this->notify( $product->get_id(), $product );
		}
	}

	/**
	 * Iterate waiting subscribers for the given product/variation and send
	 * notifications via `\RSN_Email::send_notification` (which after Wave
	 * 2.3b's `class_alias` resolves to the modern `Email::send_notification`).
	 *
	 * Logs the count to `rsn_notification_log` (capped at 100 entries) so the
	 * admin dashboard's recent-notifications widget keeps working.
	 *
	 * @param int        $product_id Product / variation id.
	 * @param \WC_Product $product   Product object.
	 */
	private function notify( $product_id, $product ) {
		$is_var = $product->is_type( 'variation' );
		$subs   = $is_var
			? Subscribers::get_waiting_for_product( $product->get_parent_id(), $product_id )
			: Subscribers::get_waiting_for_product( $product_id, 0 );

		if ( empty( $subs ) ) {
			return;
		}

		$c = 0;
		foreach ( $subs as $s ) {
			if ( Email::send_notification( $s ) ) {
				Subscribers::mark_notified( $s->id );
				++$c;
			}
		}

		if ( $c ) {
			$log   = get_option( 'rsn_notification_log', array() );
			$log[] = array(
				'product_id' => $product_id,
				'count'      => $c,
				'date'       => current_time( 'mysql' ),
			);
			if ( count( $log ) > 100 ) {
				$log = array_slice( $log, -100 );
			}
			update_option( 'rsn_notification_log', $log );
		}
	}

	/**
	 * Manual-notify trigger from the admin Subscribers page.
	 *
	 * Mirrors the legacy `\RSN_Stock_Monitor::manual_notify()` static
	 * signature so `RSN_Admin::handle_actions()` keeps working unchanged
	 * after the `class_alias` swap.
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id, or 0 for parent-level.
	 * @return int Number of notification emails dispatched.
	 */
	public static function manual_notify( $product_id, $variation_id = 0 ) {
		$subs = Subscribers::get_waiting_for_product( $product_id, $variation_id );
		if ( empty( $subs ) ) {
			return 0;
		}
		$c = 0;
		foreach ( $subs as $s ) {
			if ( Email::send_notification( $s ) ) {
				Subscribers::mark_notified( $s->id );
				++$c;
			}
		}
		return $c;
	}
}
