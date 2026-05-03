<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Exercises the 1.11.21 one-shot migration block in Core\Migrations that
 * copies etucart_vs_* settings into the freeman_core_variation_swatches_*
 * namespace. Reaches into the private method via reflection so we can test
 * the migration logic in isolation from the version-gate machinery.
 *
 * @covers \Freeman\Core\Core\Migrations
 */
final class VariationSwatchesSettingsMigrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	private function run_migration(): void {
		$registry   = new \Freeman\Core\Core\Module_Registry();
		$migrations = new \Freeman\Core\Core\Migrations( $registry );
		$method     = ( new ReflectionClass( $migrations ) )->getMethod( 'migrate_variation_swatches_settings_to_hub' );
		$method->setAccessible( true );
		$method->invoke( $migrations );
	}

	public function test_copies_legacy_value_when_new_key_is_unset(): void {
		update_option( 'etucart_vs_shop_enabled', 'yes' );

		$this->run_migration();

		$this->assertSame( 'yes', get_option( 'freeman_core_variation_swatches_shop_enabled' ) );
	}

	public function test_does_not_overwrite_an_already_set_new_key(): void {
		update_option( 'etucart_vs_pdp_hide_oos', 'yes' );
		update_option( 'freeman_core_variation_swatches_pdp_hide_oos', 'no' ); // Pre-existing new value.

		$this->run_migration();

		$this->assertSame( 'no', get_option( 'freeman_core_variation_swatches_pdp_hide_oos' ) );
	}

	public function test_does_not_create_a_new_key_when_legacy_is_unset(): void {
		// Neither key exists for shop_show_price.

		$this->run_migration();

		$this->assertFalse( get_option( 'freeman_core_variation_swatches_shop_show_price', false ) );
	}

	public function test_never_deletes_legacy_keys(): void {
		update_option( 'etucart_vs_shop_max_visible', 7 );

		$this->run_migration();

		$this->assertSame( 7, get_option( 'etucart_vs_shop_max_visible' ) );
		$this->assertSame( 7, get_option( 'freeman_core_variation_swatches_shop_max_visible' ) );
	}

	public function test_idempotent_on_repeated_runs(): void {
		update_option( 'etucart_vs_shop_apply_shop', 'yes' );

		$this->run_migration();
		// Admin then explicitly changes the new key away from the legacy value.
		update_option( 'freeman_core_variation_swatches_shop_apply_shop', 'no' );
		$this->run_migration();

		$this->assertSame( 'no', get_option( 'freeman_core_variation_swatches_shop_apply_shop' ) );
	}

	public function test_copies_array_valued_legacy_keys(): void {
		// shop_excluded_categories stores an array in WC's settings pipeline.
		update_option( 'etucart_vs_shop_excluded_categories', array( 12, 34, 56 ) );

		$this->run_migration();

		$this->assertSame(
			array( 12, 34, 56 ),
			get_option( 'freeman_core_variation_swatches_shop_excluded_categories' )
		);
	}

	public function test_copies_all_fourteen_pairs(): void {
		$pairs = array(
			'etucart_vs_shop_enabled'             => 'yes',
			'etucart_vs_shop_max_visible'         => 5,
			'etucart_vs_shop_show_price'          => 'no',
			'etucart_vs_shop_apply_shop'          => 'yes',
			'etucart_vs_shop_apply_category'      => 'yes',
			'etucart_vs_shop_apply_tag'           => 'no',
			'etucart_vs_shop_apply_search'        => 'yes',
			'etucart_vs_shop_apply_related'       => 'no',
			'etucart_vs_shop_excluded_categories' => array( 1, 2 ),
			'etucart_vs_pdp_hide_oos'             => 'yes',
			'etucart_vs_shop_hide_oos'            => 'no',
			'etucart_vs_shop_no_preselect'        => 'yes',
			'etucart_vs_shop_hide_attr_labels'    => 'no',
			'etucart_vs_shop_hide_selected'       => 'yes',
		);
		foreach ( $pairs as $legacy_key => $value ) {
			update_option( $legacy_key, $value );
		}

		$this->run_migration();

		foreach ( $pairs as $legacy_key => $expected ) {
			$new_key = str_replace( 'etucart_vs_', 'freeman_core_variation_swatches_', $legacy_key );
			$this->assertSame( $expected, get_option( $new_key ), "Expected $new_key to equal legacy value." );
		}
	}
}
