<?php
declare(strict_types=1);

require_once __DIR__ . '/snapshots/__fixtures__/wc_product_stub.php';

use Freeman\Core\Modules\RestockNotify\Frontend;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) {
		return $GLOBALS['fr_wc_get_product_return'] ?? new \WC_Product();
	}
}

/**
 * Configurable variation stub — drives each of the 6 cases the modern
 * Frontend's `is_variation_truly_oos()` checks. Mirrors the legacy
 * `WC_Product_Variation` surface the method touches.
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
final class TestVariation extends \WC_Product {
	public bool $managing_stock     = false;
	public ?int $stock_quantity     = null;
	public bool $backorders          = false;
	public string $stock_status      = 'instock';
	public bool $is_purchasable     = true;
	public int $max_purchase_qty    = -1;
	public int $id                   = 100;
	public function managing_stock() { return $this->managing_stock; }
	public function get_stock_quantity() { return $this->stock_quantity; }
	public function backorders_allowed() { return $this->backorders; }
	public function get_stock_status() { return $this->stock_status; }
	public function get_id() { return $this->id; }
	public function is_purchasable() { return $this->is_purchasable; }
	public function get_max_purchase_quantity() { return $this->max_purchase_qty; }
}

/**
 * Configurable parent product — same surface plus get_children().
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
final class TestVariableParent extends \WC_Product {
	public bool $managing_stock = false;
	public ?int $stock_quantity = null;
	public bool $backorders     = false;
	public function managing_stock() { return $this->managing_stock; }
	public function get_stock_quantity() { return $this->stock_quantity; }
	public function backorders_allowed() { return $this->backorders; }
}

/**
 * @covers \Freeman\Core\Modules\RestockNotify\Frontend
 *
 * One test per case in the 6-case ladder at Frontend::is_variation_truly_oos().
 * The ladder was copied verbatim from legacy/class-rsn-frontend.php:513-564 —
 * these tests lock the per-case behavior so a future edit (intentional or
 * accidental) surfaces as a clear failure.
 */
final class RestockNotifyVariationOosTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_post_meta'] = array();
	}

	private function call_ladder( $variation, $parent ): bool {
		// is_variation_truly_oos is private; call via Reflection.
		$frontend = new Frontend();
		$ref      = new \ReflectionMethod( $frontend, 'is_variation_truly_oos' );
		$ref->setAccessible( true );
		return (bool) $ref->invoke( $frontend, $variation, $parent );
	}

	public function test_case_1a_variation_managing_own_stock_zero_no_backorders_is_oos(): void {
		$v                   = new TestVariation();
		$v->managing_stock    = true;
		$v->stock_quantity    = 0;
		$v->backorders        = false;
		$p                    = new TestVariableParent();

		$this->assertTrue( $this->call_ladder( $v, $p ) );
	}

	public function test_case_1b_variation_managing_own_stock_with_qty_is_in_stock(): void {
		$v                   = new TestVariation();
		$v->managing_stock    = true;
		$v->stock_quantity    = 5;
		$v->backorders        = false;
		$p                    = new TestVariableParent();

		$this->assertFalse( $this->call_ladder( $v, $p ) );
	}

	public function test_case_1c_variation_managing_own_stock_zero_with_backorders_is_in_stock(): void {
		$v                   = new TestVariation();
		$v->managing_stock    = true;
		$v->stock_quantity    = 0;
		$v->backorders        = true;
		$p                    = new TestVariableParent();

		$this->assertFalse( $this->call_ladder( $v, $p ) );
	}

	public function test_case_2_variation_stock_status_outofstock_is_oos(): void {
		$v                  = new TestVariation();
		$v->managing_stock   = false;
		$v->stock_status     = 'outofstock';
		$p                   = new TestVariableParent();

		$this->assertTrue( $this->call_ladder( $v, $p ) );
	}

	public function test_case_3_raw_meta_outofstock_is_oos_even_when_status_says_otherwise(): void {
		$v                  = new TestVariation();
		$v->id               = 555;
		$v->managing_stock   = false;
		$v->stock_status     = 'instock'; // status getter says in stock
		$GLOBALS['fr_post_meta'][555]['_stock_status'] = 'outofstock'; // raw meta says oos
		$p                   = new TestVariableParent();

		$this->assertTrue( $this->call_ladder( $v, $p ) );
	}

	public function test_case_4_parent_managing_stock_with_zero_qty_no_backorders_is_oos(): void {
		$v                  = new TestVariation();
		$v->managing_stock   = false;
		$v->stock_status     = 'instock';
		$v->is_purchasable   = true;
		$v->max_purchase_qty = 5;
		$p                   = new TestVariableParent();
		$p->managing_stock   = true;
		$p->stock_quantity   = 0;
		$p->backorders       = false;

		$this->assertTrue( $this->call_ladder( $v, $p ) );
	}

	public function test_case_5_variation_not_purchasable_is_oos(): void {
		$v                  = new TestVariation();
		$v->managing_stock   = false;
		$v->stock_status     = 'instock';
		$v->is_purchasable   = false;
		$v->max_purchase_qty = 5;
		$p                   = new TestVariableParent();

		$this->assertTrue( $this->call_ladder( $v, $p ) );
	}

	public function test_case_6_max_purchase_quantity_zero_is_oos(): void {
		$v                  = new TestVariation();
		$v->managing_stock   = false;
		$v->stock_status     = 'instock';
		$v->is_purchasable   = true;
		$v->max_purchase_qty = 0;
		$p                   = new TestVariableParent();

		$this->assertTrue( $this->call_ladder( $v, $p ) );
	}

	public function test_default_in_stock_when_no_case_triggers(): void {
		$v                  = new TestVariation();
		$v->managing_stock   = false;
		$v->stock_status     = 'instock';
		$v->is_purchasable   = true;
		$v->max_purchase_qty = -1; // -1 = unlimited per WC convention
		$p                   = new TestVariableParent();

		$this->assertFalse( $this->call_ladder( $v, $p ) );
	}
}
