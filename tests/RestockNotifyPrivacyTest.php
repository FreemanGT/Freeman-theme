<?php
declare(strict_types=1);

use Freeman\Core\Core\Plugin;
use Freeman\Core\Modules\RestockNotify\Privacy;
use Freeman\Core\Modules\RestockNotify\Subscribers;
use PHPUnit\Framework\TestCase;

/**
 * Wave 4.1a — RestockNotify WP_Privacy exporter + eraser.
 *
 * Verifies filter attachment, exporter payload shape, eraser semantics
 * (scrub PII columns via tombstone + flip status to 'unsubscribed'),
 * the no-match paths, and the empty-string guards on the two new
 * Subscribers methods.
 *
 * @covers \Freeman\Core\Modules\RestockNotify\Privacy
 * @covers \Freeman\Core\Modules\RestockNotify\Subscribers::find_by_email
 * @covers \Freeman\Core\Modules\RestockNotify\Subscribers::erase_pii_by_email
 */
final class RestockNotifyPrivacyTest extends TestCase {

	/** @var object $original_wpdb */
	private $original_wpdb;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_hooks'] = array();

		$this->original_wpdb = $GLOBALS['wpdb'] ?? null;

		// Inline $wpdb mock with an in-memory rsn_subscribers row store.
		// Self-contained — does not leak into other tests (tearDown restores
		// the prior global). Only supports the methods Wave 4.1a needs:
		// prefix, prepare (best-effort %s/%d substitution), get_results,
		// update (matches by 'customer_email' WHERE and mutates the store).
		$GLOBALS['wpdb'] = new class {
			public $prefix = 'wp_';
			public array $rows = array();

			public function prepare( $sql, ...$args ) {
				if ( 1 === count( $args ) && is_array( $args[0] ) ) {
					$args = $args[0];
				}
				$out = $sql;
				foreach ( $args as $a ) {
					$replacement = is_int( $a ) ? (string) $a : "'" . str_replace( "'", "''", (string) $a ) . "'";
					$out         = preg_replace( '/%[sd]/', $replacement, $out, 1 );
				}
				return $out;
			}

			public function get_results( $sql ) {
				if ( preg_match( "/customer_email = '([^']*)'/", $sql, $m ) ) {
					$email = str_replace( "''", "'", $m[1] );
					return array_values( array_filter(
						$this->rows,
						static fn( $r ) => $r->customer_email === $email
					) );
				}
				return array();
			}

			public function update( $table, $data, $where, $format = null, $where_format = null ) {
				$matched = 0;
				foreach ( $this->rows as $row ) {
					$ok = true;
					foreach ( $where as $k => $v ) {
						if ( ( $row->$k ?? null ) !== $v ) {
							$ok = false;
							break;
						}
					}
					if ( ! $ok ) {
						continue;
					}
					foreach ( $data as $k => $v ) {
						$row->$k = $v;
					}
					$matched++;
				}
				return $matched;
			}
		};
	}

	protected function tearDown(): void {
		$this->reset_plugin_singleton();
		$GLOBALS['wpdb'] = $this->original_wpdb;
		parent::tearDown();
	}

	/**
	 * Seed a row into the inline $wpdb mock.
	 */
	private function seed_row( array $overrides = array() ): object {
		static $next_id = 1;
		$row = (object) array_merge(
			array(
				'id'                => $next_id++,
				'product_id'        => 100,
				'variation_id'      => 0,
				'customer_name'     => 'Alice',
				'customer_email'    => 'alice@example.test',
				'status'            => 'waiting',
				'unsubscribe_token' => 'tok-abcdef',
				'created_at'        => '2026-05-01 10:00:00',
				'notified_at'       => null,
			),
			$overrides
		);
		$GLOBALS['wpdb']->rows[] = $row;
		return $row;
	}

	private function reset_plugin_singleton(): void {
		$ref  = new ReflectionClass( Plugin::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	public function test_register_attaches_to_wp_privacy_exporters_filter(): void {
		( new Privacy() )->register();

		$this->assertArrayHasKey( 'wp_privacy_personal_data_exporters', $GLOBALS['fr_hooks'] );
		$this->assertNotEmpty( $GLOBALS['fr_hooks']['wp_privacy_personal_data_exporters'] );
	}

	public function test_register_attaches_to_wp_privacy_erasers_filter(): void {
		( new Privacy() )->register();

		$this->assertArrayHasKey( 'wp_privacy_personal_data_erasers', $GLOBALS['fr_hooks'] );
		$this->assertNotEmpty( $GLOBALS['fr_hooks']['wp_privacy_personal_data_erasers'] );
	}

	public function test_exporter_registration_adds_callback_under_freeman_key(): void {
		$out = ( new Privacy() )->register_exporter( array() );

		$this->assertArrayHasKey( 'freeman-core-restock-notify', $out );
		$this->assertArrayHasKey( 'callback', $out['freeman-core-restock-notify'] );
		$this->assertArrayHasKey( 'exporter_friendly_name', $out['freeman-core-restock-notify'] );
	}

	public function test_exporter_returns_done_true_and_one_item_per_subscription(): void {
		$this->seed_row( array( 'product_id' => 100 ) );
		$this->seed_row( array( 'product_id' => 200 ) );
		$this->seed_row( array( 'customer_email' => 'bob@example.test' ) );

		$result = ( new Privacy() )->exporter( 'alice@example.test' );

		$this->assertTrue( $result['done'] );
		$this->assertCount( 2, $result['data'] );
	}

	public function test_exporter_item_includes_all_9_data_fields(): void {
		$this->seed_row( array(
			'id'                => 42,
			'product_id'        => 100,
			'variation_id'      => 7,
			'customer_name'     => 'Alice',
			'customer_email'    => 'alice@example.test',
			'status'            => 'waiting',
			'unsubscribe_token' => 'tok-xyz',
			'created_at'        => '2026-05-01 10:00:00',
			'notified_at'       => '2026-05-02 11:00:00',
		) );

		$result = ( new Privacy() )->exporter( 'alice@example.test' );
		$item   = $result['data'][0];
		$names  = array_column( $item['data'], 'name' );

		$this->assertCount( 9, $item['data'] );
		$this->assertContains( 'Subscription ID', $names );
		$this->assertContains( 'Product ID', $names );
		$this->assertContains( 'Variation ID', $names );
		$this->assertContains( 'Customer Name', $names );
		$this->assertContains( 'Customer Email', $names );
		$this->assertContains( 'Status', $names );
		$this->assertContains( 'Subscribed at', $names );
		$this->assertContains( 'Notified at', $names );
		$this->assertContains( 'Unsubscribe Token', $names );
		$this->assertSame( 'restock-notify-42', $item['item_id'] );
	}

	public function test_exporter_no_match_returns_empty_data_done_true(): void {
		$this->seed_row( array( 'customer_email' => 'bob@example.test' ) );

		$result = ( new Privacy() )->exporter( 'nobody@example.test' );

		$this->assertSame( array(), $result['data'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_eraser_scrubs_name_and_tombstones_email_and_sets_status_unsubscribed(): void {
		$row = $this->seed_row( array(
			'id'             => 42,
			'customer_name'  => 'Alice',
			'customer_email' => 'alice@example.test',
			'status'         => 'waiting',
		) );

		( new Privacy() )->eraser( 'alice@example.test' );

		$this->assertSame( '', $row->customer_name );
		$this->assertSame( 'erased-42@freeman.invalid', $row->customer_email );
		$this->assertSame( 'unsubscribed', $row->status );
	}

	public function test_eraser_uses_unique_tombstones_for_same_product_status_history(): void {
		$waiting = $this->seed_row( array(
			'id'             => 101,
			'product_id'     => 500,
			'variation_id'   => 7,
			'customer_email' => 'alice@example.test',
			'status'         => 'waiting',
		) );
		$notified = $this->seed_row( array(
			'id'             => 102,
			'product_id'     => 500,
			'variation_id'   => 7,
			'customer_email' => 'alice@example.test',
			'status'         => 'notified',
		) );

		$result = ( new Privacy() )->eraser( 'alice@example.test' );

		$this->assertSame( 2, $result['items_removed'] );
		$this->assertSame( '', $waiting->customer_name );
		$this->assertSame( '', $notified->customer_name );
		$this->assertSame( 'erased-101@freeman.invalid', $waiting->customer_email );
		$this->assertSame( 'erased-102@freeman.invalid', $notified->customer_email );
		$this->assertSame( 'unsubscribed', $waiting->status );
		$this->assertSame( 'unsubscribed', $notified->status );
		$this->assertNotSame( $waiting->customer_email, $notified->customer_email );
	}

	public function test_eraser_returns_items_removed_count(): void {
		$this->seed_row( array( 'customer_email' => 'alice@example.test' ) );
		$this->seed_row( array( 'customer_email' => 'alice@example.test' ) );
		$this->seed_row( array( 'customer_email' => 'bob@example.test' ) );

		$result = ( new Privacy() )->eraser( 'alice@example.test' );

		$this->assertSame( 2, $result['items_removed'] );
		$this->assertSame( 0, $result['items_retained'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_eraser_no_match_returns_zero_removed_done_true(): void {
		$this->seed_row( array( 'customer_email' => 'bob@example.test' ) );

		$result = ( new Privacy() )->eraser( 'nobody@example.test' );

		$this->assertSame( 0, $result['items_removed'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_find_by_email_returns_empty_for_empty_input(): void {
		// Seed an already-erased row (customer_email='') and verify that
		// the empty-string guard prevents '' from matching the universe of
		// erased rows. Without the guard this would return $row.
		$this->seed_row( array( 'customer_email' => '' ) );

		$this->assertSame( array(), Subscribers::find_by_email( '' ) );
	}

	public function test_eraser_returns_zero_for_empty_input(): void {
		// Seed an already-erased row. The eraser must NOT touch it on an
		// empty-string call — accidental no-op-but-suspicious "erase
		// already-erased" path is the bug the guard prevents.
		$this->seed_row( array( 'customer_email' => '', 'status' => 'unsubscribed' ) );

		$result = ( new Privacy() )->eraser( '' );

		$this->assertSame( 0, $result['items_removed'] );
	}

	public function test_plugin_boot_registers_privacy_hooks_when_restock_module_disabled(): void {
		$this->reset_plugin_singleton();
		$GLOBALS['fr_hooks'] = array();
		update_option( 'freeman_core_db_version', FREEMAN_CORE_VERSION );
		update_option( 'freeman_core_modules', array( 'restock_notify' => false ) );

		Plugin::instance()->boot();

		$this->assertArrayHasKey( 'wp_privacy_personal_data_exporters', $GLOBALS['fr_hooks'] );
		$this->assertArrayHasKey( 'wp_privacy_personal_data_erasers', $GLOBALS['fr_hooks'] );
	}
}
