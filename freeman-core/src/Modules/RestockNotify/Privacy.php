<?php
/**
 * RestockNotify — WP_Privacy exporter + eraser.
 *
 * Wave 4.1a. Wires `wp_privacy_personal_data_exporters` and
 * `wp_privacy_personal_data_erasers` so a privacy admin can request export
 * or erasure of a customer's restock-notify subscriptions via the standard
 * WP Tools → Export/Erase Personal Data flow.
 *
 * Erasure semantics (OS-4 / decision call 2026-05-11): the eraser does NOT
 * hard-delete rows. It clears `customer_name`, replaces `customer_email`
 * with a per-row non-PII tombstone (`erased-<id>@freeman.invalid`) and sets
 * `status='unsubscribed'`. The row stays as an audit trail and the stock
 * monitor can no longer match the original email on future restocks.
 *
 * Flag-state (OS-5 / decision call 2026-05-11): registered unconditionally.
 * Privacy hooks are a platform contract — flag-gating them off by default
 * would silently break GDPR-export requests for sites that haven't enabled
 * the feature. The Wave 4.1b CSV-export admin button is flag-gated; the
 * privacy hooks are not.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\RestockNotify;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy hook integration for RestockNotify subscriptions.
 */
final class Privacy {

	/**
	 * Exporter / eraser key under which we register with WP_Privacy.
	 */
	private const KEY = 'freeman-core-restock-notify';

	/**
	 * Attach to WP_Privacy filter surfaces.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers',   array( $this, 'register_eraser' ) );
	}

	/**
	 * Register the exporter in the WP_Privacy exporters map.
	 *
	 * @param array<string,array> $exporters Existing exporter map.
	 * @return array<string,array>
	 */
	public function register_exporter( $exporters ) {
		$exporters[ self::KEY ] = array(
			'exporter_friendly_name' => __( 'Restock Notify subscriptions', 'freeman-core' ),
			'callback'               => array( $this, 'exporter' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser in the WP_Privacy erasers map.
	 *
	 * @param array<string,array> $erasers Existing eraser map.
	 * @return array<string,array>
	 */
	public function register_eraser( $erasers ) {
		$erasers[ self::KEY ] = array(
			'eraser_friendly_name' => __( 'Restock Notify subscriptions', 'freeman-core' ),
			'callback'             => array( $this, 'eraser' ),
		);
		return $erasers;
	}

	/**
	 * Build the WP_Privacy export payload for an email address.
	 *
	 * Returns `done => true` without paginating. The bound that matters is
	 * not table-wide row count but per-email result count, which is
	 * naturally capped by how many distinct products one customer has
	 * subscribed to (a handful at most). `customer_email` is indexed in
	 * the legacy schema (`KEY customer_email`), so the lookup stays
	 * sub-millisecond regardless of overall table size.
	 *
	 * @param string $email_address Customer email to export.
	 * @param int    $page          WP_Privacy pagination cursor (unused).
	 * @return array{data:array,done:bool}
	 */
	public function exporter( $email_address, $page = 1 ) {
		$rows = Subscribers::find_by_email( (string) $email_address );
		$data = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => self::KEY,
				'group_label' => __( 'Restock Notify subscriptions', 'freeman-core' ),
				'item_id'     => 'restock-notify-' . (int) $row->id,
				'data'        => array(
					array( 'name' => __( 'Subscription ID', 'freeman-core' ),   'value' => (int) $row->id ),
					array( 'name' => __( 'Product ID', 'freeman-core' ),        'value' => (int) $row->product_id ),
					array( 'name' => __( 'Variation ID', 'freeman-core' ),      'value' => (int) $row->variation_id ),
					array( 'name' => __( 'Customer Name', 'freeman-core' ),     'value' => (string) $row->customer_name ),
					array( 'name' => __( 'Customer Email', 'freeman-core' ),    'value' => (string) $row->customer_email ),
					array( 'name' => __( 'Status', 'freeman-core' ),            'value' => (string) $row->status ),
					array( 'name' => __( 'Subscribed at', 'freeman-core' ),     'value' => (string) $row->created_at ),
					array( 'name' => __( 'Notified at', 'freeman-core' ),       'value' => (string) ( $row->notified_at ?? '' ) ),
					array( 'name' => __( 'Unsubscribe Token', 'freeman-core' ), 'value' => (string) $row->unsubscribe_token ),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase PII for an email address.
	 *
	 * @param string $email_address Customer email to erase.
	 * @param int    $page          WP_Privacy pagination cursor (unused).
	 * @return array{items_removed:int,items_retained:int,messages:array,done:bool}
	 */
	public function eraser( $email_address, $page = 1 ) {
		$removed = Subscribers::erase_pii_by_email( (string) $email_address );
		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
