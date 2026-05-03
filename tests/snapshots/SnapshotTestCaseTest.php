<?php
declare(strict_types=1);

require_once __DIR__ . '/SnapshotTestCase.php';

use Freeman\Tests\Snapshots\SnapshotTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class SnapshotTestCaseTest extends TestCase {
	use SnapshotTestCase;

	private string $golden_dir;
	private string $tmp_name = '__snapshot_meta_test__.txt';

	protected function setUp(): void {
		parent::setUp();
		$this->golden_dir = __DIR__ . '/__golden__';
		if ( ! is_dir( $this->golden_dir ) ) {
			mkdir( $this->golden_dir, 0755, true );
		}
		// Remove any leftover from a previous run.
		$path = $this->golden_dir . '/' . $this->tmp_name;
		if ( file_exists( $path ) ) {
			unlink( $path );
		}
		putenv( 'UPDATE_SNAPSHOTS' );
	}

	protected function tearDown(): void {
		$path = $this->golden_dir . '/' . $this->tmp_name;
		if ( file_exists( $path ) ) {
			unlink( $path );
		}
		putenv( 'UPDATE_SNAPSHOTS' );
		parent::tearDown();
	}

	public function test_matching_string_passes(): void {
		file_put_contents( $this->golden_dir . '/' . $this->tmp_name, "hello\n" );
		$this->assertSnapshotMatches( $this->tmp_name, 'hello' );
	}

	public function test_diverging_string_fails_with_diff_message(): void {
		file_put_contents( $this->golden_dir . '/' . $this->tmp_name, "hello\n" );
		try {
			$this->assertSnapshotMatches( $this->tmp_name, 'goodbye' );
			$this->fail( 'Expected AssertionFailedError' );
		} catch ( AssertionFailedError $e ) {
			$this->assertStringContainsString( 'Snapshot mismatch', $e->getMessage() );
			$this->assertStringContainsString( '- hello', $e->getMessage() );
			$this->assertStringContainsString( '+ goodbye', $e->getMessage() );
		}
	}

	public function test_update_snapshots_env_rewrites_golden(): void {
		file_put_contents( $this->golden_dir . '/' . $this->tmp_name, "old\n" );
		putenv( 'UPDATE_SNAPSHOTS=1' );
		$this->assertSnapshotMatches( $this->tmp_name, 'new' );
		$this->assertSame( "new\n", file_get_contents( $this->golden_dir . '/' . $this->tmp_name ) );
	}

	public function test_rejects_crlf_input(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/CR or CRLF/' );
		$this->assertSnapshotMatches( $this->tmp_name, "a\r\nb" );
	}

	public function test_rejects_bom_input(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/BOM/' );
		$this->assertSnapshotMatches( $this->tmp_name, "\xEF\xBB\xBFhello" );
	}

	public function test_normalizes_to_single_trailing_newline(): void {
		// Write 3 trailing newlines, expect storage to collapse to one.
		putenv( 'UPDATE_SNAPSHOTS=1' );
		$this->assertSnapshotMatches( $this->tmp_name, "hello\n\n\n" );
		$this->assertSame( "hello\n", file_get_contents( $this->golden_dir . '/' . $this->tmp_name ) );
	}
}
