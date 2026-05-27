<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Facet_Config;
use PHPUnit\Framework\TestCase;

/**
 * Admin facet-config matrix (Phase 6.4): the pure sanitisation of a matrix
 * submission, and the reversibility guarantee that the saved config is ignored
 * unless the admin_config flag is on.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Facet_Config
 */
final class ShopFiltersFacetConfigAdminTest extends TestCase {

	private const FLAG = 'freeman_core_shop_filters_admin_config_enabled';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_sanitize_drops_unknown_taxonomies(): void {
		$raw = array(
			'pa_color'  => array( 'enabled' => '1', 'order' => '0' ),
			'pa_bogus'  => array( 'enabled' => '1', 'order' => '0' ),
		);

		$config = Facet_Config::sanitize( $raw, array( 'pa_color', 'pa_size' ) );
		$taxes  = array_map( static fn( $d ) => $d['taxonomy'], $config );

		$this->assertSame( array( 'pa_color', 'pa_size' ), $taxes );
		$this->assertNotContains( 'pa_bogus', $taxes );
	}

	public function test_sanitize_coerces_types_into_resolve_shape(): void {
		$raw = array(
			'pa_color' => array(
				'enabled'            => '1',
				'order'              => '3',
				'hide_on_categories' => array( '42', '42', '0', 'x', '7' ),
			),
			// pa_size: absent enabled key => disabled; no order => fallback index.
			'pa_size'  => array( 'hide_on_categories' => 'not-an-array' ),
		);

		$config = Facet_Config::sanitize( $raw, array( 'pa_color', 'pa_size' ) );

		$this->assertSame(
			array(
				'taxonomy'           => 'pa_color',
				'type'               => 'checkbox',
				'enabled'            => true,
				'order'              => 3,
				'hide_on_categories' => array( 42, 7 ),
			),
			$config[0]
		);
		$this->assertFalse( $config[1]['enabled'] );
		$this->assertSame( 1, $config[1]['order'] );
		$this->assertSame( array(), $config[1]['hide_on_categories'] );
	}

	public function test_sanitize_derives_category_type_for_product_cat(): void {
		$config = Facet_Config::sanitize(
			array( 'product_cat' => array( 'enabled' => '1' ) ),
			array( 'product_cat' )
		);

		$this->assertSame( 'category', $config[0]['type'] );
	}

	public function test_saved_config_is_ignored_when_flag_off(): void {
		$GLOBALS['fr_opts'][ Facet_Config::OPTION ] = array(
			array( 'taxonomy' => 'pa_size', 'type' => 'checkbox', 'enabled' => false, 'order' => 0, 'hide_on_categories' => array() ),
		);
		// Flag off (not set): saved() returns empty, so the disable is ignored.

		$taxes = array_map( static fn( $d ) => $d['taxonomy'], Facet_Config::resolve( array( 'pa_color', 'pa_size' ) ) );

		$this->assertContains( 'pa_size', $taxes, 'Flag-off must fall back to auto-derived defaults.' );
	}

	public function test_saved_config_is_honoured_when_flag_on(): void {
		$GLOBALS['fr_opts'][ self::FLAG ]           = 1;
		$GLOBALS['fr_opts'][ Facet_Config::OPTION ] = array(
			array( 'taxonomy' => 'pa_size', 'type' => 'checkbox', 'enabled' => false, 'order' => 0, 'hide_on_categories' => array() ),
		);

		$taxes = array_map( static fn( $d ) => $d['taxonomy'], Facet_Config::resolve( array( 'pa_color', 'pa_size' ) ) );

		$this->assertNotContains( 'pa_size', $taxes, 'Flag-on must honour the saved disable.' );
		$this->assertContains( 'pa_color', $taxes );
	}

	public function test_all_defs_includes_disabled_facets_for_the_editor(): void {
		$GLOBALS['fr_opts'][ self::FLAG ]           = 1;
		$GLOBALS['fr_opts'][ Facet_Config::OPTION ] = array(
			array( 'taxonomy' => 'pa_size', 'type' => 'checkbox', 'enabled' => false, 'order' => 0, 'hide_on_categories' => array() ),
		);

		$defs  = Facet_Config::all_defs( array( 'pa_color', 'pa_size' ) );
		$taxes = array_map( static fn( $d ) => $d['taxonomy'], $defs );

		// Unlike resolve(), the editor view keeps the disabled facet so it can be re-enabled.
		$this->assertContains( 'pa_size', $taxes );
		$disabled = array_values( array_filter( $defs, static fn( $d ) => 'pa_size' === $d['taxonomy'] ) )[0];
		$this->assertFalse( $disabled['enabled'] );
	}
}
