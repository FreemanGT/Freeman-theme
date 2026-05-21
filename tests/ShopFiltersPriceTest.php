<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Query;
use Freeman\Core\Modules\ShopFilters\Query_Builder;
use Freeman\Core\Modules\ShopFilters\Url_State;
use PHPUnit\Framework\TestCase;

/**
 * Pure seams of the price-band facet: URL state, band construction, counting,
 * filtering, shaping, and the SQL overlap fragment.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Url_State
 * @covers \Freeman\Core\Modules\ShopFilters\Query_Builder
 * @covers \Freeman\Core\Modules\ShopFilters\Query
 */
final class ShopFiltersPriceTest extends TestCase {

	/* ---- Url_State price bands ---- */

	public function test_parse_price_bands_parses_open_and_closed_dedupes_sorts(): void {
		$bands = Url_State::parse_price_bands( '100-,0-50,50-100,0-50,bad,30-10' );

		// '30-10' (max < min) and 'bad' dropped; '0-50' deduped; sorted by min.
		$this->assertSame(
			array(
				array( 'min' => 0.0, 'max' => 50.0 ),
				array( 'min' => 50.0, 'max' => 100.0 ),
				array( 'min' => 100.0, 'max' => null ),
			),
			$bands
		);
	}

	public function test_price_bands_round_trip_through_serialize(): void {
		$bands = array(
			array( 'min' => 0.0, 'max' => 50.0 ),
			array( 'min' => 100.0, 'max' => null ),
		);

		$this->assertSame( '0-50,100-', Url_State::serialize_price_bands( $bands ) );
		$this->assertSame( $bands, Url_State::parse_price_bands( Url_State::serialize_price_bands( $bands ) ) );
	}

	public function test_parse_keeps_price_out_of_taxonomy_filters(): void {
		$state = Url_State::parse(
			array(
				'filter_price'    => '0-50',
				'filter_pa_color' => 'red',
			)
		);

		$this->assertArrayNotHasKey( 'price', $state['filters'] );
		$this->assertSame( array( 'red' ), $state['filters']['pa_color'] );
		$this->assertSame( array( array( 'min' => 0.0, 'max' => 50.0 ) ), $state['price_bands'] );
	}

	/* ---- band construction ---- */

	public function test_bands_from_bounds_builds_contiguous_with_open_top(): void {
		$bands = Query_Builder::bands_from_bounds( array( 100, 50, 50, -5, 200 ) );

		$this->assertSame(
			array(
				array( 'min' => 0.0, 'max' => 50.0 ),
				array( 'min' => 50.0, 'max' => 100.0 ),
				array( 'min' => 100.0, 'max' => 200.0 ),
				array( 'min' => 200.0, 'max' => null ),
			),
			$bands
		);
	}

	public function test_nice_round_snaps_to_1_2_5_decades(): void {
		$this->assertSame( 50.0, Query_Builder::nice_round( 47.0 ) );
		$this->assertSame( 100.0, Query_Builder::nice_round( 60.0 ) );
		$this->assertSame( 200.0, Query_Builder::nice_round( 130.0 ) );
		$this->assertSame( 1.0, Query_Builder::nice_round( 0.4 ) );
	}

	public function test_auto_bands_cover_zero_to_open_top(): void {
		$bands = Query_Builder::auto_bands( 380.0, 4 );

		$this->assertNotEmpty( $bands );
		$this->assertSame( 0.0, $bands[0]['min'] );
		$this->assertNull( $bands[ count( $bands ) - 1 ]['max'] );
	}

	/* ---- counting / filtering / shaping ---- */

	private function prices(): array {
		return array(
			10 => array( 'min' => 20.0, 'max' => 20.0 ),
			11 => array( 'min' => 60.0, 'max' => 60.0 ),
			12 => array( 'min' => 90.0, 'max' => 120.0 ), // straddles the 50-100 boundary.
			13 => array( 'min' => 300.0, 'max' => 300.0 ),
		);
	}

	public function test_count_in_bands_counts_overlap(): void {
		$bands = array(
			array( 'min' => 0.0, 'max' => 50.0 ),
			array( 'min' => 50.0, 'max' => 100.0 ),
			array( 'min' => 100.0, 'max' => null ),
		);

		// 0-50: {10}; 50-100: {11,12}; 100+: {12,13}.
		$this->assertSame( array( 1, 2, 2 ), Query_Builder::count_in_bands( $this->prices(), $bands ) );
	}

	public function test_filter_by_bands_ors_selected_and_preserves_order(): void {
		$selected = array(
			array( 'min' => 0.0, 'max' => 50.0 ),
			array( 'min' => 100.0, 'max' => null ),
		);

		$ids = Query_Builder::filter_by_bands( array( 10, 11, 12, 13 ), $this->prices(), $selected );

		$this->assertSame( array( 10, 12, 13 ), $ids );
	}

	public function test_filter_by_bands_empty_selection_returns_all(): void {
		$this->assertSame(
			array( 10, 11 ),
			Query_Builder::filter_by_bands( array( 10, 11 ), $this->prices(), array() )
		);
	}

	public function test_shape_price_facet_drops_zero_and_flags_selected(): void {
		$bands  = array(
			array( 'min' => 0.0, 'max' => 50.0 ),
			array( 'min' => 50.0, 'max' => 100.0 ),
			array( 'min' => 100.0, 'max' => null ),
		);
		$counts = array( 3, 0, 2 ); // middle band empty → dropped.

		$facet = Query_Builder::shape_price_facet( $bands, $counts, array( array( 'min' => 100.0, 'max' => null ) ) );

		$this->assertCount( 2, $facet['bands'] );
		$this->assertSame( 0.0, $facet['bands'][0]['min'] );
		$this->assertFalse( $facet['bands'][0]['selected'] );
		$this->assertNull( $facet['bands'][1]['max'] );
		$this->assertTrue( $facet['bands'][1]['selected'] );
	}

	/* ---- SQL fragment ---- */

	public function test_price_where_sql_builds_or_overlap_and_sanitises_alias(): void {
		$sql = Query::price_where_sql(
			array(
				array( 'min' => 0.0, 'max' => 50.0 ),
				array( 'min' => 100.0, 'max' => null ),
			),
			'fsf-price;DROP'
		);

		$this->assertStringContainsString( ' OR ', $sql );
		$this->assertStringContainsString( 'fsfpriceDROP.max_price', $sql ); // alias stripped to [a-z0-9_].
		$this->assertStringNotContainsString( ';', $sql );
		$this->assertStringStartsWith( '(', $sql );
	}

	public function test_price_where_sql_empty_for_no_bands(): void {
		$this->assertSame( '', Query::price_where_sql( array(), 'a' ) );
	}
}
