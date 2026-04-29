<?php
/**
 * Shared `\RSN_Database` stub for unit tests.
 *
 * The real legacy class lives in
 * `freeman-core/src/Modules/RestockNotify/legacy/includes/class-rsn-database.php`
 * and is only loaded inside `Module::boot()` — which the tests never exercise.
 * This stub captures method calls into a public static array and lets the
 * caller drive return values via static properties.
 *
 * Used by:
 *   - tests/SubscribersTest.php (Wave 2.3a wrapper delegation)
 *   - tests/RestockNotifyStockMonitorTest.php (Wave 2.3b stock monitor)
 *
 * If you add a new test that touches `\RSN_Database`, require this file
 * first; do NOT redeclare the class inline (PHP forbids the redeclare and
 * test order becomes load-order-sensitive).
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\RSN_Database' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	class RSN_Database {
		public static array $calls                          = array();
		public static $get_waiting_for_product_return       = array();
		public static $mark_notified_return                  = 1;
		public static $get_by_token_return                   = null;
		public static $unsubscribe_return                    = 1;

		public static function get_waiting_for_product( $product_id, $variation_id = 0 ) {
			self::$calls[] = array(
				'method' => 'get_waiting_for_product',
				'args'   => array( $product_id, $variation_id ),
			);
			return self::$get_waiting_for_product_return;
		}
		public static function mark_notified( $id ) {
			self::$calls[] = array(
				'method' => 'mark_notified',
				'args'   => array( $id ),
			);
			return self::$mark_notified_return;
		}
		public static function get_by_token( $token ) {
			self::$calls[] = array(
				'method' => 'get_by_token',
				'args'   => array( $token ),
			);
			return self::$get_by_token_return;
		}
		public static function unsubscribe( $id ) {
			self::$calls[] = array(
				'method' => 'unsubscribe',
				'args'   => array( $id ),
			);
			return self::$unsubscribe_return;
		}
	}
}
