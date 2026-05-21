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
}
