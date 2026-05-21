<?php
/**
 * Shop Filters facet engine — the pure set-algebra core.
 *
 * Given the in-context product universe, an inverted index (taxonomy → term →
 * product ids) and the active filter selection, it computes:
 *   - the filtered product set for the grid (AND across facets, OR within a facet);
 *   - per-facet term availability + counts, computed with SELF-EXCLUSION.
 *
 * Self-exclusion is the rule that prevents the classic faceted-search bug: a
 * facet's own availability/counts are computed from every OTHER active facet but
 * NOT its own selection — so picking "Red" still leaves "Blue"/"Green" visible
 * (you can change or add to your colour choice). Without it, selecting one value
 * drops every sibling to zero and hides them.
 *
 * Pure: no WordPress, no $wpdb. The caller loads the index slice and hands it in.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Facet engine.
 */
final class Facet_Engine {

	/**
	 * Compute the filtered product set and per-facet availability.
	 *
	 * @param int[]                                  $base             Product ids in context (the universe).
	 * @param array<string,array<int,int[]>>         $postings         taxonomy => term_id => product ids.
	 * @param array<string,int[]>                    $active           taxonomy => selected term ids (OR within, AND across).
	 * @param string[]                               $facet_taxonomies Taxonomies to expose as facets.
	 * @return array{products:int[],facets:array<string,array<int,int>>}
	 *               'products': filtered product ids.
	 *               'facets':   taxonomy => [ term_id => count ] for terms with count > 0
	 *                           (taxonomy omitted entirely when it has no available term — hide-empty-facet).
	 */
	public static function compute( array $base, array $postings, array $active, array $facet_taxonomies ) {
		$base = array_values( array_unique( array_map( 'intval', $base ) ) );

		// Matched product set per active facet (OR within), keyed by id for O(1) lookup.
		$matched = array();
		foreach ( $active as $taxonomy => $term_ids ) {
			$term_ids = array_map( 'intval', (array) $term_ids );
			$term_ids = array_filter( $term_ids );
			if ( empty( $term_ids ) ) {
				continue;
			}
			$union = array();
			foreach ( $term_ids as $term_id ) {
				if ( empty( $postings[ $taxonomy ][ $term_id ] ) ) {
					continue;
				}
				foreach ( $postings[ $taxonomy ][ $term_id ] as $product_id ) {
					$union[ (int) $product_id ] = true;
				}
			}
			$matched[ $taxonomy ] = $union;
		}

		// Filtered grid set = base ∩ every active facet.
		$products = self::intersect( $base, $matched, null );

		// Per-facet availability + counts, self-excluded.
		$facets = array();
		foreach ( $facet_taxonomies as $taxonomy ) {
			if ( empty( $postings[ $taxonomy ] ) ) {
				continue;
			}
			$set    = array_flip( self::intersect( $base, $matched, $taxonomy ) ); // exclude this facet's own selection.
			$counts = array();
			foreach ( $postings[ $taxonomy ] as $term_id => $product_ids ) {
				$count = 0;
				foreach ( $product_ids as $product_id ) {
					if ( isset( $set[ (int) $product_id ] ) ) {
						++$count;
					}
				}
				if ( $count > 0 ) {
					$counts[ (int) $term_id ] = $count;
				}
			}
			if ( ! empty( $counts ) ) {
				$facets[ $taxonomy ] = $counts;
			}
		}

		return array(
			'products' => $products,
			'facets'   => $facets,
		);
	}

	/**
	 * Intersect the base list with every matched facet set, optionally skipping
	 * one facet (the self-exclusion case).
	 *
	 * @param int[]                       $base    Base product ids.
	 * @param array<string,array<int,bool>> $matched Facet => (product id => true).
	 * @param string|null                 $except  Facet to skip, or null.
	 * @return int[]
	 */
	private static function intersect( array $base, array $matched, $except ) {
		$result = array();
		foreach ( $base as $product_id ) {
			$keep = true;
			foreach ( $matched as $taxonomy => $set ) {
				if ( $taxonomy === $except ) {
					continue;
				}
				if ( ! isset( $set[ $product_id ] ) ) {
					$keep = false;
					break;
				}
			}
			if ( $keep ) {
				$result[] = $product_id;
			}
		}
		return $result;
	}
}
