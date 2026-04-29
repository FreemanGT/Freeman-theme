<?php
declare(strict_types=1);

require_once __DIR__ . '/SnapshotTestCase.php';
require_once __DIR__ . '/__fixtures__/wc_product_stub.php';
require_once __DIR__ . '/../../freeman-core/src/Modules/RestockNotify/legacy/helpers.php';

use Freeman\Core\Modules\RestockNotify\Frontend;
use Freeman\Tests\Snapshots\SnapshotTestCase;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) {
		return $GLOBALS['fr_wc_get_product_return'] ?? new \WC_Product();
	}
}

/**
 * Locks the rendered form HTML byte-identically per locale. The he_IL
 * goldens are the byte-identity reference vs the legacy class output (since
 * he_IL.php's strings are verbatim copies of the legacy literals); the
 * en_US goldens lock the post-fix English rendering.
 *
 * Variable-product paths are exercised at the Frontend test level (see
 * RestockNotifyFrontendTest); here we focus on the form HTML itself across
 * the simple-OOS path, plus the inline-script payload that variable-OOS
 * products prepend to the form.
 */
final class RestockNotifyFrontendSnapshotTest extends TestCase {
	use SnapshotTestCase;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                 = array();
		$GLOBALS['fr_hooks']                 = array();
		$GLOBALS['fr_wc_get_product_return'] = new \WC_Product();

		// Reset Frontend statics so the inline-CSS-printed-once flag is
		// fresh for every test (otherwise test order changes the goldens).
		$ref = new \ReflectionClass( Frontend::class );
		$rend = $ref->getProperty( 'rendered' );
		$rend->setAccessible( true );
		$rend->setValue( null, array() );
		$css = $ref->getProperty( 'inline_css_printed' );
		$css->setAccessible( true );
		$css->setValue( null, false );

		// Deterministic option content. Plain ASCII so the diff between
		// he_IL and en_US goldens surfaces ONLY in the placeholder strings.
		update_option( 'rsn_form_heading',         'HEADING' );
		update_option( 'rsn_form_description',     'DESCRIPTION.' );
		update_option( 'rsn_form_button_text',     'BUTTON' );
		update_option( 'rsn_form_success_message', 'SUCCESS' );
		update_option( 'rsn_enable_gdpr',          'no' );
		update_option( 'rsn_gdpr_text',            'GDPR' );
	}

	public function test_simple_oos_form_he_il_matches_golden(): void {
		$GLOBALS['fr_locale'] = 'he_IL';

		$frontend = new Frontend();
		$ref      = new \ReflectionMethod( $frontend, 'render_form' );
		$ref->setAccessible( true );
		$html = (string) $ref->invoke( $frontend, 42, 0, false );

		$this->assertSnapshotMatches( 'restock_notify_form_simple_he_il.html', $html );
	}

	public function test_simple_oos_form_en_us_matches_golden(): void {
		$GLOBALS['fr_locale'] = 'en_US';

		$frontend = new Frontend();
		$ref      = new \ReflectionMethod( $frontend, 'render_form' );
		$ref->setAccessible( true );
		$html = (string) $ref->invoke( $frontend, 42, 0, false );

		$this->assertSnapshotMatches( 'restock_notify_form_simple_en_us.html', $html );
	}

	public function test_variable_form_he_il_matches_golden(): void {
		// Variable-product mode = wrapper class includes `rsn-hidden`. The
		// rsn_variations <script> block is prepended by maybe_render(), not
		// render_form() itself — that's tested at the Frontend test level.
		$GLOBALS['fr_locale'] = 'he_IL';

		$frontend = new Frontend();
		$ref      = new \ReflectionMethod( $frontend, 'render_form' );
		$ref->setAccessible( true );
		$html = (string) $ref->invoke( $frontend, 42, 0, true );

		$this->assertSnapshotMatches( 'restock_notify_form_variable_he_il.html', $html );
	}
}
