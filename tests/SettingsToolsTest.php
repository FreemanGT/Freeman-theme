<?php
declare(strict_types=1);

use Freeman\Core\Core\Settings_Tools;
use PHPUnit\Framework\TestCase;

final class SettingsToolsTest extends TestCase {

	private Settings_Tools $tools;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                    = array();
		$GLOBALS['fr_hooks']                   = array();
		$GLOBALS['fr_transients']              = array();
		$GLOBALS['fr_update_option_fail_keys'] = array();
		$this->tools                           = new Settings_Tools();
	}

	private function seed_options(): void {
		update_option( 'freeman_core_modules', array( 'sliders' => true ) );
		update_option( 'freeman_core_sliders_advanced_controls_enabled', '1' );
		update_option( 'freeman_digital_some_setting', 'value' );
		update_option( 'unrelated_other_plugin_option', 'leave me alone' );
		update_option( 'etucart_legacy_setting', 'legacy' );
		update_option( 'freeman_core_log', array( 'a', 'b' ) );
		update_option( 'freeman_core_boot_failures', array( 'x' ) );
	}

	// -----------------------------------------------------------------
	// Export
	// -----------------------------------------------------------------

	public function test_export_envelope_shape(): void {
		$this->seed_options();
		$env = $this->tools->export_payload();

		$this->assertSame( 1, $env['version'] );
		$this->assertNotEmpty( $env['exported_at'] );
		$this->assertArrayHasKey( 'options', $env );
		$this->assertArrayHasKey( 'site_url', $env );
	}

	public function test_export_includes_freeman_core_and_digital_keys(): void {
		$this->seed_options();
		$env = $this->tools->export_payload();

		$this->assertArrayHasKey( 'freeman_core_modules', $env['options'] );
		$this->assertArrayHasKey( 'freeman_core_sliders_advanced_controls_enabled', $env['options'] );
		$this->assertArrayHasKey( 'freeman_digital_some_setting', $env['options'] );
	}

	public function test_export_excludes_runtime_state_and_unrelated_prefixes(): void {
		$this->seed_options();
		update_option( 'freeman_core_settings_backups', array( 'should not export' ) );

		$env = $this->tools->export_payload();

		$this->assertArrayNotHasKey( 'freeman_core_log', $env['options'] );
		$this->assertArrayNotHasKey( 'freeman_core_boot_failures', $env['options'] );
		$this->assertArrayNotHasKey( 'freeman_core_settings_backups', $env['options'] );
		$this->assertArrayNotHasKey( 'unrelated_other_plugin_option', $env['options'] );
		$this->assertArrayNotHasKey( 'etucart_legacy_setting', $env['options'] );
	}

	// -----------------------------------------------------------------
	// Import: validation
	// -----------------------------------------------------------------

	public function test_import_accepts_valid_v1_envelope(): void {
		$envelope = array(
			'version'     => 1,
			'exported_at' => '2026-04-29T10:00:00+00:00',
			'site_url'    => 'https://example.test',
			'options'     => array(
				'freeman_core_modules'         => array( 'sliders' => true ),
				'freeman_digital_some_setting' => 'imported',
			),
		);

		$result = $this->tools->import( $envelope );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 2, $result['written'] );
		$this->assertSame( 'imported', get_option( 'freeman_digital_some_setting' ) );
	}

	public function test_import_rejects_version_2(): void {
		$envelope = array(
			'version'     => 2,
			'exported_at' => 'now',
			'options'     => array( 'freeman_core_x' => 'y' ),
		);

		$result = $this->tools->import( $envelope );

		$this->assertFalse( $result['ok'] );
		$this->assertStringStartsWith( 'unsupported_version:', $result['reason'] );
		$this->assertSame( 0, $result['written'] );
		$this->assertNull( $result['backup_at'] );
		$this->assertArrayNotHasKey( 'freeman_core_x', $GLOBALS['fr_opts'] );
	}

	public function test_import_rejects_malformed_envelope(): void {
		$result = $this->tools->import( array( 'version' => 1 ) ); // missing options + exported_at.

		$this->assertFalse( $result['ok'] );
		$this->assertStringStartsWith( 'missing_field:', $result['reason'] );
		$this->assertNull( $result['backup_at'] );
	}

	public function test_import_rejects_keys_outside_allowlist(): void {
		$envelope = array(
			'version'     => 1,
			'exported_at' => 'now',
			'options'     => array(
				'freeman_core_ok'        => 'a',
				'malicious_other_plugin' => 'b',
			),
		);

		$result = $this->tools->import( $envelope );

		$this->assertFalse( $result['ok'] );
		$this->assertStringStartsWith( 'disallowed_key:', $result['reason'] );
		$this->assertSame( 0, $result['written'] );
		$this->assertArrayNotHasKey( 'freeman_core_ok', $GLOBALS['fr_opts'] );
	}

	// -----------------------------------------------------------------
	// Import: logging (split into two tests)
	// -----------------------------------------------------------------

	public function test_import_logs_one_info_line_per_option(): void {
		$envelope = array(
			'version'     => 1,
			'exported_at' => 'now',
			'options'     => array(
				'freeman_core_a' => 1,
				'freeman_core_b' => 2,
				'freeman_core_c' => 3,
			),
		);

		$this->tools->import( $envelope );

		$messages = array_column( \Freeman\Core\Core\Logger::entries(), 'message' );
		$writes   = array_filter( $messages, static fn( $m ) => str_starts_with( $m, 'Settings import: writing ' ) );
		$this->assertCount( 3, $writes );
	}

	public function test_import_logs_each_write_before_writing(): void {
		$envelope = array(
			'version'     => 1,
			'exported_at' => 'now',
			'options'     => array( 'freeman_core_target' => 'new' ),
		);

		$timeline = array();
		add_action(
			'freeman_core/logger/written',
			static function ( $entry ) use ( &$timeline ) {
				if ( str_contains( $entry['message'], 'writing freeman_core_target' ) ) {
					$timeline[] = array( 'event' => 'log', 'value' => $GLOBALS['fr_opts']['freeman_core_target'] ?? null );
				}
			}
		);

		$this->tools->import( $envelope );
		$timeline[] = array( 'event' => 'after_import', 'value' => $GLOBALS['fr_opts']['freeman_core_target'] ?? null );

		// At log time, the target option must NOT yet be written ('new' must
		// only appear AFTER the log line). At the end of import it must equal 'new'.
		$this->assertNull( $timeline[0]['value'], 'Log line fired AFTER the option was written; ordering is wrong.' );
		$this->assertSame( 'new', $timeline[1]['value'] );
	}

	// -----------------------------------------------------------------
	// Auto-backup
	// -----------------------------------------------------------------

	public function test_valid_import_creates_backup_with_pre_import_state(): void {
		update_option( 'freeman_core_target', 'old' );

		$envelope = array(
			'version'     => 1,
			'exported_at' => 'now',
			'options'     => array( 'freeman_core_target' => 'new' ),
		);

		$result  = $this->tools->import( $envelope );
		$backups = $this->tools->list_backups();

		$this->assertNotNull( $result['backup_at'] );
		$this->assertCount( 1, $backups );
		$this->assertSame( 'old', $backups[0]['options']['freeman_core_target'] );
	}

	public function test_rejected_import_does_not_create_backup(): void {
		update_option( 'freeman_core_target', 'old' );

		$this->tools->import( array( 'version' => 99, 'exported_at' => 'x', 'options' => array() ) );

		$this->assertSame( array(), $this->tools->list_backups() );
	}

	public function test_skip_backup_filter_suppresses_backup(): void {
		update_option( 'freeman_core_target', 'old' );
		add_filter( 'freeman_core/tools/import/skip_backup', '__return_true' );

		$envelope = array(
			'version'     => 1,
			'exported_at' => 'now',
			'options'     => array( 'freeman_core_target' => 'new' ),
		);
		$result = $this->tools->import( $envelope );

		$this->assertNull( $result['backup_at'] );
		$this->assertSame( array(), $this->tools->list_backups() );
	}

	public function test_backup_list_is_bounded_to_max(): void {
		for ( $i = 1; $i <= 7; $i++ ) {
			update_option( 'freeman_core_counter', $i );
			$this->tools->backup_current();
		}

		$backups = $this->tools->list_backups();
		$this->assertCount( Settings_Tools::MAX_BACKUPS, $backups );
		// Most recent first.
		$this->assertSame( 7, $backups[0]['options']['freeman_core_counter'] );
		$this->assertSame( 3, $backups[ Settings_Tools::MAX_BACKUPS - 1 ]['options']['freeman_core_counter'] );
	}

	// -----------------------------------------------------------------
	// Restore
	// -----------------------------------------------------------------

	public function test_restore_writes_backup_options_and_creates_pre_restore_backup(): void {
		update_option( 'freeman_core_target', 'v1' );
		$this->tools->backup_current(); // backup of v1

		update_option( 'freeman_core_target', 'v2' );
		$result = $this->tools->restore( 0 );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'v1', get_option( 'freeman_core_target' ) );

		// Restore itself created a backup of pre-restore state (v2).
		$backups = $this->tools->list_backups();
		$this->assertSame( 'v2', $backups[0]['options']['freeman_core_target'] );
	}

	// -----------------------------------------------------------------
	// Partial-restore-failure (best-effort, halt-on-error)
	// -----------------------------------------------------------------

	public function test_import_halts_on_first_write_failure_and_surfaces_backup(): void {
		update_option( 'freeman_core_pre', 'before' );

		$envelope = array(
			'version'     => 1,
			'exported_at' => 'now',
			'options'     => array(
				'freeman_core_a' => 'A',
				'freeman_core_b' => 'B',
				'freeman_core_c' => 'C', // will fail
				'freeman_core_d' => 'D',
				'freeman_core_e' => 'E',
			),
		);

		$GLOBALS['fr_update_option_fail_keys'] = array( 'freeman_core_c' );

		$result = $this->tools->import( $envelope );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'write_failed', $result['reason'] );
		$this->assertSame( 'freeman_core_c', $result['failed_at'] );
		$this->assertSame( 2, $result['written'] );
		$this->assertSame( 5, $result['total'] );

		// Options before the failure are written.
		$this->assertSame( 'A', get_option( 'freeman_core_a' ) );
		$this->assertSame( 'B', get_option( 'freeman_core_b' ) );
		// Options after the failure are NOT written.
		$this->assertFalse( get_option( 'freeman_core_d', false ) );
		$this->assertFalse( get_option( 'freeman_core_e', false ) );

		// Auto-backup contains pre-import state and is intact.
		$backups = $this->tools->list_backups();
		$this->assertNotEmpty( $backups );
		$this->assertSame( 'before', $backups[0]['options']['freeman_core_pre'] );
	}
}
