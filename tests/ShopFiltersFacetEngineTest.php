<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Facet_Engine;
use PHPUnit\Framework\TestCase;

/**
 * Pure facet set-algebra: AND-across / OR-within, self-exclusion, hide-zero,
 * hide-empty-facet.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Facet_Engine
 */
final class ShopFiltersFacetEngineTest extends TestCase {

	/**
	 * base 1..4; colours red[1,2] blue[3] green[4]; sizes s[1,3] m[2,4].
	 *
	 * @return array
	 */
	private function postings(): array {
		return array(
			'pa_color' => array( 10 => array( 1, 2 ), 11 => array( 3 ), 12 => array( 4 ) ),
			'pa_size'  => array( 20 => array( 1, 3 ), 21 => array( 2, 4 ) ),
		);
	}

	public function test_no_filters_returns_all_with_full_counts(): void {
		$result = Facet_Engine::compute( array( 1, 2, 3, 4 ), $this->postings(), array(), array( 'pa_color', 'pa_size' ) );

		$this->assertSame( array( 1, 2, 3, 4 ), $result['products'] );
		$this->assertSame( array( 10 => 2, 11 => 1, 12 => 1 ), $result['facets']['pa_color'] );
		$this->assertSame( array( 20 => 2, 21 => 2 ), $result['facets']['pa_size'] );
	}

	public function test_self_exclusion_keeps_sibling_values_of_the_active_facet(): void {
		// Pick red (10). Grid narrows to red products [1,2], but the colour facet
		// must STILL offer blue/green — the classic faceted-search bug guard.
		$result = Facet_Engine::compute(
			array( 1, 2, 3, 4 ),
			$this->postings(),
			array( 'pa_color' => array( 10 ) ),
			array( 'pa_color', 'pa_size' )
		);

		$this->assertSame( array( 1, 2 ), $result['products'] );
		// Colour availability is self-excluded → computed from the full base.
		$this->assertSame( array( 10 => 2, 11 => 1, 12 => 1 ), $result['facets']['pa_color'] );
		// Size is narrowed to the red products [1,2]: s(1), m(1).
		$this->assertSame( array( 20 => 1, 21 => 1 ), $result['facets']['pa_size'] );
	}

	public function test_and_across_facets_or_within_a_facet(): void {
		// (red OR blue) AND size m: red|blue = [1,2,3]; m = [2,4]; ∩ = [2].
		$result = Facet_Engine::compute(
			array( 1, 2, 3, 4 ),
			$this->postings(),
			array(
				'pa_color' => array( 10, 11 ),
				'pa_size'  => array( 21 ),
			),
			array( 'pa_color', 'pa_size' )
		);

		$this->assertSame( array( 2 ), $result['products'] );
	}

	public function test_hides_zero_count_values_within_a_facet(): void {
		// Base restricted to [1]: only red has a match; blue/green drop out.
		$result = Facet_Engine::compute( array( 1 ), $this->postings(), array(), array( 'pa_color' ) );

		$this->assertSame( array( 10 => 1 ), $result['facets']['pa_color'] );
		$this->assertArrayNotHasKey( 11, $result['facets']['pa_color'] );
	}

	public function test_hides_facet_with_no_available_terms(): void {
		// Product 5 is in no posting → both facets have no available term → omitted.
		$result = Facet_Engine::compute( array( 5 ), $this->postings(), array(), array( 'pa_color', 'pa_size' ) );

		$this->assertSame( array( 5 ), $result['products'] ); // base passes through (no filters).
		$this->assertArrayNotHasKey( 'pa_color', $result['facets'] );
		$this->assertArrayNotHasKey( 'pa_size', $result['facets'] );
	}
}
