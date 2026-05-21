<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Diagnostics;
use PHPUnit\Framework\TestCase;

/**
 * Pure shaping for the index diagnostic: merging raw term stats with resolved
 * term name/slug, flagging unresolved terms, and ordering.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Diagnostics
 */
final class ShopFiltersDiagnosticsTest extends TestCase {

	public function test_merges_term_name_and_slug_and_keeps_counts(): void {
		$stats = array(
			array( 'taxonomy' => 'pa_color', 'term_id' => 11, 'products' => 4, 'in_stock' => 3 ),
		);
		$lookup = array(
			'pa_color' => array( 11 => array( 'slug' => 'red', 'name' => 'Red' ) ),
		);

		$rows = Diagnostics::shape_rows( $stats, $lookup );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Red', $rows[0]['name'] );
		$this->assertSame( 'red', $rows[0]['slug'] );
		$this->assertSame( 4, $rows[0]['products'] );
		$this->assertSame( 3, $rows[0]['in_stock'] );
		$this->assertTrue( $rows[0]['resolved'] );
	}

	public function test_flags_unresolved_term_present_in_index_but_not_live(): void {
		$stats = array(
			array( 'taxonomy' => 'pa_size', 'term_id' => 99, 'products' => 1, 'in_stock' => 0 ),
		);

		$rows = Diagnostics::shape_rows( $stats, array() );

		$this->assertFalse( $rows[0]['resolved'] );
		$this->assertSame( '', $rows[0]['slug'] );
		$this->assertSame( 99, $rows[0]['term_id'] );
	}

	public function test_exposes_a_name_slug_mismatch_for_the_eye(): void {
		// The shaper does not "fix" a scramble; it surfaces it — a term named "S"
		// whose slug is "5" is exactly the data bug filters trip over.
		$rows = Diagnostics::shape_rows(
			array( array( 'taxonomy' => 'pa_clothing-size', 'term_id' => 7, 'products' => 2, 'in_stock' => 2 ) ),
			array( 'pa_clothing-size' => array( 7 => array( 'slug' => '5', 'name' => 'S' ) ) )
		);

		$this->assertSame( 'S', $rows[0]['name'] );
		$this->assertSame( '5', $rows[0]['slug'] );
	}

	public function test_sorted_by_taxonomy_then_name(): void {
		$rows = Diagnostics::shape_rows(
			array(
				array( 'taxonomy' => 'pa_size', 'term_id' => 2, 'products' => 1, 'in_stock' => 1 ),
				array( 'taxonomy' => 'pa_color', 'term_id' => 12, 'products' => 1, 'in_stock' => 1 ),
				array( 'taxonomy' => 'pa_color', 'term_id' => 11, 'products' => 1, 'in_stock' => 1 ),
			),
			array(
				'pa_color' => array(
					12 => array( 'slug' => 'blue', 'name' => 'Blue' ),
					11 => array( 'slug' => 'red', 'name' => 'Red' ),
				),
				'pa_size'  => array( 2 => array( 'slug' => 'm', 'name' => 'M' ) ),
			)
		);

		$this->assertSame( array( 'pa_color', 'pa_color', 'pa_size' ), array_column( $rows, 'taxonomy' ) );
		$this->assertSame( array( 'Blue', 'Red', 'M' ), array_column( $rows, 'name' ) );
	}
}
