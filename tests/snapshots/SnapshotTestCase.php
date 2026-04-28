<?php
/**
 * SnapshotTestCase — trait providing assertSnapshotMatches().
 *
 * Compares a string against a committed golden file under
 * tests/snapshots/__golden__/. On mismatch fails with a unified diff.
 * With UPDATE_SNAPSHOTS=1 set in the env, rewrites the golden instead
 * of failing — the workflow for accepting a deliberate output change.
 *
 * Goldens are required to be LF-only, no BOM, with exactly one trailing
 * newline. The trait enforces these on read AND on write so platform
 * drift (CRLF on Windows, BOM from a manual edit) surfaces as a clear
 * test failure rather than a silent byte-mismatch.
 *
 * @package FreemanCore
 */

declare(strict_types=1);

namespace Freeman\Tests\Snapshots;

trait SnapshotTestCase {

	/**
	 * Assert that $actual matches the committed golden file for $name.
	 *
	 * @param string $name   Golden filename (no directory, no extension stripped).
	 * @param string $actual Actual string output.
	 */
	protected function assertSnapshotMatches( string $name, string $actual ): void {
		$path     = __DIR__ . '/__golden__/' . $name;
		$normalized = self::normalize_for_storage( $actual );

		if ( getenv( 'UPDATE_SNAPSHOTS' ) === '1' ) {
			file_put_contents( $path, $normalized );
			$this->addToAssertionCount( 1 );
			return;
		}

		if ( ! is_readable( $path ) ) {
			$this->fail(
				"Golden file does not exist: $path\n"
				. "Run UPDATE_SNAPSHOTS=1 composer test to create it."
			);
		}

		$expected = file_get_contents( $path );

		self::assert_format_invariants( $expected, $path );

		if ( $expected === $normalized ) {
			$this->addToAssertionCount( 1 );
			return;
		}

		$this->fail(
			"Snapshot mismatch: $name\n"
			. "Run UPDATE_SNAPSHOTS=1 composer test to update if the change is intentional.\n"
			. "--- expected (golden)\n+++ actual\n"
			. self::diff( $expected, $normalized )
		);
	}

	/**
	 * Normalize a string for golden storage: reject CRLF/BOM and ensure
	 * exactly one trailing newline.
	 */
	private static function normalize_for_storage( string $s ): string {
		if ( str_starts_with( $s, "\xEF\xBB\xBF" ) ) {
			throw new \RuntimeException( 'Snapshot input contains a UTF-8 BOM. Strip it before snapshotting.' );
		}
		if ( str_contains( $s, "\r\n" ) || str_contains( $s, "\r" ) ) {
			throw new \RuntimeException( 'Snapshot input contains CR or CRLF line endings. Convert to LF before snapshotting.' );
		}
		// Exactly one trailing LF.
		return rtrim( $s, "\n" ) . "\n";
	}

	/**
	 * Assert a golden file on disk satisfies the format invariants. Any
	 * violation here means a manual edit has corrupted the file.
	 */
	private static function assert_format_invariants( string $contents, string $path ): void {
		if ( str_starts_with( $contents, "\xEF\xBB\xBF" ) ) {
			throw new \RuntimeException( "Golden file has a UTF-8 BOM: $path" );
		}
		if ( str_contains( $contents, "\r" ) ) {
			throw new \RuntimeException( "Golden file has CR/CRLF line endings: $path" );
		}
		if ( '' !== $contents && substr( $contents, -1 ) !== "\n" ) {
			throw new \RuntimeException( "Golden file is missing trailing newline: $path" );
		}
	}

	/**
	 * Tiny line-based unified-style diff. Good enough for failure messages;
	 * not a full diff implementation.
	 */
	private static function diff( string $expected, string $actual ): string {
		$exp_lines = explode( "\n", $expected );
		$act_lines = explode( "\n", $actual );
		$max       = max( count( $exp_lines ), count( $act_lines ) );
		$out       = '';
		for ( $i = 0; $i < $max; $i++ ) {
			$e = $exp_lines[ $i ] ?? '<EOF>';
			$a = $act_lines[ $i ] ?? '<EOF>';
			if ( $e === $a ) {
				continue;
			}
			$out .= sprintf( "@@ line %d\n- %s\n+ %s\n", $i + 1, $e, $a );
		}
		return $out !== '' ? $out : "(strings differ but line-by-line diff is empty — check trailing whitespace)\n";
	}
}
