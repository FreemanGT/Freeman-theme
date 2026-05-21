<?php
/**
 * Shop Filters query bridge — applies the URL filter selection to the main
 * WooCommerce product query so a plain page load returns genuinely filtered
 * products (the storefront filters work on reload, with no AJAX required).
 *
 * Our URL convention is `filter_<taxonomy>=slug,slug` (e.g. filter_pa_color),
 * which is deliberately distinct from WooCommerce's own layered-nav
 * `filter_<attr>` vars — so WC never double-handles it and we keep one
 * canonical contract shared with Url_State and the panel render.
 *
 * Primary hook: `woocommerce_product_query_tax_query` (shop + product_cat /
 * attribute archives) — appends our clauses to WC's tax_query, preserving the
 * product_visibility clause WC already set. Secondary: a tightly-scoped
 * `pre_get_posts` for product *search* results, which WC's product_query path
 * does not always govern.
 *
 * tax_query_for() — the array building — is pure and unit-tested; the hook
 * wiring is integration / live QA.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Query bridge.
 */
final class Query {

	/**
	 * Wire the bridge. Only called from Module::boot() when the frontend flag
	 * is on.
	 */
	public function register() {
		add_filter( 'woocommerce_product_query_tax_query', array( $this, 'filter_wc_tax_query' ), 20, 2 );
		add_action( 'pre_get_posts', array( $this, 'apply_to_search_query' ), 20 );
	}

	/**
	 * Build a WP tax_query from a parsed filter selection: AND across facets
	 * (separate clauses), OR within a facet (operator IN). Matches by slug, so
	 * no term-id resolution is needed. Pure.
	 *
	 * @param array<string,string[]> $filters taxonomy => slugs.
	 * @return array WP tax_query (empty when nothing is selected).
	 */
	public static function tax_query_for( array $filters ) {
		$clauses = array();
		foreach ( $filters as $taxonomy => $slugs ) {
			$taxonomy = (string) $taxonomy;
			if ( '' === $taxonomy ) {
				continue;
			}
			$clean = array();
			foreach ( (array) $slugs as $slug ) {
				$slug = (string) $slug;
				if ( '' !== $slug ) {
					$clean[ $slug ] = true; // dedupe.
				}
			}
			if ( empty( $clean ) ) {
				continue;
			}
			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => array_keys( $clean ),
				'operator' => 'IN',
			);
		}

		if ( count( $clauses ) > 1 ) {
			$clauses['relation'] = 'AND';
		}
		return $clauses;
	}

	/**
	 * Append our clauses to WooCommerce's product-query tax_query (shop +
	 * attribute / category archives). Returning the array unchanged when nothing
	 * is selected keeps clean URLs untouched.
	 *
	 * @param array     $tax_query Existing tax_query (carries product_visibility).
	 * @param \WC_Query $query     WC query (unused).
	 * @return array
	 */
	public function filter_wc_tax_query( $tax_query, $query ) {
		$our = self::tax_query_for( $this->current_filters() );
		if ( empty( $our ) ) {
			return $tax_query;
		}
		$tax_query = is_array( $tax_query ) ? $tax_query : array();
		foreach ( $our as $key => $clause ) {
			if ( 'relation' === $key ) {
				continue; // WC's tax_query relation is already AND.
			}
			$tax_query[] = $clause;
		}
		return $tax_query;
	}

	/**
	 * Apply the selection to a product *search* main query — the case WC's
	 * product_query filter does not reliably cover. Tightly scoped: front end,
	 * main query, a product search WC's archive path hasn't already handled.
	 *
	 * @param \WP_Query $q Query.
	 */
	public function apply_to_search_query( $q ) {
		if ( is_admin() || ! $q instanceof \WP_Query || ! $q->is_main_query() || ! $q->is_search() ) {
			return;
		}
		// Shop / attribute / category archives are handled by filter_wc_tax_query.
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			return;
		}
		$post_type  = $q->get( 'post_type' );
		$is_product = ( 'product' === $post_type ) || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );
		if ( ! $is_product ) {
			return;
		}

		$our = self::tax_query_for( $this->current_filters() );
		if ( empty( $our ) ) {
			return;
		}

		$existing = $q->get( 'tax_query' );
		$merged   = is_array( $existing ) ? $existing : array();
		foreach ( $our as $key => $clause ) {
			if ( 'relation' === $key ) {
				continue;
			}
			$merged[] = $clause;
		}
		$q->set( 'tax_query', $merged );
	}

	/**
	 * Parse the current request's filter selection (Url_State sanitises).
	 *
	 * @return array<string,string[]>
	 */
	private function current_filters() {
		$params = is_array( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state  = Url_State::parse( $params );
		return isset( $state['filters'] ) && is_array( $state['filters'] ) ? $state['filters'] : array();
	}
}
