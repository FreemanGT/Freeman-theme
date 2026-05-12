<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Freeman\Core\Modules\VariationSwatches\Attribute_Order;

/**
 * 1.11.50 — PDP swatch option ordering.
 *
 * Exercises Attribute_Order::reorder() without the WC stack: a tiny stand-in
 * product exposes get_id() / get_attributes(), and the taxonomy path reads
 * the bootstrap's wc_get_product_terms() stub via $GLOBALS['fr_product_terms'].
 * Precedent for unit-testing extracted helpers this way: the VariationSwatches
 * card-image / image-swatch payload snapshot tests.
 *
 * @covers \Freeman\Core\Modules\VariationSwatches\Attribute_Order
 */
final class VariationSwatchesAttributeOrderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_product_terms'] = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fr_product_terms'] );
		parent::tearDown();
	}

	/** A variable product with custom (non-taxonomy) attributes in a fixed typed order. */
	private function product_with_custom_attributes( array $typed ): object {
		$attrs = array();
		foreach ( $typed as $name => $options ) {
			$attrs[ sanitize_title( $name ) ] = new class( $name, $options ) {
				public function __construct( private string $name, private array $options ) {}
				public function get_name(): string {
					return $this->name;
				}
				public function get_options(): array {
					return $this->options;
				}
			};
		}
		return new class( $attrs ) {
			public function __construct( private array $attrs ) {}
			public function get_id(): int {
				return 123;
			}
			public function get_attributes(): array {
				return $this->attrs;
			}
		};
	}

	private function bare_product(): object {
		return new class {
			public function get_id(): int {
				return 123;
			}
			public function get_attributes(): array {
				return array();
			}
		};
	}

	public function test_custom_attribute_follows_typed_order(): void {
		$product = $this->product_with_custom_attributes( array( 'Size' => array( 'S', 'M', 'L', 'XL' ) ) );
		$out     = Attribute_Order::reorder( array( 'Size' => array( 'XL', 'S', 'L', 'M' ) ), $product, array() );
		$this->assertSame( array( 'S', 'M', 'L', 'XL' ), $out['Size'] );
	}

	public function test_numeric_values_sort_ascending(): void {
		$out = Attribute_Order::reorder( array( 'Waist' => array( '28', '23', '32', '25', '27' ) ), $this->bare_product(), array() );
		$this->assertSame( array( '23', '25', '27', '28', '32' ), $out['Waist'] );
	}

	public function test_numeric_short_circuit_beats_configured_order(): void {
		// Even if the merchant typed them out of order, an all-numeric attribute
		// sorts ascending.
		$product = $this->product_with_custom_attributes( array( 'Waist' => array( '32', '23', '28' ) ) );
		$out     = Attribute_Order::reorder( array( 'Waist' => array( '32', '23', '28' ) ), $product, array() );
		$this->assertSame( array( '23', '28', '32' ), $out['Waist'] );
	}

	public function test_taxonomy_attribute_follows_wc_get_product_terms(): void {
		$GLOBALS['fr_product_terms']['pa_color'] = array( 'red', 'green', 'blue' );
		$out = Attribute_Order::reorder( array( 'pa_color' => array( 'blue', 'red', 'green' ) ), $this->bare_product(), array() );
		$this->assertSame( array( 'red', 'green', 'blue' ), $out['pa_color'] );
	}

	public function test_values_not_in_reference_order_are_appended(): void {
		$product = $this->product_with_custom_attributes( array( 'Size' => array( 'S', 'M', 'L' ) ) );
		$out     = Attribute_Order::reorder( array( 'Size' => array( 'M', 'XXL', 'S' ) ), $product, array() );
		$this->assertSame( array( 'S', 'M', 'XXL' ), $out['Size'] );
	}

	public function test_out_of_stock_values_demoted_to_end(): void {
		$product = $this->product_with_custom_attributes( array( 'Size' => array( 'S', 'M', 'L' ) ) );
		$variations = array(
			array( 'is_in_stock' => true, 'is_purchasable' => true, 'attributes' => array( 'attribute_size' => 'S' ) ),
			array( 'is_in_stock' => true, 'is_purchasable' => true, 'attributes' => array( 'attribute_size' => 'L' ) ),
			// M exists but is out of stock -> not indexed.
			array( 'is_in_stock' => false, 'is_purchasable' => true, 'attributes' => array( 'attribute_size' => 'M' ) ),
		);
		$out = Attribute_Order::reorder( array( 'Size' => array( 'L', 'M', 'S' ) ), $product, $variations );
		// Base order (typed): S, M, L -> demote M: S, L, M.
		$this->assertSame( array( 'S', 'L', 'M' ), $out['Size'] );
	}

	public function test_in_stock_any_variation_keeps_every_value(): void {
		$product = $this->product_with_custom_attributes( array( 'Size' => array( 'S', 'M', 'L' ) ) );
		$variations = array(
			// "Any size" variation, in stock -> every Size value counts as in stock.
			array( 'is_in_stock' => true, 'is_purchasable' => true, 'attributes' => array( 'attribute_size' => '' ) ),
		);
		$out = Attribute_Order::reorder( array( 'Size' => array( 'L', 'M', 'S' ) ), $product, $variations );
		$this->assertSame( array( 'S', 'M', 'L' ), $out['Size'] );
	}

	public function test_all_out_of_stock_leaves_order_untouched(): void {
		$product = $this->product_with_custom_attributes( array( 'Size' => array( 'S', 'M', 'L' ) ) );
		$variations = array(
			array( 'is_in_stock' => false, 'is_purchasable' => true, 'attributes' => array( 'attribute_size' => 'S' ) ),
			array( 'is_in_stock' => false, 'is_purchasable' => true, 'attributes' => array( 'attribute_size' => 'M' ) ),
		);
		// No in-stock data at all -> base order only, no demotion shuffle.
		$out = Attribute_Order::reorder( array( 'Size' => array( 'L', 'S', 'M' ) ), $product, $variations );
		$this->assertSame( array( 'S', 'M', 'L' ), $out['Size'] );
	}

	public function test_single_option_and_empty_attributes_untouched(): void {
		$this->assertSame( array(), Attribute_Order::reorder( array(), $this->bare_product(), array() ) );
		$out = Attribute_Order::reorder( array( 'Size' => array( 'OneSize' ) ), $this->bare_product(), array() );
		$this->assertSame( array( 'OneSize' ), $out['Size'] );
	}

	public function test_fractional_and_plain_sizes_sort_numerically(): void {
		// French/EU shoe sizing: plain ints and "N M/D" fractions intermixed.
		$out = Attribute_Order::reorder(
			array( 'EU' => array( '38 2/3', '33', '37 1/3', '36 2/3', '28', '30' ) ),
			$this->bare_product(),
			array()
		);
		$this->assertSame( array( '28', '30', '33', '36 2/3', '37 1/3', '38 2/3' ), $out['EU'] );
	}

	public function test_comma_decimal_is_treated_as_numeric(): void {
		$out = Attribute_Order::reorder( array( 'EU' => array( '37', '36,5', '35' ) ), $this->bare_product(), array() );
		$this->assertSame( array( '35', '36,5', '37' ), $out['EU'] );
	}

	public function test_non_numeric_value_disables_numeric_sort(): void {
		// One unrankable value -> the attribute is no longer fully rankable,
		// so it falls back to the configured order (none here) i.e. input order.
		$out = Attribute_Order::reorder( array( 'EU' => array( '38 2/3', 'TBD', '33' ) ), $this->bare_product(), array() );
		$this->assertSame( array( '38 2/3', 'TBD', '33' ), $out['EU'] );
	}

	public function test_letter_sizes_sort_by_size_not_alphabetically(): void {
		$out = Attribute_Order::reorder(
			array( 'Size' => array( 'XL', 'S', 'M', 'XXL', 'L', 'XS' ) ),
			$this->bare_product(),
			array()
		);
		$this->assertSame( array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' ), $out['Size'] );
	}

	public function test_numeric_prefixed_x_sizes_sort(): void {
		$out = Attribute_Order::reorder(
			array( 'Size' => array( '2XL', 'XL', '3XL', 'L', 'M' ) ),
			$this->bare_product(),
			array()
		);
		$this->assertSame( array( 'M', 'L', 'XL', '2XL', '3XL' ), $out['Size'] );
	}

	public function test_spelled_out_sizes_sort(): void {
		$out = Attribute_Order::reorder(
			array( 'Size' => array( 'Large', 'Small', 'X-Large', 'Medium', 'XX-Large' ) ),
			$this->bare_product(),
			array()
		);
		$this->assertSame( array( 'Small', 'Medium', 'Large', 'X-Large', 'XX-Large' ), $out['Size'] );
	}

	public function test_one_size_sorts_first(): void {
		$out = Attribute_Order::reorder(
			array( 'Size' => array( 'M', 'One Size', 'S', 'L' ) ),
			$this->bare_product(),
			array()
		);
		$this->assertSame( array( 'One Size', 'S', 'M', 'L' ), $out['Size'] );
	}

	public function test_age_range_values_sort_by_first_number(): void {
		// Baby clothing: "<a>-<b> חודשים" (months). Sort by the first number.
		$out = Attribute_Order::reorder(
			array(
				'Age' => array( '9-12 חודשים', '0-3 חודשים', '18-24 חודשים', '3-6 חודשים', '12-18 חודשים' ),
			),
			$this->bare_product(),
			array()
		);
		$this->assertSame(
			array( '0-3 חודשים', '3-6 חודשים', '9-12 חודשים', '12-18 חודשים', '18-24 חודשים' ),
			$out['Age']
		);
	}

	public function test_numeric_range_labels_sort_numerically(): void {
		$out = Attribute_Order::reorder( array( 'Age' => array( '10-11', '6-7', '8-9' ) ), $this->bare_product(), array() );
		$this->assertSame( array( '6-7', '8-9', '10-11' ), $out['Age'] );
	}
}
