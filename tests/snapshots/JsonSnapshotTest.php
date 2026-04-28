<?php
declare(strict_types=1);

require_once __DIR__ . '/SnapshotTestCase.php';
require_once __DIR__ . '/Scrubber.php';

use Freeman\Core\Core\Settings_Tools;
use Freeman\Tests\Snapshots\Scrubber;
use Freeman\Tests\Snapshots\SnapshotTestCase;
use PHPUnit\Framework\TestCase;

final class JsonSnapshotTest extends TestCase {
	use SnapshotTestCase;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_settings_export_envelope_matches_golden(): void {
		// Seed a deterministic options table covering both prefixes the
		// exporter emits, plus an etucart_* key (which the exporter must
		// NOT include — the snapshot proves that).
		update_option( 'freeman_core_modules', array( 'sliders' => true, 'product_feed' => true ) );
		update_option( 'freeman_core_sliders_advanced_controls_enabled', '1' );
		update_option( 'freeman_core_tools_settings_import_enabled', '0' );
		update_option( 'freeman_digital_some_setting', 'value' );
		update_option( 'etucart_legacy_setting', 'should not appear in export' );

		$tools    = new Settings_Tools();
		$envelope = $tools->export_payload();

		$envelope = Scrubber::json_keys(
			$envelope,
			array( 'exported_at', 'site_url' ),
			'<scrubbed>'
		);

		$json = json_encode( $envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$this->assertSnapshotMatches( 'settings_export_envelope.json', $json );
	}
}
