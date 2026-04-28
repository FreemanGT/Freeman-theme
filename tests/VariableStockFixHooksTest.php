<?php
declare(strict_types=1);

// Reuse the WC_Product shim from the ProductFeed snapshot fixture — see
// CheapestVariationHooksTest.php for the rationale.
require_once __DIR__ . '/snapshots/__fixtures__/wc_product_stub.php';

use Freeman\Core\Modules\VariableStockFix\Module;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freeman\Core\Modules\VariableStockFix\Module
 */
final class VariableStockFixHooksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_should_check_filter_short_circuits_evaluation(): void {
		$received = array();
		add_filter(
			'freeman_core/variable_stock_fix/should_check',
			static function ( $check, $product ) use ( &$received ) {
				$received[] = array(
					'check'   => $check,
					'product' => $product,
				);
				return false;
			},
			10,
			2
		);

		$product = new TestVsfVariableProduct( 99 );
		$result  = ( new Module() )->maybe_uncheck_manage_stock( $product );

		$this->assertCount( 1, $received );
		$this->assertTrue( $received[0]['check'] );
		$this->assertSame( $product, $received[0]['product'] );

		$this->assertFalse( $result['changed'] );
		$this->assertSame( 'skipped by freeman_core/variable_stock_fix/should_check', $result['reason'] );
	}

	public function test_should_check_filter_default_true_lets_evaluation_proceed(): void {
		// No listener — falls through to the existing manage_stock check below.
		$product = new TestVsfVariableProduct( 99 ); // manage_stock=false stub

		$result = ( new Module() )->maybe_uncheck_manage_stock( $product );

		$this->assertFalse( $result['changed'] );
		// Must reach the next branch, not the should_check short-circuit.
		$this->assertSame( 'parent manage_stock already off', $result['reason'] );
	}

	public function test_should_check_filter_skips_non_variable_early(): void {
		// Even though we set the filter to false, the early "not variable"
		// guard runs first — verifying the filter doesn't fire on simple
		// products and saves listeners from having to type-check themselves.
		$fired = 0;
		add_filter(
			'freeman_core/variable_stock_fix/should_check',
			static function ( $v ) use ( &$fired ) {
				++$fired;
				return $v;
			}
		);

		$result = ( new Module() )->maybe_uncheck_manage_stock( new \WC_Product() ); // type=simple stub

		$this->assertSame( 0, $fired );
		$this->assertSame( 'not variable', $result['reason'] );
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
final class TestVsfVariableProduct extends \WC_Product {
	private int $id;
	public function __construct( int $id ) {
		$this->id = $id;
	}
	public function get_id() { return $this->id; }
	public function get_type() { return 'variable'; }
	public function get_manage_stock() { return false; }
	public function get_children() { return array(); }
}
