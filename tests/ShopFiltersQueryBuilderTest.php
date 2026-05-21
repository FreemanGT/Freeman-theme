<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Query_Builder;
use PHPUnit\Framework\TestCase;

/**
 * Pure seams of the query builder: slug → term-id resolution and the
 * engine-counts → facets[] shaping (hide-zero, selected flag, ordering).
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Query_Builder
 */
final class ShopFiltersQueryBuilderTest extends TestCase {

	public function test_resolve_active_maps_slugs_and_drops_unknowns(): void {
		$filters = array(
			'pa_color' => array( 'red', 'blue', 'ghost' ), // 'ghost' is unknown.
			'pa_size'  => array( 'm' ),
		);
		$map = array(
			'pa_color' => array( 'red' => 11, 'blue' => 12 ),
			'pa_size'  => array( 'm' => 21 ),
		);

		$active = Query_Builder::resolve_active( $filters, $map );

		$this->assertSame( array( 11, 12 ), $active['pa_color'] );
		$this->assertSame( array( 21 ), $active['pa_size'] );
	}

	public function test_resolve_active_omits_taxonomy_with_no_resolvable_term(): void {
		$active = Query_Builder::resolve_active(
			array( 'pa_color' => array( 'unknown' ) ),
			array( 'pa_color' => array( 'red' => 11 ) )
		);

		$this->assertSame( array(), $active );
	}

	public function test_resolve_active_dedupes_term_ids(): void {
		// Two slugs resolving to the same id collapse to one.
		$active = Query_Builder::resolve_active(
			array( 'pa_color' => array( 'red', 'rouge' ) ),
			array( 'pa_color' => array( 'red' => 11, 'rouge' => 11 ) )
		);

		$this->assertSame( array( 11 ), $active['pa_color'] );
	}

	public function test_shape_facets_emits_only_counted_terms_and_flags_selected(): void {
		$defs = array(
			array( 'taxonomy' => 'pa_color', 'type' => 'checkbox', 'label' => 'Colour' ),
		);
		$engine = array(
			'pa_color' => array( 11 => 3, 12 => 1 ), // only red + blue have counts.
		);
		$term_index = array(
			'pa_color' => array(
				11 => array( 'slug' => 'red', 'name' => 'Red', 'order' => 1 ),
				12 => array( 'slug' => 'blue', 'name' => 'Blue', 'order' => 0 ),
			),
		);
		$active_slugs = array( 'pa_color' => array( 'red' ) );

		$facets = Query_Builder::shape_facets( $defs, $engine, $term_index, $active_slugs );

		$this->assertCount( 1, $facets );
		$this->assertSame( 'pa_color', $facets[0]['taxonomy'] );
		$this->assertSame( 'Colour', $facets[0]['label'] );
		$this->assertFalse( $facets[0]['hidden'] );

		// Ordered by 'order' (blue=0 before red=1); 'order' stripped from wire shape.
		$terms = $facets[0]['terms'];
		$this->assertSame( 'blue', $terms[0]['slug'] );
		$this->assertSame( 'red', $terms[1]['slug'] );
		$this->assertArrayNotHasKey( 'order', $terms[0] );

		// Counts + selected flag carried through.
		$this->assertSame( 1, $terms[0]['count'] );
		$this->assertFalse( $terms[0]['selected'] );
		$this->assertTrue( $terms[1]['selected'] );
	}

	public function test_shape_facets_hides_facet_absent_from_engine(): void {
		// A facet def whose taxonomy the engine returned no terms for is dropped
		// entirely (hide-empty-facet, requirement #1).
		$facets = Query_Builder::shape_facets(
			array( array( 'taxonomy' => 'pa_size', 'type' => 'checkbox', 'label' => 'Size' ) ),
			array(),
			array(),
			array()
		);

		$this->assertSame( array(), $facets );
	}

	public function test_shape_facets_skips_terms_without_a_slug(): void {
		// A counted term we have no slug for can't be a checkbox — skip it.
		$facets = Query_Builder::shape_facets(
			array( array( 'taxonomy' => 'pa_color', 'type' => 'checkbox', 'label' => 'Colour' ) ),
			array( 'pa_color' => array( 11 => 2, 99 => 5 ) ),
			array( 'pa_color' => array( 11 => array( 'slug' => 'red', 'name' => 'Red', 'order' => 0 ) ) ),
			array()
		);

		$this->assertCount( 1, $facets[0]['terms'] );
		$this->assertSame( 'red', $facets[0]['terms'][0]['slug'] );
	}

	public function test_shape_facets_carries_swatch_data_and_flips_type_to_color(): void {
		// A facet whose term-index entries carry colour/image becomes a 'color'
		// facet (rendered as swatches), and the data rides through onto the terms.
		$facets = Query_Builder::shape_facets(
			array( array( 'taxonomy' => 'pa_color', 'type' => 'checkbox', 'label' => 'Colour' ) ),
			array( 'pa_color' => array( 11 => 2, 12 => 1 ) ),
			array(
				'pa_color' => array(
					11 => array( 'slug' => 'red', 'name' => 'Red', 'order' => 0, 'color' => '#ff0000' ),
					12 => array( 'slug' => 'denim', 'name' => 'Denim', 'order' => 1, 'image' => 'https://example.test/denim.jpg' ),
				),
			),
			array()
		);

		$this->assertSame( 'color', $facets[0]['type'] );
		$terms = $facets[0]['terms'];
		$this->assertSame( '#ff0000', $terms[0]['color'] );
		$this->assertArrayNotHasKey( 'image', $terms[0] );
		$this->assertSame( 'https://example.test/denim.jpg', $terms[1]['image'] );
		$this->assertArrayNotHasKey( 'color', $terms[1] );
	}

	public function test_shape_facets_keeps_checkbox_type_without_swatch_data(): void {
		$facets = Query_Builder::shape_facets(
			array( array( 'taxonomy' => 'pa_size', 'type' => 'checkbox', 'label' => 'Size' ) ),
			array( 'pa_size' => array( 21 => 4 ) ),
			array( 'pa_size' => array( 21 => array( 'slug' => 'm', 'name' => 'M', 'order' => 0 ) ) ),
			array()
		);

		$this->assertSame( 'checkbox', $facets[0]['type'] );
		$this->assertArrayNotHasKey( 'color', $facets[0]['terms'][0] );
	}

	public function test_shape_category_nodes_maps_counts_with_meta_and_drops_unknown(): void {
		$counts = array( 5 => 3, 6 => 1, 99 => 7 ); // 99 has no metadata.
		$meta   = array(
			5 => array( 'parent' => 0, 'name' => 'Clothing', 'slug' => 'clothing', 'order' => 2 ),
			6 => array( 'parent' => 5, 'name' => 'Shirts', 'slug' => 'shirts', 'order' => 0 ),
		);

		$nodes = Query_Builder::shape_category_nodes( $counts, $meta );

		$this->assertCount( 2, $nodes );
		$this->assertSame( 5, $nodes[0]['term_id'] );
		$this->assertSame( 0, $nodes[0]['parent'] );
		$this->assertSame( 'Clothing', $nodes[0]['name'] );
		$this->assertSame( 3, $nodes[0]['count'] );
		$this->assertSame( 2, $nodes[0]['order'] );
		$this->assertSame( 5, $nodes[1]['parent'] );
		$this->assertSame( 'shirts', $nodes[1]['slug'] );
	}
}
