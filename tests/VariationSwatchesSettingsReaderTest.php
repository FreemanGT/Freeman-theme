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

		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_on_normalizes_settings_hub_checkbox_values_to_legacy_strings(): void {
		update_option( self::FLAG_OPT, 1 );

		update_option( self::NEW_KEY, 1 );
		$this->assertSame( 'yes', Settings_Reader::get( self::LEGACY, 'fallback' ) );

		update_option( self::NEW_KEY, 0 );
		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_on_normalizes_settings_hub_checkbox_strings_to_legacy_strings(): void {
		update_option( self::FLAG_OPT, 1 );

		update_option( self::NEW_KEY, 'yes' );
		$this->assertSame( 'yes', Settings_Reader::get( self::LEGACY, 'fallback' ) );

		update_option( self::NEW_KEY, 'no' );
		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_flag_on_normalizes_comma_separated_excluded_categories(): void {
		update_option( self::FLAG_OPT, 1 );
		update_option( 'freeman_core_variation_swatches_shop_excluded_categories', '12, 34, invalid, 0, 56' );

		$this->assertSame(
			array( 12, 34, 56 ),
			Settings_Reader::get( 'etucart_vs_shop_excluded_categories', array() )
		);
	}

	public function test_flag_on_preserves_array_excluded_categories_from_migration(): void {
		update_option( self::FLAG_OPT, 1 );
		update_option( 'freeman_core_variation_swatches_shop_excluded_categories', array( 12, 34, 56 ) );

		$this->assertSame(
			array( 12, 34, 56 ),
			Settings_Reader::get( 'etucart_vs_shop_excluded_categories', array() )
		);
	}

	/**
	 * Drift-detector: every `'checkbox'`-typed entry in the schema must have
	 * its legacy key in Settings_Reader::CHECKBOX_KEYS. Catches the omission
	 * case (someone adds setting #15 with type `checkbox` and forgets to
	 * extend the const list) at CI time instead of silently re-shipping the
	 * 1.11.21 storage-shape bug.
	 */
	public function test_every_checkbox_in_settings_schema_is_in_checkbox_keys_list(): void {
		update_option( self::FLAG_OPT, 1 ); // Module::settings_schema() returns [] when flag is OFF.

		$schema = ( new \Freeman\Core\Modules\VariationSwatches\Module() )->settings_schema();

		$ref           = new ReflectionClass( Settings_Reader::class );
		$checkbox_keys = $ref->getConstant( 'CHECKBOX_KEYS' );
		$this->assertIsArray( $checkbox_keys, 'CHECKBOX_KEYS must be an array constant.' );

		$missing = array();
		foreach ( $schema as $suffix => $def ) {
			if ( ( $def['type'] ?? '' ) !== 'checkbox' ) {
				continue;
			}
			$legacy_key = 'etucart_vs_' . $suffix;
			if ( ! in_array( $legacy_key, $checkbox_keys, true ) ) {
				$missing[] = $legacy_key;
			}
		}

		$this->assertSame(
			array(),
			$missing,
			"Schema has checkbox-typed entries whose legacy keys are missing from Settings_Reader::CHECKBOX_KEYS — flag-ON sites would read these as raw 1/0 integers instead of 'yes'/'no' strings, breaking Etucart_VS_Settings::bool(). Add the missing keys to the const list. Missing: " . implode( ', ', $missing )
		);
	}

	/**
	 * Reverse drift-detector: every key in CHECKBOX_KEYS must correspond to a
	 * `'checkbox'`-typed entry in the schema. Catches stale entries left
	 * behind when a setting is removed.
	 */
	public function test_every_checkbox_keys_entry_corresponds_to_a_schema_checkbox(): void {
		update_option( self::FLAG_OPT, 1 );

		$schema = ( new \Freeman\Core\Modules\VariationSwatches\Module() )->settings_schema();

		$ref           = new ReflectionClass( Settings_Reader::class );
		$checkbox_keys = $ref->getConstant( 'CHECKBOX_KEYS' );

		$schema_checkbox_legacy_keys = array();
		foreach ( $schema as $suffix => $def ) {
			if ( ( $def['type'] ?? '' ) === 'checkbox' ) {
				$schema_checkbox_legacy_keys[] = 'etucart_vs_' . $suffix;
			}
		}

		$stale = array();
		foreach ( $checkbox_keys as $legacy_key ) {
			if ( ! in_array( $legacy_key, $schema_checkbox_legacy_keys, true ) ) {
				$stale[] = $legacy_key;
			}
		}

		$this->assertSame(
			array(),
			$stale,
			'CHECKBOX_KEYS contains entries that no longer match a checkbox-typed schema field. Remove them. Stale: ' . implode( ', ', $stale )
		);
	}
}
