<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Term_Helpers;
use PHPUnit\Framework\TestCase;

/**
 * Pure attribute/term helpers used by the indexer.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Term_Helpers
 */
final class ShopFiltersTermHelpersTest extends TestCase {

	/**
	 * Three variations: red/m in stock, blue/l out of stock, any-colour/s in stock.
	 *
	 * @return array
	 */
	private function variations(): array {
		return array(
			array(
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'attributes'     => array( 'attribute_pa_color' => 'red', 'attribute_pa_size' => 'm' ),
			),
			array(
				'is_in_stock'    => false, // out of stock — must be ignored.
				'is_purchasable' => true,
				'attributes'     => array( 'attribute_pa_color' => 'blue', 'attribute_pa_size' => 'l' ),
			),
			array(
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'attributes'     => array( 'attribute_pa_color' => '', 'attribute_pa_size' => 's' ), // "any" colour.
			),
		);
	}

	public function test_maps_only_in_stock_values_and_records_any_flag(): void {
		$map = Term_Helpers::in_stock_values_by_attribute( $this->variations() );

		$this->assertArrayHasKey( 'red', $map['values']['attribute_pa_color'] );
		$this->assertArrayNotHasKey( 'blue', $map['values']['attribute_pa_color'] ?? array() );

		$this->assertArrayHasKey( 'm', $map['values']['attribute_pa_size'] );
		$this->assertArrayHasKey( 's', $map['values']['attribute_pa_size'] );
		$this->assertArrayNotHasKey( 'l', $map['values']['attribute_pa_size'] ?? array() );

		$this->assertNotEmpty( $map['any']['attribute_pa_color'] );
	}

	public function test_value_in_stock_respects_explicit_and_any(): void {
		$map = Term_Helpers::in_stock_values_by_attribute( $this->variations() );

		$this->assertTrue( Term_Helpers::value_in_stock( $map, 'attribute_pa_size', 'm' ) );
		$this->assertFalse( Term_Helpers::value_in_stock( $map, 'attribute_pa_size', 'l' ) );
		// An in-stock "any" variation makes every colour count, even one never listed.
		$this->assertTrue( Term_Helpers::value_in_stock( $map, 'attribute_pa_color', 'green' ) );
		// No "any" on size, and 'xl' never appears.
		$this->assertFalse( Term_Helpers::value_in_stock( $map, 'attribute_pa_size', 'xl' ) );
	}

	public function test_empty_variations_yield_empty_map(): void {
		$map = Term_Helpers::in_stock_values_by_attribute( array() );

		$this->assertSame( array(), $map['values'] );
		$this->assertSame( array(), $map['any'] );
	}

	public function test_resolve_in_stock_gates_only_variation_attributes(): void {
		$map = Term_Helpers::in_stock_values_by_attribute( $this->variations() );

		// A non-variation attribute (e.g. Brand) follows overall stock, ignoring the map.
		$this->assertSame( 1, Term_Helpers::resolve_in_stock( false, $map, 'attribute_pa_brand', 'acme', 1 ) );
		$this->assertSame( 0, Term_Helpers::resolve_in_stock( false, $map, 'attribute_pa_brand', 'acme', 0 ) );

		// A variation-axis attribute is gated by its variations' stock.
		$this->assertSame( 1, Term_Helpers::resolve_in_stock( true, $map, 'attribute_pa_size', 'm', 1 ) );
		// 'l' is out of stock even though the product is overall in stock.
		$this->assertSame( 0, Term_Helpers::resolve_in_stock( true, $map, 'attribute_pa_size', 'l', 1 ) );
	}
}
