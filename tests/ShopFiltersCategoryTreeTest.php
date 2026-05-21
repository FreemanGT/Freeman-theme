<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Category_Tree;
use PHPUnit\Framework\TestCase;

/**
 * Pure category-tree build: nesting, count roll-up, zero-branch pruning,
 * sibling ordering, orphan-as-root.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Category_Tree
 */
final class ShopFiltersCategoryTreeTest extends TestCase {

	public function test_nests_and_rolls_up_counts(): void {
		// Shoes(7, own 0) > Sneakers(8, 5), Boots(9, 0).
		$tree = Category_Tree::build(
			array(
				array( 'term_id' => 7, 'parent' => 0, 'name' => 'Shoes', 'slug' => 'shoes', 'count' => 0, 'order' => 0 ),
				array( 'term_id' => 8, 'parent' => 7, 'name' => 'Sneakers', 'slug' => 'sneakers', 'count' => 5, 'order' => 0 ),
				array( 'term_id' => 9, 'parent' => 7, 'name' => 'Boots', 'slug' => 'boots', 'count' => 0, 'order' => 1 ),
			)
		);

		$this->assertCount( 1, $tree );
		$this->assertSame( 7, $tree[0]['term_id'] );
		$this->assertSame( 5, $tree[0]['count'] );           // rolled up from Sneakers.
		$this->assertCount( 1, $tree[0]['children'] );        // Boots (0) pruned.
		$this->assertSame( 8, $tree[0]['children'][0]['term_id'] );
	}

	public function test_prunes_branches_that_roll_up_to_zero(): void {
		$tree = Category_Tree::build(
			array(
				array( 'term_id' => 1, 'parent' => 0, 'name' => 'A', 'slug' => 'a', 'count' => 0, 'order' => 0 ),
				array( 'term_id' => 2, 'parent' => 1, 'name' => 'B', 'slug' => 'b', 'count' => 0, 'order' => 0 ),
			)
		);

		$this->assertSame( array(), $tree );
	}

	public function test_orders_siblings_by_order_then_name(): void {
		$tree = Category_Tree::build(
			array(
				array( 'term_id' => 1, 'parent' => 0, 'name' => 'Zebra', 'slug' => 'z', 'count' => 1, 'order' => 0 ),
				array( 'term_id' => 2, 'parent' => 0, 'name' => 'Apple', 'slug' => 'a', 'count' => 1, 'order' => 0 ),
				array( 'term_id' => 3, 'parent' => 0, 'name' => 'First', 'slug' => 'f', 'count' => 1, 'order' => -5 ),
			)
		);

		$ids = array_map( static fn( $n ) => $n['term_id'], $tree );
		$this->assertSame( array( 3, 2, 1 ), $ids ); // order -5 first, then Apple, Zebra.
	}

	public function test_orphan_parent_becomes_root(): void {
		// Parent 99 not in the input → node roots itself (subtree-friendly).
		$tree = Category_Tree::build(
			array(
				array( 'term_id' => 8, 'parent' => 99, 'name' => 'Sneakers', 'slug' => 'sneakers', 'count' => 3, 'order' => 0 ),
			)
		);

		$this->assertCount( 1, $tree );
		$this->assertSame( 8, $tree[0]['term_id'] );
		$this->assertSame( 3, $tree[0]['count'] );
	}
}
