<?php
declare(strict_types=1);

use Freeman\Core\Modules\VariationSwatches\Settings_Reader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freeman\Core\Modules\VariationSwatches\Settings_Reader
 */
final class VariationSwatchesSettingsReaderTest extends TestCase {

	private const FLAG_OPT = 'freeman_core_variation_swatches_settings_hub_enabled';
	private const LEGACY   = 'etucart_vs_shop_enabled';
	private const NEW_KEY  = 'freeman_core_variation_swatches_shop_enabled';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_translate_maps_legacy_prefix_to_new(): void {
		$this->assertSame(
			'freeman_core_variation_swatches_pdp_hide_oos',
			Settings_Reader::translate( 'etucart_vs_pdp_hide_oos' )
		);
	}

	public function test_translate_passes_through_unprefixed_keys(): void {
		// Should never happen in practice, but the method must not blindly mangle.
		$this->assertSame( 'unrelated_option', Settings_Reader::translate( 'unrelated_option' ) );
	}

	public function test_flag_off_returns_legacy_directly_ignoring_new_key(): void {
		// Flag OFF (default).
		update_option( self::LEGACY, 'no' );
		update_option( self::NEW_KEY, 'yes' ); // Should be ignored when flag is OFF.

		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_off_falls_back_to_caller_default_when_legacy_unset(): void {
		// Flag OFF, no legacy value either.
		$this->assertSame( 'fallback', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_on_prefers_new_key_when_set(): void {
		update_option( self::FLAG_OPT, 1 );
		update_option( self::LEGACY, 'no' );
		update_option( self::NEW_KEY, 'yes' );

		$this->assertSame( 'yes', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_on_falls_back_to_legacy_when_new_unset(): void {
		update_option( self::FLAG_OPT, 1 );
		update_option( self::LEGACY, 'no' );
		// New key intentionally unset.

		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_on_falls_back_to_caller_default_when_neither_set(): void {
		update_option( self::FLAG_OPT, 1 );
		// Neither legacy nor new is set.

		$this->assertSame( 'fallback', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_on_returns_falsy_legacy_value_not_default(): void {
		// Empty string is a real value the admin saved; must not be confused with "unset."
		update_option( self::FLAG_OPT, 1 );
		update_option( self::LEGACY, '' );

		$this->assertSame( '', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_on_returns_falsy_new_value_not_falling_back_to_legacy(): void {
		// New key explicitly set to '0' (or empty string) must win over legacy.
		update_option( self::FLAG_OPT, 1 );
		update_option( self::LEGACY, 'yes' );
		update_option( self::NEW_KEY, '0' );

		$this->assertSame( '0', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}
}
