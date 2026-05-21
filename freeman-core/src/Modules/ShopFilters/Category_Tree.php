<?php
/**
 * Shop Filters category tree builder.
 *
 * Turns a flat list of category nodes with per-category counts (from the facet
 * engine) into a pruned, ordered, nested tree:
 *   - counts roll up (a parent's count includes its descendants), so a parent
 *     stays visible when only its children have matches;
 *   - branches that roll up to zero are pruned (req #3: only relevant categories);
 *   - a node whose parent isn't in the input is treated as a root, so passing a
 *     single category's subtree "just works" on a category archive.
 *
 * Roll-up sums direct counts, so a product assigned to BOTH a parent and a child
 * is counted once per assignment (a tiny, accepted over-count for display only).
 *
 * Pure: no WordPress.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Category tree.
 */
final class Category_Tree {

	/**
	 * Build the pruned, count-rolled-up, ordered tree.
	 *
	 * @param array $nodes Flat nodes, each: term_id, parent, name, slug, count, order.
	 * @return array Nested nodes, each with 'children' and the rolled-up 'count'.
	 */
	public static function build( array $nodes ) {
		$by_id       = array();
		$children_of = array();

		foreach ( $nodes as $node ) {
			$id = (int) ( $node['term_id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$by_id[ $id ] = array(
				'term_id' => $id,
				'parent'  => (int) ( $node['parent'] ?? 0 ),
				'name'    => (string) ( $node['name'] ?? '' ),
				'slug'    => (string) ( $node['slug'] ?? '' ),
				'count'   => (int) ( $node['count'] ?? 0 ),
				'order'   => (int) ( $node['order'] ?? 0 ),
			);
		}

		// A parent outside the input set roots the node (subtree-friendly).
		foreach ( $by_id as $id => $node ) {
			$parent = ( $node['parent'] && isset( $by_id[ $node['parent'] ] ) ) ? $node['parent'] : 0;
			$children_of[ $parent ][] = $id;
		}

		return self::build_level( 0, $by_id, $children_of );
	}

	/**
	 * Recursively build one level: attach children, roll up counts, prune zeros,
	 * sort by order then name.
	 *
	 * @param int   $parent_id   Parent term id (0 = roots).
	 * @param array $by_id       term_id => node.
	 * @param array $children_of parent_id => term_id[].
	 * @return array
	 */
	private static function build_level( $parent_id, array $by_id, array $children_of ) {
		if ( empty( $children_of[ $parent_id ] ) ) {
			return array();
		}

		$nodes = array();
		foreach ( $children_of[ $parent_id ] as $id ) {
			$node             = $by_id[ $id ];
			$node['children'] = self::build_level( $id, $by_id, $children_of );

			$child_sum = 0;
			foreach ( $node['children'] as $child ) {
				$child_sum += $child['count'];
			}
			$node['count'] += $child_sum;

			if ( $node['count'] > 0 ) {
				$nodes[] = $node;
			}
		}

		usort(
			$nodes,
			static function ( $a, $b ) {
				return ( $a['order'] <=> $b['order'] ) ?: strcmp( $a['name'], $b['name'] );
			}
		);

		return $nodes;
	}
}
