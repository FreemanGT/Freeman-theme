<?php
declare(strict_types=1);

// Reuse the WC_Product shim from the ProductFeed snapshot fixture so only one
// definition of WC_Product exists across the whole suite — otherwise alphabetical
// test loading races different shims and snapshot tests that need richer stubs lose.
require_once __DIR__ . '/snapshots/__fixtures__/wc_product_stub.php';

use Freeman\Core\Modules\CheapestDefaultVariation\Module;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freeman\Core\Modules\CheapestDefaultVariation\Module
 */
final class CheapestVariationHooksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();

		// PDP-only mode is on by default; the module bails early on shop loops.
		// Disable it so the picker actually runs in unit tests (no `is_product()`).
		update_option( 'freeman_core_cheapest_default_variation_pdp_only', 0 );
		update_option( 'freeman_core_cheapest_default_variation_respect_manual_defaults', 0 );
	}

	public function test_should_apply_filter_can_skip_picker(): void {
		add_filter(
			'freeman_core/cheapest_variation/should_apply',
			static function () {
				return false;
			}
		);

		$product = new TestCheapestVariableProduct( 1, $this->two_variations() );
		$result  = ( new Module() )->default_cheapest_variation( array( 'pa_color' => 'preset' ), $product );

		$this->assertSame( array( 'pa_color' => 'preset' ), $result );
	}

	public function test_should_apply_filter_receives_product_and_defaults(): void {
		$captured = array();
		add_filter(
			'freeman_core/cheapest_variation/should_apply',
			static function ( $apply, $product, $defaults ) use ( &$captured ) {
				$captured = array(
					'apply'    => $apply,
					'product'  => $product,
					'defaults' => $defaults,
				);
				return $apply;
			},
			10,
			3
		);

		$product = new TestCheapestVariableProduct( 7, $this->two_variations() );
		( new Module() )->default_cheapest_variation( array( 'seed' => 'x' ), $product );

		$this->assertTrue( $captured['apply'] );
		$this->assertSame( $product, $captured['product'] );
		$this->assertSame( array( 'seed' => 'x' ), $captured['defaults'] );
	}

	public function test_chosen_filter_can_swap_picked_variation(): void {
		add_filter(
			'freeman_core/cheapest_variation/chosen',
			static function ( $picked, $product, $variations ) {
				foreach ( $variations as $v ) {
					if ( 12 === $v['variation_id'] ) {
						return $v;
					}
				}
				return $picked;
			},
			10,
			3
		);

		$product = new TestCheapestVariableProduct( 9, $this->two_variations() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'blue', $result['pa_color'] );
	}

	public function test_chosen_filter_returning_null_leaves_defaults_untouched(): void {
		add_filter(
			'freeman_core/cheapest_variation/chosen',
			static function () {
				return null;
			}
		);

		$product = new TestCheapestVariableProduct( 9, $this->two_variations() );
		$result  = ( new Module() )->default_cheapest_variation( array( 'kept' => 'yes' ), $product );

		$this->assertSame( array( 'kept' => 'yes' ), $result );
	}

	public function test_no_listeners_picks_cheapest(): void {
		$product = new TestCheapestVariableProduct( 9, $this->two_variations() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'red', $result['pa_color'] );
	}

	private function two_variations(): array {
		return array(
			array(
				'variation_id'   => 11,
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'display_price'  => 10.00,
				'attributes'     => array( 'attribute_pa_color' => 'red' ),
			),
			array(
				'variation_id'   => 12,
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'display_price'  => 20.00,
				'attributes'     => array( 'attribute_pa_color' => 'blue' ),
			),
		);
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
final class TestCheapestVariableProduct extends \WC_Product {
	private int $id;
	private array $variations;

	public function __construct( int $id, array $variations ) {
		$this->id         = $id;
		$this->variations = $variations;
	}
	public function get_id() { return $this->id; }
	public function is_type( $t ) { return 'variable' === $t; }
	public function get_available_variations() { return $this->variations; }
}
