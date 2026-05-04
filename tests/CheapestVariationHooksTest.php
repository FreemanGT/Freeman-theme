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
		$GLOBALS['fr_opts']      = array();
		$GLOBALS['fr_hooks']     = array();
		$GLOBALS['fr_post_meta'] = array();

		// PDP-only mode is on by default; the module bails early on shop loops.
		// Disable it so the picker actually runs in unit tests (no `is_product()`).
		update_option( 'freeman_core_cheapest_default_variation_pdp_only', 0 );
		update_option( 'freeman_core_cheapest_default_variation_respect_manual_defaults', 0 );

		// Wave 3.3: enable the strategy-selector flag by default for new tests.
		// Existing hook-tests are flag-agnostic — should_apply fires before the
		// flag gate, and chosen converges after both branches with identical
		// payload when default setting='cheapest' and no strategy listener.
		// The one flag-OFF test opts out via delete_option().
		update_option( 'freeman_core_cheapest_variation_strategy_enabled', 1 );
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

	/**
	 * Three variations where the cheapest is out of stock, the next is
	 * mid-priced and in stock, and the cheapest in-stock is last. Lets a test
	 * distinguish "cheapest" (returns the last) from "first_in_stock"
	 * (returns the middle one).
	 */
	private function three_variations_oos_first(): array {
		return array(
			array(
				'variation_id'   => 21,
				'is_in_stock'    => false,
				'is_purchasable' => false,
				'display_price'  => 5.00,
				'attributes'     => array( 'attribute_pa_color' => 'green' ),
			),
			array(
				'variation_id'   => 22,
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'display_price'  => 20.00,
				'attributes'     => array( 'attribute_pa_color' => 'red' ),
			),
			array(
				'variation_id'   => 23,
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'display_price'  => 10.00,
				'attributes'     => array( 'attribute_pa_color' => 'blue' ),
			),
		);
	}

	// --- Wave 3.3: strategy-selector tests ---------------------------------

	public function test_strategy_first_in_stock_picks_first_passing_variation_after_oos(): void {
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'first_in_stock' );

		$product = new TestCheapestVariableProduct( 31, $this->three_variations_oos_first() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'red', $result['pa_color'] );
	}

	public function test_strategy_setting_dispatches_cheapest(): void {
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'cheapest' );

		$product = new TestCheapestVariableProduct( 32, $this->three_variations_oos_first() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'blue', $result['pa_color'] );
	}

	public function test_strategy_filter_overrides_setting(): void {
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'cheapest' );
		add_filter(
			'freeman_core/cheapest_variation/strategy',
			static function () {
				return 'first_in_stock';
			}
		);

		$product = new TestCheapestVariableProduct( 33, $this->three_variations_oos_first() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'red', $result['pa_color'] );
	}

	public function test_strategy_meta_overrides_setting(): void {
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'cheapest' );
		$GLOBALS['fr_post_meta'][34] = array(
			'_freeman_cheapest_variation_strategy' => 'first_in_stock',
		);

		$product = new TestCheapestVariableProduct( 34, $this->three_variations_oos_first() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'red', $result['pa_color'] );
	}

	public function test_strategy_filter_overrides_meta(): void {
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'cheapest' );
		$GLOBALS['fr_post_meta'][35] = array(
			'_freeman_cheapest_variation_strategy' => 'first_in_stock',
		);
		add_filter(
			'freeman_core/cheapest_variation/strategy',
			static function () {
				return 'cheapest';
			}
		);

		$product = new TestCheapestVariableProduct( 35, $this->three_variations_oos_first() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'blue', $result['pa_color'] );
	}

	public function test_strategy_filter_invalid_value_falls_back_to_resolved_pre_filter(): void {
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'cheapest' );
		add_filter(
			'freeman_core/cheapest_variation/strategy',
			static function () {
				return 'bogus';
			}
		);

		$product = new TestCheapestVariableProduct( 36, $this->three_variations_oos_first() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		// Falls back to the resolved-pre-filter value ('cheapest'), not blindly
		// to the enum default. Cheapest in-stock here is 'blue'.
		$this->assertSame( 'blue', $result['pa_color'] );
	}

	public function test_flag_off_runs_legacy_cheapest_path_ignoring_strategy_setting(): void {
		delete_option( 'freeman_core_cheapest_variation_strategy_enabled' );
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'first_in_stock' );

		$product = new TestCheapestVariableProduct( 37, $this->three_variations_oos_first() );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		// Strategy setting is ignored under flag-OFF; legacy cheapest path runs.
		$this->assertSame( 'blue', $result['pa_color'] );
	}

	public function test_strategy_filter_receives_resolved_strategy_and_product(): void {
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'first_in_stock' );
		$captured = array();
		add_filter(
			'freeman_core/cheapest_variation/strategy',
			static function ( $strategy, $product ) use ( &$captured ) {
				$captured = array(
					'strategy' => $strategy,
					'product'  => $product,
				);
				return $strategy;
			},
			10,
			2
		);

		$product = new TestCheapestVariableProduct( 38, $this->three_variations_oos_first() );
		( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'first_in_stock', $captured['strategy'] );
		$this->assertSame( $product, $captured['product'] );
	}

	/**
	 * Pins the assumption that "first_in_stock" means first-in-the-order-WC
	 * returns from get_available_variations(). If WC ever changes that
	 * order, this test fails loudly instead of behavior shifting silently.
	 */
	public function test_first_in_stock_pins_wc_order_assumption(): void {
		update_option( 'freeman_core_cheapest_default_variation_strategy', 'first_in_stock' );

		// All three are in stock; 'first' must mean the array-order first,
		// which is the more expensive 'red' variation, not the cheaper 'blue'.
		$variations = array(
			array(
				'variation_id'   => 41,
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'display_price'  => 20.00,
				'attributes'     => array( 'attribute_pa_color' => 'red' ),
			),
			array(
				'variation_id'   => 42,
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'display_price'  => 10.00,
				'attributes'     => array( 'attribute_pa_color' => 'blue' ),
			),
			array(
				'variation_id'   => 43,
				'is_in_stock'    => true,
				'is_purchasable' => true,
				'display_price'  => 15.00,
				'attributes'     => array( 'attribute_pa_color' => 'green' ),
			),
		);

		$product = new TestCheapestVariableProduct( 39, $variations );
		$result  = ( new Module() )->default_cheapest_variation( array(), $product );

		$this->assertSame( 'red', $result['pa_color'] );
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
