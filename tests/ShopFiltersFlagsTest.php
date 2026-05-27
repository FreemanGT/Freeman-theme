<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Query;
use Freeman\Core\Modules\ShopFilters\Query_Builder;
use PHPUnit\Framework\TestCase;

/**
 * On-sale / in-stock numeric facets (Phase 6.5c): the pure SQL fragment, the
 * grid-set intersection, and the wire-facet shaping (hide-zero + in-stock only
 * when out-of-stock products are shown). The lookup reads + hook wiring are
 * integration / live-QA.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Query
 * @covers \Freeman\Core\Modules\ShopFilters\Query_Builder
 */
final class ShopFiltersFlagsTest extends TestCase {

	public function test_flags_where_sql_onsale_only(): void {
		$this->assertSame( 'fsf_price.onsale = 1', Query::flags_where_sql( true, false, 'fsf_price' ) );
	}

	public function test_flags_where_sql_in_stock_only(): void {
		$this->assertSame( "fsf_price.stock_status = 'instock'", Query::flags_where_sql( false, true, 'fsf_price' ) );
	}

	public function test_flags_where_sql_both_are_anded(): void {
		$this->assertSame(
			"fsf_price.onsale = 1 AND fsf_price.stock_status = 'instock'",
			Query::flags_where_sql( true, true, 'fsf_price' )
		);
	}

	public function test_flags_where_sql_empty_when_neither(): void {
		$this->assertSame( '', Query::flags_where_sql( false, false, 'fsf_price' ) );
	}

	public function test_flags_where_sql_sanitises_alias(): void {
		// Dangerous characters (space, semicolon) are stripped from the alias;
		// only [A-Za-z0-9_] survive into the identifier.
		$this->assertSame( 'fsfpriceDROP.onsale = 1', Query::flags_where_sql( true, false, 'fsf price;DROP' ) );
	}

	public function test_filter_by_flags_returns_all_when_no_flag_active(): void {
		$this->assertSame(
			array( 1, 2, 3 ),
			Query_Builder::filter_by_flags( array( 1, 2, 3 ), array(), false, false )
		);
	}

	public function test_filter_by_flags_ands_onsale_and_in_stock(): void {
		$map = array(
			1 => array( 'onsale' => true, 'in_stock' => true ),
			2 => array( 'onsale' => true, 'in_stock' => false ),
			3 => array( 'onsale' => false, 'in_stock' => true ),
		);

		$this->assertSame( array( 1, 2 ), Query_Builder::filter_by_flags( array( 1, 2, 3 ), $map, true, false ) );
		$this->assertSame( array( 1, 3 ), Query_Builder::filter_by_flags( array( 1, 2, 3 ), $map, false, true ) );
		$this->assertSame( array( 1 ), Query_Builder::filter_by_flags( array( 1, 2, 3 ), $map, true, true ) );
	}

	public function test_shape_flag_facet_hides_zero_count_onsale(): void {
		$this->assertSame( array(), Query_Builder::shape_flag_facet( 0, 0, false, false, true ) );
	}

	public function test_shape_flag_facet_shows_onsale_with_count(): void {
		$facet = Query_Builder::shape_flag_facet( 5, 0, true, false, true );

		$this->assertArrayHasKey( 'onsale', $facet );
		$this->assertSame( 5, $facet['onsale']['count'] );
		$this->assertTrue( $facet['onsale']['selected'] );
	}

	public function test_shape_flag_facet_keeps_selected_zero_count_so_it_can_be_unticked(): void {
		$facet = Query_Builder::shape_flag_facet( 0, 0, true, false, true );

		$this->assertArrayHasKey( 'onsale', $facet );
		$this->assertSame( 0, $facet['onsale']['count'] );
	}

	public function test_shape_flag_facet_omits_in_stock_when_oos_hidden(): void {
		// $show_in_stock = false → in-stock flag is never offered (redundant when
		// the store already hides out-of-stock products).
		$facet = Query_Builder::shape_flag_facet( 3, 7, false, false, false );

		$this->assertArrayNotHasKey( 'in_stock', $facet );
		$this->assertArrayHasKey( 'onsale', $facet );
	}

	public function test_shape_flag_facet_shows_in_stock_when_oos_visible(): void {
		$facet = Query_Builder::shape_flag_facet( 0, 7, false, false, true );

		$this->assertArrayHasKey( 'in_stock', $facet );
		$this->assertSame( 7, $facet['in_stock']['count'] );
	}
}
