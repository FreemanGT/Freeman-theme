<?php
declare(strict_types=1);

use Freeman\Core\Modules\VariationSwatches\Settings_Reader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freeman\Core\Modules\VariationSwatches\Settings_Reader
 */
final class VariationSwatchesSettingsReaderTest extends TestCase {

	private const LEGACY  = 'etucart_vs_shop_enabled';
	private const NEW_KEY = 'freeman_core_variation_swatches_shop_enabled';

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

	public function test_prefers_new_key_when_set(): void {
		update_option( self::LEGACY, 'no' );
		update_option( self::NEW_KEY, 'yes' );

		$this->assertSame( 'yes', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_falls_back_to_legacy_when_new_unset(): void {
		update_option( self::LEGACY, 'no' );
		// New key intentionally unset.

		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_falls_back_to_caller_default_when_neither_set(): void {
		$this->assertSame( 'fallback', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_returns_falsy_legacy_value_not_default(): void {
		// Empty string is a real value the admin saved; must not be confused with "unset."
		update_option( self::LEGACY, '' );

		$this->assertSame( '', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_falsy_new_value_wins_over_legacy(): void {
		// New key explicitly set to '0' must win over a truthy legacy value.
		update_option( self::LEGACY, 'yes' );
		update_option( self::NEW_KEY, '0' );

		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_normalizes_checkbox_int_values_to_legacy_strings(): void {
		update_option( self::NEW_KEY, 1 );
		$this->assertSame( 'yes', Settings_Reader::get( self::LEGACY, 'fallback' ) );

		update_option( self::NEW_KEY, 0 );
		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_normalizes_checkbox_string_values_to_legacy_strings(): void {
		update_option( self::NEW_KEY, 'yes' );
		$this->assertSame( 'yes', Settings_Reader::get( self::LEGACY, 'fallback' ) );

		update_option( self::NEW_KEY, 'no' );
		$this->assertSame( 'no', Settings_Reader::get( self::LEGACY, 'fallback' ) );
	}

	public function test_normalizes_comma_separated_excluded_categories(): void {
		update_option( 'freeman_core_variation_swatches_shop_excluded_categories', '12, 34, invalid, 0, 56' );

		$this->assertSame(
			array( 12, 34, 56 ),
			Settings_Reader::get( 'etucart_vs_shop_excluded_categories', array() )
		);
	}

	public function test_preserves_array_excluded_categories(): void {
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
			'Schema has checkbox-typed entries whose legacy keys are missing from Settings_Reader::CHECKBOX_KEYS — reads would surface as raw 1/0 ints instead of "yes"/"no". Missing: ' . implode( ', ', $missing )
		);
	}

	/**
	 * Reverse drift-detector: every key in CHECKBOX_KEYS must correspond to a
	 * `'checkbox'`-typed entry in the schema. Catches stale entries left
	 * behind when a setting is removed.
	 */
	public function test_every_checkbox_keys_entry_corresponds_to_a_schema_checkbox(): void {
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
			'CHECKBOX_KEYS contains entries that no longer match a checkbox-typed schema field. Stale: ' . implode( ', ', $stale )
		);
	}
}
