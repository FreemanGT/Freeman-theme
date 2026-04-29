<?php
declare(strict_types=1);

require_once __DIR__ . '/../freeman-core/src/Modules/RestockNotify/legacy/helpers.php';
require_once __DIR__ . '/snapshots/__fixtures__/wc_product_stub.php';
require_once __DIR__ . '/__stubs__/rsn_database_stub.php';

use Freeman\Core\Modules\RestockNotify\Stock_Monitor;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) {
		return $GLOBALS['fr_wc_get_product_return'] ?? new \WC_Product();
	}
}

/**
 * Variant of WC_Product that drives `is_type()` and stock-quantity returns.
 * Inline because tests need different return shapes per case.
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
final class TestStockMonitorProduct extends \WC_Product {
	public bool $is_variation_type = false;
	public int $stock_qty           = 0;
	public bool $in_stock           = true;
	public int $id                  = 42;
	public int $parent_id           = 0;
	public function is_type( $type ) { return ( 'variation' === $type ) ? $this->is_variation_type : false; }
	public function get_stock_quantity() { return $this->stock_qty; }
	public function is_in_stock() { return $this->in_stock; }
	public function get_id() { return $this->id; }
	public function get_parent_id() { return $this->parent_id; }
}

/**
 * @covers \Freeman\Core\Modules\RestockNotify\Stock_Monitor
 */
final class RestockNotifyStockMonitorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                     = array();
		$GLOBALS['fr_hooks']                    = array();
		$GLOBALS['fr_locale']                   = 'en_US';
		$GLOBALS['fr_wp_mail_calls']            = array();
		$GLOBALS['fr_wp_mail_return']           = true;
		$GLOBALS['fr_wc_get_product_return']    = new \WC_Product();
		$GLOBALS['fr_blogname']                 = 'Acme Shop';

		\RSN_Database::$calls                          = array();
		\RSN_Database::$get_waiting_for_product_return = array();
		\RSN_Database::$mark_notified_return           = 1;

		// Email needs these to render without errors.
		update_option( 'rsn_notify_subject',     '{product_name} is back' );
		update_option( 'rsn_notify_heading',     "It's back!" );
		update_option( 'rsn_notify_body',        '<strong>{product_name}</strong> is back.' );
		update_option( 'rsn_notify_button_text', 'Buy now' );
		update_option( 'rsn_from_name',          'Acme' );
		update_option( 'rsn_from_email',         'shop@example.test' );
		update_option( 'admin_email',            'admin@example.test' );
	}

	public function test_constructor_registers_woocommerce_stock_change_hooks(): void {
		new Stock_Monitor();

		$this->assertNotEmpty( $GLOBALS['fr_hooks']['woocommerce_product_set_stock_status']   ?? array() );
		$this->assertNotEmpty( $GLOBALS['fr_hooks']['woocommerce_variation_set_stock_status'] ?? array() );
		$this->assertNotEmpty( $GLOBALS['fr_hooks']['woocommerce_product_set_stock']          ?? array() );
		$this->assertNotEmpty( $GLOBALS['fr_hooks']['woocommerce_variation_set_stock']        ?? array() );
	}

	public function test_on_status_change_to_instock_triggers_notify(): void {
		\RSN_Database::$get_waiting_for_product_return = array(
			(object) array( 'id' => 11, 'product_id' => 42, 'variation_id' => 0, 'customer_name' => 'A', 'customer_email' => 'a@x.test', 'unsubscribe_token' => 'tok11' ),
		);
		$product = new TestStockMonitorProduct();

		( new Stock_Monitor() )->on_status_change( 42, 'instock', $product );

		$this->assertCount( 1, $GLOBALS['fr_wp_mail_calls'], 'One subscriber → one wp_mail call' );
		// mark_notified was called for the same subscription id.
		$marked = array_filter( \RSN_Database::$calls, static fn( $c ) => 'mark_notified' === $c['method'] );
		$this->assertSame( array( 11 ), reset( $marked )['args'] );
	}

	public function test_on_status_change_to_outofstock_does_nothing(): void {
		\RSN_Database::$get_waiting_for_product_return = array(
			(object) array( 'id' => 11, 'product_id' => 42, 'variation_id' => 0, 'customer_name' => 'A', 'customer_email' => 'a@x.test', 'unsubscribe_token' => 'tok11' ),
		);
		$product = new TestStockMonitorProduct();

		( new Stock_Monitor() )->on_status_change( 42, 'outofstock', $product );

		$this->assertCount( 0, $GLOBALS['fr_wp_mail_calls'] );
		$this->assertCount( 0, \RSN_Database::$calls, 'Subscribers query must not even run on outofstock' );
	}

	public function test_on_qty_change_with_zero_quantity_does_nothing(): void {
		$product             = new TestStockMonitorProduct();
		$product->stock_qty  = 0;
		$product->in_stock   = false;

		( new Stock_Monitor() )->on_qty_change( $product );

		$this->assertCount( 0, $GLOBALS['fr_wp_mail_calls'] );
	}

	public function test_on_qty_change_with_positive_quantity_triggers_notify(): void {
		\RSN_Database::$get_waiting_for_product_return = array(
			(object) array( 'id' => 22, 'product_id' => 42, 'variation_id' => 0, 'customer_name' => 'B', 'customer_email' => 'b@x.test', 'unsubscribe_token' => 'tok22' ),
		);
		$product            = new TestStockMonitorProduct();
		$product->stock_qty = 5;
		$product->in_stock  = true;

		( new Stock_Monitor() )->on_qty_change( $product );

		$this->assertCount( 1, $GLOBALS['fr_wp_mail_calls'] );
	}

	public function test_manual_notify_returns_count_and_marks_notified(): void {
		\RSN_Database::$get_waiting_for_product_return = array(
			(object) array( 'id' => 31, 'product_id' => 42, 'variation_id' => 0, 'customer_name' => 'C', 'customer_email' => 'c@x.test', 'unsubscribe_token' => 'tok31' ),
			(object) array( 'id' => 32, 'product_id' => 42, 'variation_id' => 0, 'customer_name' => 'D', 'customer_email' => 'd@x.test', 'unsubscribe_token' => 'tok32' ),
		);

		$count = Stock_Monitor::manual_notify( 42, 0 );

		$this->assertSame( 2, $count );
		$this->assertCount( 2, $GLOBALS['fr_wp_mail_calls'] );
		$marked = array_filter( \RSN_Database::$calls, static fn( $c ) => 'mark_notified' === $c['method'] );
		$this->assertCount( 2, $marked );
	}

	public function test_notification_log_appends_and_caps_at_100(): void {
		// Pre-seed with 100 entries — next notify() call should still result
		// in array of exactly 100 (oldest dropped).
		$preset = array_fill( 0, 100, array( 'product_id' => 1, 'count' => 1, 'date' => '2024-01-01 00:00:00' ) );
		update_option( 'rsn_notification_log', $preset );

		\RSN_Database::$get_waiting_for_product_return = array(
			(object) array( 'id' => 99, 'product_id' => 42, 'variation_id' => 0, 'customer_name' => 'E', 'customer_email' => 'e@x.test', 'unsubscribe_token' => 'tok99' ),
		);
		$product = new TestStockMonitorProduct();

		( new Stock_Monitor() )->on_status_change( 42, 'instock', $product );

		$log = get_option( 'rsn_notification_log', array() );
		$this->assertCount( 100, $log, 'Log capped at 100 entries' );
		$this->assertSame( 42, $log[99]['product_id'], 'Newest entry appended at the end' );
	}
}
