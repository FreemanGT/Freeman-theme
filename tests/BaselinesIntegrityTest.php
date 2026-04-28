<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Wave 0.4: regenerates the static baselines into a temp dir and asserts
 * each one matches the committed file byte-for-byte. A failing assertion
 * means the public surface of freeman-core / freeman-digital drifted
 * without the baseline file being updated in the same PR.
 *
 * The live `baseline-options.txt` (captured via `wp option list`) is
 * intentionally not asserted here — it's a human-curated snapshot.
 */
final class BaselinesIntegrityTest extends TestCase {

	private const BASELINES = array(
		'baseline-hooks.txt',
		'baseline-rest.txt',
		'baseline-cli.txt',
		'baseline-options-declared.txt',
		'baseline-options-legacy.txt',
	);

	public function test_capture_script_output_matches_committed_baselines(): void {
		$repo_root = dirname( __DIR__ );
		$script    = $repo_root . '/tools/capture-baselines.sh';
		$tests_dir = $repo_root . '/tests';

		$this->assertFileExists( $script, 'capture-baselines.sh missing' );
		$this->assertTrue( is_executable( $script ), 'capture-baselines.sh must be executable' );

		// Snapshot committed baselines, swap tests/ aside, regenerate, compare, restore.
		$committed = array();
		foreach ( self::BASELINES as $name ) {
			$path = $tests_dir . '/' . $name;
			$this->assertFileExists( $path, "Committed baseline missing: {$name}" );
			$committed[ $name ] = file_get_contents( $path );
		}

		$tmp = sys_get_temp_dir() . '/freeman-baselines-' . bin2hex( random_bytes( 4 ) );
		mkdir( $tmp, 0700, true );

		try {
			foreach ( self::BASELINES as $name ) {
				rename( $tests_dir . '/' . $name, $tmp . '/' . $name );
			}

			$cmd = 'cd ' . escapeshellarg( $repo_root )
				. ' && LC_ALL=C bash ' . escapeshellarg( $script ) . ' 2>&1';
			$output = array();
			$status = 0;
			exec( $cmd, $output, $status );

			$this->assertSame(
				0,
				$status,
				"capture-baselines.sh exited {$status}:\n" . implode( "\n", $output )
			);

			foreach ( self::BASELINES as $name ) {
				$regenerated = file_get_contents( $tests_dir . '/' . $name );
				$this->assertSame(
					$committed[ $name ],
					$regenerated,
					"Baseline drift detected in {$name}. "
					. 'Run `bash tools/capture-baselines.sh` and commit the diff '
					. '(or fix the source change that caused it).'
				);
			}
		} finally {
			// Restore originals so a failing run does not leave the repo dirty.
			foreach ( self::BASELINES as $name ) {
				$tmp_path = $tmp . '/' . $name;
				if ( file_exists( $tmp_path ) ) {
					rename( $tmp_path, $tests_dir . '/' . $name );
				}
			}
			@rmdir( $tmp );
		}
	}
}
