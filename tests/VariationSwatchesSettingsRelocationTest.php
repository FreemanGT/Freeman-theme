<?php
declare(strict_types=1);

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Modules\VariationSwatches\Module;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/freeman-core/src/Modules/VariationSwatches/legacy/includes/class-settings.php';

/**
 * Wave 2.2 / 4g — VariationSwatches settings moved to the Settings_Hub page,
 * the legacy WooCommerce → Products section retired to a "moved" notice, the
 * settings_hub flag retired, and a re-sync migration brings the new keys
 * current on flag-OFF sites.
 *
 * @covers \Freeman\Core\Modules\VariationSwatches\Module
 * @covers \Freeman\Core\Core\Migrations
 * @covers \Freeman\Core\Core\Feature_Flags
 * @covers \Etucart_VS_Settings
 */
final class VariationSwatchesSettingsRelocationTest extends TestCase {

	private const WC_SECTION = 'etucart_vs_shop_pick';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	private function migrations(): \Freeman\Core\Core\Migrations {
		return new \Freeman\Core\Core\Migrations( new \Freeman\Core\Core\Module_Registry() );
	}

	private function invoke_private( object $obj, string $method, array $args = array() ) {
		$ref = ( new ReflectionClass( $obj ) )->getMethod( $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $obj, $args );
	}

	/* ---- settings_schema is now unconditional ----------------------------- */

	public function test_settings_schema_returns_fourteen_keys_with_no_flag_set(): void {
		$schema = ( new Module() )->settings_schema();
		$this->assertCount( 14, $schema );
	}

	public function test_settings_schema_key_set_is_stable(): void {
		$expected = array(
			'shop_enabled', 'shop_max_visible', 'shop_show_price', 'shop_apply_shop',
			'shop_apply_category', 'shop_apply_tag', 'shop_apply_search', 'shop_apply_related',
			'shop_excluded_categories', 'pdp_hide_oos', 'shop_hide_oos', 'shop_no_preselect',
			'shop_hide_attr_labels', 'shop_hide_selected',
		);
		$this->assertSame( $expected, array_keys( ( new Module() )->settings_schema() ) );
	}

	public function test_settings_schema_each_field_has_label_type_and_default(): void {
		foreach ( ( new Module() )->settings_schema() as $key => $def ) {
			$this->assertArrayHasKey( 'label', $def, "Field $key missing label" );
			$this->assertArrayHasKey( 'type', $def, "Field $key missing type" );
			$this->assertArrayHasKey( 'default', $def, "Field $key missing default" );
		}
	}

	public function test_settings_schema_max_visible_default_matches_legacy_helper(): void {
		// Etucart_VS_Settings::max_visible() hardcodes a default of 5; the schema must agree.
		$schema = ( new Module() )->settings_schema();
		$this->assertSame( 5, $schema['shop_max_visible']['default'] );
	}

	/* ---- legacy WooCommerce → Products section is now a "moved" notice ----- */

	public function test_wc_section_renders_only_a_moved_notice(): void {
		$rows = ( new \Etucart_VS_Settings() )->add_settings( array(), self::WC_SECTION );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'title', $rows[0]['type'] );
		$this->assertSame( 'sectionend', $rows[1]['type'] );

		$ids = array_column( $rows, 'id' );
		$this->assertNotContains( 'etucart_vs_shop_enabled', $ids );
		$this->assertNotContains( 'etucart_vs_shop_max_visible', $ids );
	}

	public function test_wc_section_left_untouched_for_other_sections(): void {
		$incoming = array( array( 'id' => 'something' ) );
		$this->assertSame( $incoming, ( new \Etucart_VS_Settings() )->add_settings( $incoming, 'general' ) );
	}

	public function test_wc_section_is_still_registered_so_the_url_resolves(): void {
		$sections = ( new \Etucart_VS_Settings() )->add_section( array() );
		$this->assertArrayHasKey( self::WC_SECTION, $sections );
	}

	/* ---- settings_hub flag is retired ------------------------------------- */

	public function test_settings_hub_flag_removed_from_registry(): void {
		foreach ( Feature_Flags::registry() as $flag ) {
			$this->assertFalse(
				'variation_swatches' === $flag['module'] && 'settings_hub' === $flag['feature'],
				'The retired settings_hub flag must not appear in Feature_Flags::registry().'
			);
		}
	}

	/* ---- re-sync migration (the bare method) ------------------------------ */

	public function test_resync_overwrites_new_key_with_current_legacy_value(): void {
		update_option( 'etucart_vs_shop_enabled', 'no' );
		update_option( 'freeman_core_variation_swatches_shop_enabled', 'yes' ); // stale 1.11.21 snapshot

		$this->invoke_private( $this->migrations(), 'resync_variation_swatches_settings_from_legacy' );

		$this->assertSame( 'no', get_option( 'freeman_core_variation_swatches_shop_enabled' ) );
	}

	public function test_resync_skips_legacy_keys_that_were_never_set(): void {
		update_option( 'freeman_core_variation_swatches_shop_show_price', 'yes' );

		$this->invoke_private( $this->migrations(), 'resync_variation_swatches_settings_from_legacy' );

		$this->assertSame( 'yes', get_option( 'freeman_core_variation_swatches_shop_show_price' ) );
	}

	public function test_resync_never_deletes_legacy_keys(): void {
		update_option( 'etucart_vs_shop_max_visible', 7 );

		$this->invoke_private( $this->migrations(), 'resync_variation_swatches_settings_from_legacy' );

		$this->assertSame( 7, get_option( 'etucart_vs_shop_max_visible' ) );
		$this->assertSame( 7, get_option( 'freeman_core_variation_swatches_shop_max_visible' ) );
	}

	/* ---- re-sync migration (version + flag gating) ------------------------ */

	public function test_resync_runs_on_pre_4g_upgrade_when_flag_off(): void {
		update_option( 'etucart_vs_shop_enabled', 'no' );
		update_option( 'freeman_core_variation_swatches_shop_enabled', 'yes' );
		// settings_hub flag option intentionally unset → OFF.

		$this->invoke_private( $this->migrations(), 'run_one_shot_migrations', array( '1.11.40' ) );

		$this->assertSame( 'no', get_option( 'freeman_core_variation_swatches_shop_enabled' ) );
	}

	public function test_resync_skipped_when_flag_was_on(): void {
		update_option( 'etucart_vs_shop_enabled', 'no' );
		update_option( 'freeman_core_variation_swatches_shop_enabled', 'yes' );
		update_option( 'freeman_core_variation_swatches_settings_hub_enabled', 1 );

		$this->invoke_private( $this->migrations(), 'run_one_shot_migrations', array( '1.11.40' ) );

		$this->assertSame( 'yes', get_option( 'freeman_core_variation_swatches_shop_enabled' ) );
	}

	public function test_resync_skipped_on_4g_or_later_install(): void {
		update_option( 'etucart_vs_shop_enabled', 'no' );
		update_option( 'freeman_core_variation_swatches_shop_enabled', 'yes' );

		$this->invoke_private( $this->migrations(), 'run_one_shot_migrations', array( '1.11.45' ) );

		$this->assertSame( 'yes', get_option( 'freeman_core_variation_swatches_shop_enabled' ) );
	}
}
