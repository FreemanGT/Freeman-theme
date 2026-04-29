<?php
declare(strict_types=1);

use Freeman\Core\Modules\RestockNotify\Subscribers;
use PHPUnit\Framework\TestCase;

// Stub `RSN_Database` before the legacy file gets a chance to load it.
// `Subscribers::*` calls resolve to this stub at runtime; the real legacy
// class is only require_once'd from Module::boot(), which the tests never
// exercise. Public static properties capture call args + drive returns.
if ( ! class_exists( '\RSN_Database' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	class RSN_Database {
		public static array $calls = array();
		public static $get_waiting_for_product_return = array();
		public static $mark_notified_return = 1;
		public static $get_by_token_return = null;
		public static $unsubscribe_return = 1;

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

/**
 * @covers \Freeman\Core\Modules\RestockNotify\Subscribers
 */
final class SubscribersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\RSN_Database::$calls                          = array();
		\RSN_Database::$get_waiting_for_product_return = array();
		\RSN_Database::$mark_notified_return           = 1;
		\RSN_Database::$get_by_token_return            = null;
		\RSN_Database::$unsubscribe_return             = 1;
	}

	public function test_get_waiting_for_product_delegates_with_default_variation_id(): void {
		\RSN_Database::$get_waiting_for_product_return = array( (object) array( 'id' => 7 ) );

		$rows = Subscribers::get_waiting_for_product( 42 );

		$this->assertSame(
			array( array( 'method' => 'get_waiting_for_product', 'args' => array( 42, 0 ) ) ),
			\RSN_Database::$calls
		);
		$this->assertCount( 1, $rows );
		$this->assertSame( 7, $rows[0]->id );
	}

	public function test_get_waiting_for_product_passes_variation_id(): void {
		Subscribers::get_waiting_for_product( 42, 99 );

		$this->assertSame( array( 42, 99 ), \RSN_Database::$calls[0]['args'] );
	}

	public function test_mark_notified_delegates_and_returns_legacy_value(): void {
		\RSN_Database::$mark_notified_return = 1;

		$result = Subscribers::mark_notified( 5 );

		$this->assertSame( 1, $result );
		$this->assertSame( 'mark_notified', \RSN_Database::$calls[0]['method'] );
		$this->assertSame( array( 5 ), \RSN_Database::$calls[0]['args'] );
	}

	public function test_get_by_token_returns_null_pass_through(): void {
		\RSN_Database::$get_by_token_return = null;

		$row = Subscribers::get_by_token( 'abc123' );

		$this->assertNull( $row );
		$this->assertSame( array( 'abc123' ), \RSN_Database::$calls[0]['args'] );
	}

	public function test_get_by_token_returns_row_object(): void {
		\RSN_Database::$get_by_token_return = (object) array( 'id' => 11, 'customer_email' => 'x@y.test' );

		$row = Subscribers::get_by_token( 'tok' );

		$this->assertSame( 11, $row->id );
		$this->assertSame( 'x@y.test', $row->customer_email );
	}

	public function test_unsubscribe_delegates(): void {
		\RSN_Database::$unsubscribe_return = 1;

		$result = Subscribers::unsubscribe( 17 );

		$this->assertSame( 1, $result );
		$this->assertSame( 'unsubscribe', \RSN_Database::$calls[0]['method'] );
		$this->assertSame( array( 17 ), \RSN_Database::$calls[0]['args'] );
	}
}
