<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Facet_Config;
use PHPUnit\Framework\TestCase;

/**
 * Facet config resolution: auto-derived defaults, per-category hiding, enabled
 * flag, and the visibility filter override.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Facet_Config
 */
final class ShopFiltersFacetConfigTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
		// saved() is gated by the admin_config flag (1.12.21); turn it on so the
		// saved-config cases below exercise the merge path.
		$GLOBALS['fr_opts']['freeman_core_shop_filters_admin_config_enabled'] = 1;
	}

	public function test_auto_derives_defaults_all_visible_in_order(): void {
		$defs  = Facet_Config::resolve( array( 'product_cat', 'pa_color', 'pa_size' ) );
		$taxes = array_map( static fn( $d ) => $d['taxonomy'], $defs );

		$this->assertSame( array( 'product_cat', 'pa_color', 'pa_size' ), $taxes );
		$this->assertSame( 'category', $defs[0]['type'] ); // product_cat
		$this->assertSame( 'checkbox', $defs[1]['type'] ); // pa_color
	}

	public function test_hide_on_categories_hides_facet_only_in_that_context(): void {
		$GLOBALS['fr_opts'][ Facet_Config::OPTION ] = array(
			array( 'taxonomy' => 'pa_color', 'type' => 'checkbox', 'enabled' => true, 'order' => 0, 'hide_on_categories' => array( 42 ) ),
		);

		$on_42 = array_map( static fn( $d ) => $d['taxonomy'], Facet_Config::resolve( array( 'pa_color', 'pa_size' ), 42 ) );
		$on_7  = array_map( static fn( $d ) => $d['taxonomy'], Facet_Config::resolve( array( 'pa_color', 'pa_size' ), 7 ) );

		$this->assertNotContains( 'pa_color', $on_42 );
		$this->assertContains( 'pa_color', $on_7 );
	}

	public function test_disabled_facet_is_excluded(): void {
		$GLOBALS['fr_opts'][ Facet_Config::OPTION ] = array(
			array( 'taxonomy' => 'pa_size', 'type' => 'checkbox', 'enabled' => false, 'order' => 0, 'hide_on_categories' => array() ),
		);

		$taxes = array_map( static fn( $d ) => $d['taxonomy'], Facet_Config::resolve( array( 'pa_color', 'pa_size' ) ) );

		$this->assertContains( 'pa_color', $taxes );
		$this->assertNotContains( 'pa_size', $taxes );
	}

	public function test_is_facet_visible_filter_can_force_hide(): void {
		add_filter(
			'freeman_core/shop_filters/is_facet_visible',
			static function ( $visible, $taxonomy ) {
				return 'pa_color' === $taxonomy ? false : $visible;
			},
			10,
			2
		);

		$taxes = array_map( static fn( $d ) => $d['taxonomy'], Facet_Config::resolve( array( 'pa_color', 'pa_size' ) ) );

		$this->assertNotContains( 'pa_color', $taxes );
		$this->assertContains( 'pa_size', $taxes );
	}
}
