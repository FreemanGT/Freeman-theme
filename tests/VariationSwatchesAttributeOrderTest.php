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
}
