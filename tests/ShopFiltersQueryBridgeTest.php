<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Query;
use PHPUnit\Framework\TestCase;

/**
 * The query bridge: pure tax_query construction (AND across facets, OR within)
 * and the flag-gated hook wiring.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Query
 */
final class ShopFiltersQueryBridgeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_single_facet_builds_one_in_clause_without_relation(): void {
		$tq = Query::tax_query_for( array( 'pa_color' => array( 'red', 'blue' ) ) );

		$this->assertCount( 1, $tq );
		$this->assertArrayNotHasKey( 'relation', $tq );
		$this->assertSame( 'pa_color', $tq[0]['taxonomy'] );
		$this->assertSame( 'slug', $tq[0]['field'] );
		$this->assertSame( array( 'red', 'blue' ), $tq[0]['terms'] );
		$this->assertSame( 'IN', $tq[0]['operator'] );
	}

	public function test_multiple_facets_are_anded(): void {
		$tq = Query::tax_query_for(
			array(
				'pa_color' => array( 'red' ),
				'pa_size'  => array( 'm', 'l' ),
			)
		);

		$this->assertSame( 'AND', $tq['relation'] );
		$this->assertSame( 'pa_color', $tq[0]['taxonomy'] );
		$this->assertSame( 'pa_size', $tq[1]['taxonomy'] );
		$this->assertSame( array( 'm', 'l' ), $tq[1]['terms'] );
	}

	public function test_empty_or_blank_selection_yields_empty_tax_query(): void {
		$this->assertSame( array(), Query::tax_query_for( array() ) );
		$this->assertSame( array(), Query::tax_query_for( array( 'pa_color' => array( '', '' ) ) ) );
		$this->assertSame( array(), Query::tax_query_for( array( '' => array( 'red' ) ) ) );
	}

	public function test_slugs_deduped_within_facet(): void {
		$tq = Query::tax_query_for( array( 'pa_color' => array( 'red', 'red', 'blue' ) ) );

		$this->assertSame( array( 'red', 'blue' ), $tq[0]['terms'] );
	}

	public function test_register_wires_the_wc_filter_and_search_hook(): void {
		( new Query() )->register();

		$this->assertNotFalse( has_filter( 'woocommerce_product_query_tax_query' ) );
		$this->assertNotFalse( has_action( 'pre_get_posts' ) );
	}

	public function test_intersect_id_sets_ands_across_facets(): void {
		// AND across facets: only ids present in every set survive.
		$ids = Query::intersect_id_sets(
			array(
				array( 1, 2, 3, 4 ),
				array( 2, 3, 5 ),
				array( 3, 2 ),
			)
		);
		sort( $ids );

		$this->assertSame( array( 2, 3 ), $ids );
	}

	public function test_intersect_id_sets_empty_facet_short_circuits(): void {
		// A facet that resolved to no in-stock products yields no matches.
		$this->assertSame(
			array(),
			Query::intersect_id_sets( array( array( 1, 2, 3 ), array() ) )
		);
	}

	public function test_intersect_id_sets_single_facet_dedupes(): void {
		$this->assertSame(
			array( 1, 2 ),
			Query::intersect_id_sets( array( array( 1, 2, 2, 1 ) ) )
		);
	}

	public function test_intersect_id_sets_no_facets_is_empty(): void {
		$this->assertSame( array(), Query::intersect_id_sets( array() ) );
	}

	public function test_filter_posts_to_ids_keeps_allowed_in_order(): void {
		$posts = array(
			(object) array( 'ID' => 10 ),
			(object) array( 'ID' => 11 ),
			(object) array( 'ID' => 12 ),
		);

		$kept = Query::filter_posts_to_ids( $posts, array( 12, 10 ) );

		$this->assertCount( 2, $kept );
		$this->assertSame( 10, $kept[0]->ID ); // original order preserved.
		$this->assertSame( 12, $kept[1]->ID );
	}

	public function test_filter_posts_to_ids_empty_allowlist_drops_all(): void {
		$posts = array( (object) array( 'ID' => 10 ) );

		$this->assertSame( array(), Query::filter_posts_to_ids( $posts, array() ) );
	}
}
