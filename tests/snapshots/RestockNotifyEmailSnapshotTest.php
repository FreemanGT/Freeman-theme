<?php
declare(strict_types=1);

require_once __DIR__ . '/SnapshotTestCase.php';
require_once __DIR__ . '/__fixtures__/wc_product_stub.php';
require_once __DIR__ . '/../../freeman-core/src/Modules/RestockNotify/legacy/helpers.php';

use Freeman\Core\Modules\RestockNotify\Email;
use Freeman\Tests\Snapshots\SnapshotTestCase;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) {
		return $GLOBALS['fr_wc_get_product_return'] ?? new \WC_Product();
	}
}

/**
 * Locks the rendered email HTML for both locales and both kinds. Wave 2.3b
 * fixed the bilingual-email shell — these goldens are the post-fix reference.
 *
 * For the en_US goldens specifically: there is NO pre-2.3b equivalent because
 * legacy `\RSN_Email::build_html()` always rendered Hebrew shell strings
 * regardless of locale. The diff between "what English-locale users got
 * before 1.11.4" vs "what they get from 1.11.4 onward" is exactly the
 * intentional fix.
 *
 * For the he_IL goldens: legacy and modern produce identical Hebrew shell
 * strings (locale data file is a verbatim copy of the legacy literals), so
 * the byte-identity expectation holds across the migration on Hebrew sites.
 */
final class RestockNotifyEmailSnapshotTest extends TestCase {
	use SnapshotTestCase;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                  = array();
		$GLOBALS['fr_hooks']                 = array();
		$GLOBALS['fr_wp_mail_calls']         = array();
		$GLOBALS['fr_wp_mail_return']        = true;
		$GLOBALS['fr_wc_get_product_return'] = new \WC_Product();
		$GLOBALS['fr_blogname']              = 'Acme Shop';
		$GLOBALS['fr_home_url']              = 'https://example.test';
		$GLOBALS['fr_attachment_url']        = '';

		// Deterministic option content so the snapshot has stable text. The
		// values use plain ASCII so the diff between he_IL and en_US goldens
		// surfaces ONLY in the shell strings (greeting / unsubscribe link /
		// suffix / customer-name fallback) — exactly the surface this wave
		// changed.
		update_option( 'rsn_confirm_subject',     'Subscribed: {product_name}' );
		update_option( 'rsn_confirm_heading',     'Heading' );
		update_option( 'rsn_confirm_body',        'Body for {product_name}.' );
		update_option( 'rsn_notify_subject',      '{product_name} is back' );
		update_option( 'rsn_notify_heading',      'Heading' );
		update_option( 'rsn_notify_body',         'Body for {product_name}.' );
		update_option( 'rsn_notify_button_text',  'CTA' );
		update_option( 'rsn_from_name',           'Acme' );
		update_option( 'rsn_from_email',          'shop@example.test' );
	}

	private function subscriber(): object {
		return (object) array(
			'id'                => 7,
			'product_id'        => 42,
			'variation_id'      => 0,
			'customer_name'     => 'Alice',
			'customer_email'    => 'alice@example.test',
			'unsubscribe_token' => 'tok_abcdef0123456789',
			'status'            => 'waiting',
		);
	}

	public function test_confirmation_email_he_il_matches_golden(): void {
		$GLOBALS['fr_locale'] = 'he_IL';

		Email::send_confirmation( $this->subscriber() );

		$this->assertSnapshotMatches(
			'restock_notify_confirmation_he_il.html',
			$GLOBALS['fr_wp_mail_calls'][0]['message']
		);
	}

	public function test_confirmation_email_en_us_matches_golden(): void {
		$GLOBALS['fr_locale'] = 'en_US';

		Email::send_confirmation( $this->subscriber() );

		$this->assertSnapshotMatches(
			'restock_notify_confirmation_en_us.html',
			$GLOBALS['fr_wp_mail_calls'][0]['message']
		);
	}

	public function test_notification_email_he_il_matches_golden(): void {
		$GLOBALS['fr_locale'] = 'he_IL';

		Email::send_notification( $this->subscriber() );

		$this->assertSnapshotMatches(
			'restock_notify_notification_he_il.html',
			$GLOBALS['fr_wp_mail_calls'][0]['message']
		);
	}

	public function test_notification_email_en_us_matches_golden(): void {
		$GLOBALS['fr_locale'] = 'en_US';

		Email::send_notification( $this->subscriber() );

		$this->assertSnapshotMatches(
			'restock_notify_notification_en_us.html',
			$GLOBALS['fr_wp_mail_calls'][0]['message']
		);
	}
}
