<?php
declare(strict_types=1);

use Freeman\Core\Modules\VariationSwatches\Module;
use PHPUnit\Framework\TestCase;

/**
 * Asserts the flag-OFF / flag-ON contract for VariationSwatches::settings_schema().
 *
 * Flag OFF (default) — the schema is empty so Settings_Hub does not surface
 * a Freeman → Variation Swatches admin page (admins continue using the legacy
 * WooCommerce Settings → Products tab).
 *
 * Flag ON — the schema contains 14 entries matching the 14 etucart_vs_*
 * options, with stable keys / labels / defaults.
 *
 * @covers \Freeman\Core\Modules\VariationSwatches\Module::settings_schema
 */
final class VariationSwatchesSettingsHubSnapshotTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_flag_off_schema_is_empty(): void {
		// Flag default OFF.
		$schema = ( new Module() )->settings_schema();
		$this->assertSame( array(), $schema, 'Flag OFF must produce empty schema so the admin page disappears.' );
	}

	public function test_flag_on_schema_has_fourteen_entries(): void {
		update_option( 'freeman_core_variation_swatches_settings_hub_enabled', 1 );

		$schema = ( new Module() )->settings_schema();

		$this->assertCount( 14, $schema, 'Flag ON must surface all 14 etucart_vs_* settings.' );
	}

	public function test_flag_on_schema_keys_match_etucart_suffixes(): void {
		update_option( 'freeman_core_variation_swatches_settings_hub_enabled', 1 );

		$expected = array(
			'shop_enabled',
			'shop_max_visible',
			'shop_show_price',
			'shop_apply_shop',
			'shop_apply_category',
			'shop_apply_tag',
			'shop_apply_search',
			'shop_apply_related',
			'shop_excluded_categories',
			'pdp_hide_oos',
			'shop_hide_oos',
			'shop_no_preselect',
			'shop_hide_attr_labels',
			'shop_hide_selected',
		);

		$this->assertSame( $expected, array_keys( ( new Module() )->settings_schema() ) );
	}

	public function test_flag_on_each_field_has_label_type_and_default(): void {
		update_option( 'freeman_core_variation_swatches_settings_hub_enabled', 1 );

		foreach ( ( new Module() )->settings_schema() as $key => $def ) {
			$this->assertArrayHasKey( 'label', $def, "Field $key missing label" );
			$this->assertArrayHasKey( 'type', $def, "Field $key missing type" );
			$this->assertArrayHasKey( 'default', $def, "Field $key missing default" );
		}
	}

	public function test_flag_on_max_visible_default_matches_legacy(): void {
		// The legacy Etucart_VS_Settings::max_visible() helper hardcodes a default of 5.
		// Schema must agree so the new admin page seeds the same value.
		update_option( 'freeman_core_variation_swatches_settings_hub_enabled', 1 );

		$schema = ( new Module() )->settings_schema();
		$this->assertSame( 5, $schema['shop_max_visible']['default'] );
	}
}
