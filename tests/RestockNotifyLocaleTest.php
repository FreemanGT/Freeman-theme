<?php
declare(strict_types=1);

use Freeman\Core\Modules\RestockNotify\Module;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freeman\Core\Modules\RestockNotify\Module
 */
final class RestockNotifyLocaleTest extends TestCase {

	/** The 18 `rsn_*` option keys seeded by `Module::seed_locale_defaults()`. */
	private const REQUIRED_KEYS = array(
		'auto_inject', 'form_heading', 'form_description', 'form_button_text',
		'form_success_message', 'form_duplicate_message', 'enable_confirmation',
		'enable_gdpr', 'gdpr_text', 'confirm_subject', 'confirm_heading',
		'confirm_body', 'notify_subject', 'notify_heading', 'notify_body',
		'notify_button_text', 'from_name', 'from_email',
	);

	/**
	 * Email-shell keys added in Wave 2.3b (1.11.4). Consumed directly by the
	 * modern `Email` class; NOT seeded into `rsn_*` options.
	 */
	private const SHELL_KEYS = array(
		'shell_customer_name_fallback',
		'shell_greeting',
		'shell_unsubscribe_link_text',
		'shell_unsubscribe_link_suffix',
	);

	/**
	 * Frontend keys added in Wave 2.3c (1.11.5). Consumed directly by the
	 * modern `Frontend` class (`js_*` end up in the wp_localize_script
	 * payload, `form_placeholder_*` end up as inline form input
	 * placeholders). NOT seeded into `rsn_*` options.
	 */
	private const FRONTEND_KEYS = array(
		'js_invalid_email',
		'js_consent_missing',
		'js_product_missing',
		'js_script_missing',
		'js_generic_error',
		'js_network_error',
		'form_placeholder_name',
		'form_placeholder_email',
	);

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']   = array();
		$GLOBALS['fr_hooks']  = array();
		$GLOBALS['fr_locale'] = 'en_US';
	}

	public function test_defaults_returns_en_us_strings_when_locale_is_english(): void {
		$d = Module::defaults( 'en_US' );
		$this->assertSame( 'Notify me when back in stock', $d['form_heading'] );
		$this->assertSame( 'Subscribe', $d['form_button_text'] );
		$this->assertSame( "It's back!", $d['notify_heading'] );
	}

	public function test_defaults_returns_he_il_strings_when_locale_is_hebrew(): void {
		$d = Module::defaults( 'he_IL' );
		$this->assertSame( 'עדכנו אותי כשיחזור למלאי', $d['form_heading'] );
		$this->assertSame( 'הרשמה לעדכון', $d['form_button_text'] );
		$this->assertSame( 'המוצר חזר!', $d['notify_heading'] );
	}

	public function test_defaults_falls_back_to_en_us_for_unknown_locales(): void {
		$en = Module::defaults( 'en_US' );
		$fr = Module::defaults( 'fr_FR' );
		$de = Module::defaults( 'de_DE' );
		$this->assertSame( $en, $fr );
		$this->assertSame( $en, $de );
	}

	public function test_defaults_method_return_shape_is_stable(): void {
		$en       = Module::defaults( 'en_US' );
		$he       = Module::defaults( 'he_IL' );
		$expected = array_merge( self::REQUIRED_KEYS, self::SHELL_KEYS, self::FRONTEND_KEYS );
		$this->assertSame( $expected, array_keys( $en ) );
		$this->assertSame( $expected, array_keys( $he ) );
	}

	public function test_shell_keys_present_in_both_locales(): void {
		$en = Module::defaults( 'en_US' );
		$he = Module::defaults( 'he_IL' );
		foreach ( self::SHELL_KEYS as $k ) {
			$this->assertNotEmpty( $en[ $k ], "en_US.{$k} must be set" );
			$this->assertNotEmpty( $he[ $k ], "he_IL.{$k} must be set" );
		}
	}

	public function test_frontend_keys_present_in_both_locales(): void {
		$en = Module::defaults( 'en_US' );
		$he = Module::defaults( 'he_IL' );
		foreach ( self::FRONTEND_KEYS as $k ) {
			$this->assertNotEmpty( $en[ $k ], "en_US.{$k} must be set" );
			$this->assertNotEmpty( $he[ $k ], "he_IL.{$k} must be set" );
		}
	}

	public function test_seed_locale_defaults_skips_shell_and_frontend_keys(): void {
		$GLOBALS['fr_locale'] = 'en_US';

		( new Module() )->seed_locale_defaults();

		// Neither shell nor frontend keys may be seeded into the options
		// table — they're read directly from the locale files at call time.
		foreach ( array_merge( self::SHELL_KEYS, self::FRONTEND_KEYS ) as $k ) {
			$this->assertFalse(
				get_option( 'rsn_' . $k, false ),
				"rsn_{$k} must NOT be seeded as an option"
			);
		}
		// Sanity: option keys still ARE seeded.
		$this->assertNotFalse( get_option( 'rsn_form_heading', false ) );
	}

	public function test_seed_locale_defaults_seeds_english_when_locale_is_english(): void {
		$GLOBALS['fr_locale'] = 'en_US';

		( new Module() )->seed_locale_defaults();

		$this->assertSame( 'Notify me when back in stock', get_option( 'rsn_form_heading' ) );
		$this->assertSame( 'Subscribe', get_option( 'rsn_form_button_text' ) );
		// All 18 keys populated.
		foreach ( self::REQUIRED_KEYS as $k ) {
			$this->assertNotFalse( get_option( 'rsn_' . $k, false ), "rsn_{$k} should be seeded" );
		}
	}

	public function test_seed_locale_defaults_seeds_hebrew_when_locale_is_hebrew(): void {
		$GLOBALS['fr_locale'] = 'he_IL';

		( new Module() )->seed_locale_defaults();

		$this->assertSame( 'עדכנו אותי כשיחזור למלאי', get_option( 'rsn_form_heading' ) );
		$this->assertSame( 'הרשמה לעדכון', get_option( 'rsn_form_button_text' ) );
	}

	public function test_seed_locale_defaults_does_not_overwrite_existing_values(): void {
		// Pre-set 5 of the 18 keys with sentinel values, simulating a Hebrew
		// install that activated under pre-1.11.2 hardcoded defaults.
		$preset = array(
			'form_heading'        => 'CUSTOM existing value',
			'form_button_text'    => 'CUSTOM button',
			'notify_subject'      => 'CUSTOM subject',
			'notify_heading'      => 'CUSTOM heading',
			'enable_confirmation' => 'no',
		);
		foreach ( $preset as $k => $v ) {
			update_option( 'rsn_' . $k, $v );
		}
		$GLOBALS['fr_locale'] = 'en_US';

		( new Module() )->seed_locale_defaults();

		// Preset values must be preserved.
		foreach ( $preset as $k => $v ) {
			$this->assertSame( $v, get_option( 'rsn_' . $k ), "rsn_{$k} must not be overwritten" );
		}
		// Non-preset keys must still be seeded with English defaults.
		$this->assertSame( "You're on the waiting list!", get_option( 'rsn_confirm_subject' ) );
		$this->assertSame( "We'll let you know", get_option( 'rsn_confirm_heading' ) );
		$this->assertNotFalse( get_option( 'rsn_form_description', false ), 'Non-preset key must still be seeded' );
	}

	public function test_en_us_strings_are_pure_ascii(): void {
		$en = Module::defaults( 'en_US' );
		foreach ( $en as $key => $value ) {
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}
			$this->assertSame(
				$value,
				mb_convert_encoding( $value, 'ASCII', 'UTF-8' ),
				"en_US.{$key} must be pure ASCII (no Hebrew leakage); got: " . $value
			);
		}
	}

	public function test_he_il_strings_contain_non_ascii_characters(): void {
		$he = Module::defaults( 'he_IL' );
		// Keys whose values are intentionally English/empty (toggles, blank).
		$skip_keys = array( 'auto_inject', 'enable_confirmation', 'enable_gdpr', 'from_name', 'from_email' );
		foreach ( $he as $key => $value ) {
			if ( in_array( $key, $skip_keys, true ) || '' === $value ) {
				continue;
			}
			$this->assertNotSame(
				$value,
				mb_convert_encoding( $value, 'ASCII', 'UTF-8' ),
				"he_IL.{$key} must contain non-ASCII characters (no English leakage); got: " . $value
			);
		}
	}
}
