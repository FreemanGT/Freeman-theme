<?php
declare(strict_types=1);

// Email needs the legacy `rsn_get_option()` shim to read configurable options.
// Loading helpers.php is side-effect-free (just defines two functions if they
// aren't already defined).
require_once __DIR__ . '/../freeman-core/src/Modules/RestockNotify/legacy/helpers.php';

// Reuse the WC_Product shim from the ProductFeed snapshot fixture so a single
// definition exists across the suite (matches Wave 1.1b's pattern). The shim
// already provides get_name, get_permalink, get_image_id, get_variation_attributes,
// and the rest the Email class touches.
require_once __DIR__ . '/snapshots/__fixtures__/wc_product_stub.php';

use Freeman\Core\Modules\RestockNotify\Email;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) {
		return $GLOBALS['fr_wc_get_product_return'] ?? new \WC_Product();
	}
}

/**
 * @covers \Freeman\Core\Modules\RestockNotify\Email
 */
final class RestockNotifyEmailTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']             = array();
		$GLOBALS['fr_hooks']            = array();
		$GLOBALS['fr_locale']           = 'en_US';
		$GLOBALS['fr_wp_mail_calls']    = array();
		$GLOBALS['fr_wp_mail_return']   = true;
		$GLOBALS['fr_wc_get_product_return'] = new \WC_Product();
		$GLOBALS['fr_blogname']         = 'Acme Shop';

		// Pre-seed the email-content options so rsn_get_option() doesn't fall
		// through to seeded defaults (and also doesn't trigger spurious
		// update_option calls from the legacy helper's side-effect path).
		update_option( 'rsn_confirm_subject',     'Subscribed: {product_name}' );
		update_option( 'rsn_confirm_heading',     "We'll let you know" );
		update_option( 'rsn_confirm_body',        'Hi {customer_name}, you are subscribed for {product_name}.' );
		update_option( 'rsn_notify_subject',      '{product_name} is back in stock' );
		update_option( 'rsn_notify_heading',      "It's back!" );
		update_option( 'rsn_notify_body',         '<strong>{product_name}</strong> is back!' );
		update_option( 'rsn_notify_button_text',  'Buy now' );
		update_option( 'rsn_from_name',           'Acme' );
		update_option( 'rsn_from_email',          'shop@example.test' );
		update_option( 'admin_email',             'admin@example.test' );
	}

	private function subscriber( array $overrides = array() ): object {
		return (object) array_merge(
			array(
				'id'                  => 7,
				'product_id'          => 42,
				'variation_id'        => 0,
				'customer_name'       => 'Alice',
				'customer_email'      => 'alice@example.test',
				'unsubscribe_token'   => 'abcdef0123',
				'status'              => 'waiting',
			),
			$overrides
		);
	}

	public function test_send_confirmation_uses_en_us_shell_strings_when_locale_is_english(): void {
		$GLOBALS['fr_locale'] = 'en_US';

		Email::send_confirmation( $this->subscriber() );

		$msg = $GLOBALS['fr_wp_mail_calls'][0]['message'];
		$this->assertStringContainsString( 'Hi Alice,', $msg );
		$this->assertStringContainsString( '>Unsubscribe<', $msg );
		$this->assertStringContainsString( 'for this product.', $msg );
		// And explicitly NOT the Hebrew shell strings.
		$this->assertStringNotContainsString( 'היי', $msg );
		$this->assertStringNotContainsString( 'הסרה', $msg );
	}

	public function test_send_confirmation_uses_he_il_shell_strings_when_locale_is_hebrew(): void {
		$GLOBALS['fr_locale'] = 'he_IL';

		Email::send_confirmation( $this->subscriber() );

		$msg = $GLOBALS['fr_wp_mail_calls'][0]['message'];
		$this->assertStringContainsString( 'היי Alice,', $msg );
		$this->assertStringContainsString( 'הסרה מרשימת התפוצה', $msg );
		$this->assertStringContainsString( 'עבור מוצר זה.', $msg );
	}

	public function test_send_notification_uses_locale_aware_shell_strings(): void {
		$GLOBALS['fr_locale'] = 'en_US';

		Email::send_notification( $this->subscriber() );

		$msg = $GLOBALS['fr_wp_mail_calls'][0]['message'];
		$this->assertStringContainsString( 'Hi Alice,', $msg );
		// Notification emails get the CTA button.
		$this->assertStringContainsString( 'Buy now', $msg );
	}

	public function test_customer_name_falls_back_to_locale_when_subscriber_has_no_name(): void {
		$GLOBALS['fr_locale'] = 'en_US';

		Email::send_confirmation( $this->subscriber( array( 'customer_name' => '' ) ) );

		$msg = $GLOBALS['fr_wp_mail_calls'][0]['message'];
		$this->assertStringContainsString( 'Hi Customer,', $msg );
	}

	public function test_email_args_filter_can_mutate_args_dictionary(): void {
		add_filter(
			'freeman_core/restock_notify/email_args',
			static function ( $args ) {
				$args['heading'] = 'INJECTED HEADING';
				return $args;
			}
		);

		Email::send_confirmation( $this->subscriber() );

		$msg = $GLOBALS['fr_wp_mail_calls'][0]['message'];
		$this->assertStringContainsString( 'INJECTED HEADING', $msg );
	}

	public function test_email_args_filter_receives_subscriber_and_kind(): void {
		$captured = array();
		add_filter(
			'freeman_core/restock_notify/email_args',
			static function ( $args, $subscriber, $kind ) use ( &$captured ) {
				$captured[] = array( 'kind' => $kind, 'sub_id' => $subscriber->id );
				return $args;
			},
			10,
			3
		);

		Email::send_confirmation( $this->subscriber() );
		Email::send_notification( $this->subscriber( array( 'id' => 9 ) ) );

		$this->assertSame( 'confirmation', $captured[0]['kind'] );
		$this->assertSame( 7, $captured[0]['sub_id'] );
		$this->assertSame( 'notification', $captured[1]['kind'] );
		$this->assertSame( 9, $captured[1]['sub_id'] );
	}

	public function test_before_send_action_fires_with_to_subject_html_subscriber(): void {
		$captured = null;
		add_action(
			'freeman_core/restock_notify/before_send',
			static function ( $to, $subject, $html, $subscriber ) use ( &$captured ) {
				$captured = compact( 'to', 'subject', 'html', 'subscriber' );
			},
			10,
			4
		);

		Email::send_confirmation( $this->subscriber() );

		$this->assertNotNull( $captured );
		$this->assertSame( 'alice@example.test', $captured['to'] );
		$this->assertStringContainsString( 'Test Product', $captured['subject'] );
		$this->assertStringContainsString( '<html', $captured['html'] );
		$this->assertSame( 7, $captured['subscriber']->id );
	}

	public function test_send_falls_back_to_admin_email_when_from_email_is_invalid(): void {
		update_option( 'rsn_from_email', 'not-an-email' );

		Email::send_confirmation( $this->subscriber() );

		$headers = $GLOBALS['fr_wp_mail_calls'][0]['headers'];
		$from_line = '';
		foreach ( (array) $headers as $h ) {
			if ( false !== strpos( $h, 'From:' ) ) {
				$from_line = $h;
				break;
			}
		}
		$this->assertStringContainsString( 'admin@example.test', $from_line );
	}
}
