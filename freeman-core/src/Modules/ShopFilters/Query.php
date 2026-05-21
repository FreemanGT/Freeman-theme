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
		add_filter( 'posts_clauses', array( $this, 'filter_price_clauses' ), 20, 2 );
		add_filter( 'woocommerce_default_catalog_orderby', array( $this, 'default_catalog_orderby' ) );
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
	 * Build the SQL WHERE fragment that keeps products whose price range overlaps
	 * ANY selected band (OR within the price facet). Reads min/max from the
	 * wc_product_meta_lookup row aliased as $alias. A band overlaps when the
	 * product's max_price >= band.min AND (open-ended top, or min_price <= band.max).
	 * Numbers only — no user strings reach the SQL. Pure.
	 *
	 * @param array  $bands Selected bands ([ ['min'=>float,'max'=>?float], … ]).
	 * @param string $alias wc_product_meta_lookup table alias.
	 * @return string SQL fragment (empty when no usable band).
	 */
	public static function price_where_sql( array $bands, $alias ) {
		$alias   = preg_replace( '/[^a-z0-9_]/i', '', (string) $alias );
		$clauses = array();
		foreach ( $bands as $band ) {
			if ( ! isset( $band['min'] ) ) {
				continue;
			}
			$min = (float) $band['min'];
			$max = isset( $band['max'] ) && null !== $band['max'] ? (float) $band['max'] : null;
			if ( null === $max ) {
				$clauses[] = sprintf( '%1$s.max_price >= %2$F', $alias, $min );
			} else {
				$clauses[] = sprintf( '( %1$s.max_price >= %2$F AND %1$s.min_price <= %3$F )', $alias, $min, $max );
			}
		}
		if ( empty( $clauses ) ) {
			return '';
		}
		return '( ' . implode( ' OR ', $clauses ) . ' )';
	}

	/**
	 * Join wc_product_meta_lookup and apply the selected price bands to the main
	 * product query (shop / category / attribute archives + product search). Uses
	 * the lookup table (decisions §5.2) so price is never duplicated.
	 *
	 * @param array     $clauses Posts clauses (join/where/…).
	 * @param \WP_Query $query   Query.
	 * @return array
	 */
	public function filter_price_clauses( $clauses, $query ) {
		if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return $clauses;
		}
		if ( ! $this->is_product_listing( $query ) ) {
			return $clauses;
		}
		$bands = $this->current_price_bands();
		if ( empty( $bands ) ) {
			return $clauses;
		}

		global $wpdb;
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
		$alias  = 'fsf_price';
		if ( false === strpos( (string) $clauses['join'], $alias ) ) {
			$clauses['join'] .= " LEFT JOIN {$lookup} {$alias} ON {$wpdb->posts}.ID = {$alias}.product_id ";
		}
		$where = self::price_where_sql( $bands, $alias );
		if ( '' !== $where ) {
			$clauses['where'] .= ' AND ' . $where;
		}
		return $clauses;
	}

	/**
	 * Default catalogue ordering: when the site sets a Shop Filters default sort,
	 * shop + category pages default to it unless the URL specifies an orderby.
	 *
	 * @param string $default WooCommerce's default orderby.
	 * @return string
	 */
	public function default_catalog_orderby( $default ) {
		$setting = (string) get_option( 'freeman_core_shop_filters_default_sort', '' );
		return in_array( $setting, Url_State::orderby_whitelist(), true ) ? $setting : $default;
	}

	/**
	 * Whether a query is the storefront product listing the price filter applies
	 * to (shop / product taxonomy archive, or a product search).
	 *
	 * @param \WP_Query $query Query.
	 * @return bool
	 */
	private function is_product_listing( $query ) {
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			return true;
		}
		if ( ! $query->is_search() ) {
			return false;
		}
		$post_type = $query->get( 'post_type' );
		return ( 'product' === $post_type ) || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );
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

	/**
	 * Parse the current request's selected price bands.
	 *
	 * @return array<int,array{min:float,max:?float}>
	 */
	private function current_price_bands() {
		$params = is_array( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state  = Url_State::parse( $params );
		return isset( $state['price_bands'] ) && is_array( $state['price_bands'] ) ? $state['price_bands'] : array();
	}
}
