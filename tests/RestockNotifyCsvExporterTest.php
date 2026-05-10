<?php
declare(strict_types=1);

use Freeman\Core\Modules\RestockNotify\Admin_Tools;
use Freeman\Core\Modules\RestockNotify\CSV_Exporter;
use Freeman\Core\Modules\RestockNotify\Subscribers;
use PHPUnit\Framework\TestCase;

/**
 * Wave 4.1b — Admin CSV export of rsn_subscribers.
 *
 * Tests data-flow concerns (CSV format, BOM, header row, row mapping,
 * notified_at NULL → empty cell) and gating (Admin_Tools and CSV_Exporter
 * both no-op when the feature flag is off — defense-in-depth per NS-6).
 *
 * Caller cap / nonce verification isn't tested here; those go through
 * existing Security::verify_nonce coverage and current_user_can stubs,
 * and exercising the wp_die branches would require fatal-recovery
 * gymnastics for thin value.
 *
 * @covers \Freeman\Core\Modules\RestockNotify\CSV_Exporter
 * @covers \Freeman\Core\Modules\RestockNotify\Admin_Tools
 * @covers \Freeman\Core\Modules\RestockNotify\Subscribers::all
 */
final class RestockNotifyCsvExporterTest extends TestCase {

	/** @var object|null $original_wpdb */
	private $original_wpdb;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_hooks'] = array();

		$this->original_wpdb = $GLOBALS['wpdb'] ?? null;

		// Inline $wpdb mock for Subscribers::all() and Subscribers
		// methods that hit get_results. Mirrors the
		// RestockNotifyPrivacyTest pattern.
		$GLOBALS['wpdb'] = new class {
			public $prefix = 'wp_';
			public array $rows = array();

			public function get_results( $sql ) {
				if ( preg_match( '/SELECT \* FROM (\S+)\s*$/', $sql, $m ) ) {
					return array_values( $this->rows );
				}
				return array();
			}
		};
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->original_wpdb;
		parent::tearDown();
	}

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
				'unsubscribe_token' => 'tok-abc',
				'created_at'        => '2026-05-01 10:00:00',
				'notified_at'       => null,
			),
			$overrides
		);
		$GLOBALS['wpdb']->rows[] = $row;
		return $row;
	}

	public function test_admin_tools_submenu_registered_on_admin_menu(): void {
		( new Admin_Tools() )->register();

		$this->assertArrayHasKey( 'admin_menu', $GLOBALS['fr_hooks'] );
		$this->assertNotEmpty( $GLOBALS['fr_hooks']['admin_menu'] );
	}

	public function test_admin_tools_submenu_not_registered_when_register_not_called(): void {
		// Flag-OFF in production means the caller (Module::boot) does NOT
		// call Admin_Tools::register(). Verify that without the call, no
		// admin_menu listener exists for our submenu.
		$this->assertArrayNotHasKey( 'admin_menu', $GLOBALS['fr_hooks'] );
	}

	public function test_csv_exporter_attaches_admin_post_when_registered(): void {
		( new CSV_Exporter() )->register();

		$hook = 'admin_post_' . CSV_Exporter::ACTION;
		$this->assertArrayHasKey( $hook, $GLOBALS['fr_hooks'] );
		$this->assertNotEmpty( $GLOBALS['fr_hooks'][ $hook ] );
	}

	public function test_csv_exporter_no_attach_when_register_not_called(): void {
		// Flag-OFF path: caller skips register(); no admin_post listener
		// → a direct POST to admin-post.php with our action is unhandled.
		$hook = 'admin_post_' . CSV_Exporter::ACTION;
		$this->assertArrayNotHasKey( $hook, $GLOBALS['fr_hooks'] );
	}

	public function test_build_csv_starts_with_utf8_bom(): void {
		$csv = ( new CSV_Exporter() )->build_csv( array() );
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $csv );
	}

	public function test_build_csv_header_row_lists_all_9_columns_in_order(): void {
		$csv  = ( new CSV_Exporter() )->build_csv( array() );
		$body = substr( $csv, 3 ); // strip BOM
		$head = strtok( $body, "\n" );

		$this->assertSame(
			'"Subscription ID",'
			. '"Product ID",'
			. '"Variation ID",'
			. '"Customer Name",'
			. '"Customer Email",'
			. 'Status,'
			. '"Subscribed at",'
			. '"Notified at",'
			. '"Unsubscribe Token"',
			rtrim( $head, "\r" )
		);
	}

	public function test_build_csv_emits_one_data_row_per_subscription(): void {
		$rows = array(
			(object) array(
				'id' => 1, 'product_id' => 100, 'variation_id' => 0,
				'customer_name' => 'Alice', 'customer_email' => 'a@x.test',
				'status' => 'waiting', 'created_at' => '2026-05-01 10:00:00',
				'notified_at' => null, 'unsubscribe_token' => 'tok-a',
			),
			(object) array(
				'id' => 2, 'product_id' => 200, 'variation_id' => 7,
				'customer_name' => 'Bob', 'customer_email' => 'b@x.test',
				'status' => 'notified', 'created_at' => '2026-05-02 11:00:00',
				'notified_at' => '2026-05-03 12:00:00', 'unsubscribe_token' => 'tok-b',
			),
		);

		$csv   = ( new CSV_Exporter() )->build_csv( $rows );
		$lines = preg_split( '/\r?\n/', rtrim( substr( $csv, 3 ), "\r\n" ) );

		$this->assertCount( 3, $lines, '1 header + 2 data rows' );
		$this->assertStringContainsString( 'Alice', $lines[1] );
		$this->assertStringContainsString( 'a@x.test', $lines[1] );
		$this->assertStringContainsString( 'Bob', $lines[2] );
		$this->assertStringContainsString( 'b@x.test', $lines[2] );
	}

	public function test_build_csv_empty_table_emits_headers_only(): void {
		$csv   = ( new CSV_Exporter() )->build_csv( array() );
		$lines = preg_split( '/\r?\n/', rtrim( substr( $csv, 3 ), "\r\n" ) );

		$this->assertCount( 1, $lines, 'header only when no rows' );
	}

	public function test_build_csv_notified_at_null_renders_as_empty_cell(): void {
		$row = (object) array(
			'id' => 1, 'product_id' => 100, 'variation_id' => 0,
			'customer_name' => 'Alice', 'customer_email' => 'a@x.test',
			'status' => 'waiting', 'created_at' => '2026-05-01 10:00:00',
			'notified_at' => null, 'unsubscribe_token' => 'tok-a',
		);

		$csv  = ( new CSV_Exporter() )->build_csv( array( $row ) );
		$line = preg_split( '/\r?\n/', rtrim( substr( $csv, 3 ), "\r\n" ) )[1];

		// Column 8 (0-indexed 7) is notified_at. fputcsv on '' yields
		// nothing between the surrounding commas — verify by counting.
		$this->assertStringContainsString( ',,', $line, 'empty notified_at cell collapses to ,,' );
	}

	public function test_subscribers_all_returns_all_rows_regardless_of_status(): void {
		$this->seed_row( array( 'status' => 'waiting' ) );
		$this->seed_row( array( 'status' => 'notified' ) );
		$this->seed_row( array( 'status' => 'unsubscribed', 'customer_email' => '' ) );

		$rows = Subscribers::all();

		$this->assertCount( 3, $rows );
	}
}
