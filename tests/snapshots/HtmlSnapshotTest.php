<?php
declare(strict_types=1);

require_once __DIR__ . '/SnapshotTestCase.php';
require_once __DIR__ . '/Scrubber.php';

use Freeman\Tests\Snapshots\Scrubber;
use Freeman\Tests\Snapshots\SnapshotTestCase;
use PHPUnit\Framework\TestCase;

// Stubs only reached by HTML snapshot rendering. Each is guarded so it does
// not collide with bootstrap.php or other tests.
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = '' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return str_replace( array( "\\", "'", '"', "\r", "\n" ), array( '\\\\', "\\'", '\\"', '', '\\n' ), (string) $text );
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() { return 1; }
}
if ( ! function_exists( 'disabled' ) ) {
	function disabled( $disabled, $current = true, $echo = true ) {
		$out = ( $disabled === $current ) ? ' disabled="disabled"' : '';
		if ( $echo ) { echo $out; }
		return $out;
	}
}

final class HtmlSnapshotTest extends TestCase {
	use SnapshotTestCase;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']       = array();
		$GLOBALS['fr_hooks']      = array();
		$GLOBALS['fr_transients'] = array();
	}

	public function test_settings_tools_section_view_matches_golden(): void {
		// Flag OFF (the default): the import form is hidden and the
		// "enable with wp option update" hint is shown. This is the
		// snapshot consumers will see after activating the plugin
		// fresh — and the one that must remain byte-identical when
		// future PRs add unrelated tools.
		ob_start();
		include __DIR__ . '/../../freeman-core/src/Admin/views/settings-tools-section.php';
		$html = (string) ob_get_clean();

		$scrubbed = Scrubber::nonces( $html );

		$this->assertSnapshotMatches( 'settings_tools_section.html', $scrubbed );
	}
}
