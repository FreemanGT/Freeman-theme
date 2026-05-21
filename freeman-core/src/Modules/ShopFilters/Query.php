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
	 * Index repository (lazy).
	 *
	 * @var Index_Repository|null
	 */
	private $repo = null;

	/**
	 * Cached "does the index have any rows" check.
	 *
	 * @var bool|null
	 */
	private $index_has_data = null;

	/**
	 * Wire the bridge. Only called from Module::boot() when the frontend flag
	 * is on.
	 */
	public function register() {
		add_filter( 'woocommerce_product_query_tax_query', array( $this, 'filter_wc_tax_query' ), 20, 2 );
		add_action( 'pre_get_posts', array( $this, 'apply_to_search_query' ), 20 );
		add_action( 'pre_get_posts', array( $this, 'apply_instock_post_in' ), 20 );
		add_filter( 'posts_clauses', array( $this, 'filter_price_clauses' ), 20, 2 );
		add_filter( 'woocommerce_default_catalog_orderby', array( $this, 'default_catalog_orderby' ) );
		add_filter( 'the_posts', array( $this, 'enforce_filters_on_search' ), 10, 2 );
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
	 * Constrain the storefront grid to the index's IN-STOCK product set for the
	 * active attribute selection, so a size whose variations are all sold out no
	 * longer matches (WooCommerce's own tax_query matches the parent's assigned
	 * terms, which include out-of-stock variation options — the bug this fixes).
	 *
	 * Resolves each facet's slugs to the index's in-stock product ids (OR within a
	 * facet), intersects across facets (AND), and sets post__in. Only engages when
	 * the index actually has rows — otherwise the woocommerce_product_query_tax_query
	 * bridge remains the (product-level) fallback, so a site with indexing off is
	 * not left with an empty grid.
	 *
	 * @param \WP_Query $q Query.
	 */
	public function apply_instock_post_in( $q ) {
		if ( is_admin() || ! $q instanceof \WP_Query || ! $q->is_main_query() ) {
			return;
		}
		if ( ! $this->is_product_listing( $q ) ) {
			return;
		}
		$filters = $this->current_filters();
		if ( empty( $filters ) ) {
			return;
		}
		if ( ! $this->index_has_data() ) {
			return; // Fall back to the tax_query bridge when the index is empty.
		}

		$ids = $this->instock_product_ids( $filters );
		if ( empty( $ids ) ) {
			$ids = array( 0 ); // WP_Query treats an empty post__in as "no constraint"; force zero results.
		}

		$existing = $q->get( 'post__in' );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$ids = array_values( array_intersect( array_map( 'intval', $existing ), $ids ) );
			if ( empty( $ids ) ) {
				$ids = array( 0 );
			}
		}
		$q->set( 'post__in', $ids );
	}

	/**
	 * Safety net for search-results pages whose grid is supplied by a search plugin
	 * (e.g. Advanced Woo Search) that bypasses our query-level constraints: enforce
	 * the active attribute selection on the FINAL post list, dropping any product
	 * that isn't in the index's in-stock set for the selection. Runs only on the
	 * front-end main *search* query, only when filters are active and the index has
	 * data — so a normal archive (already constrained by post__in) is untouched.
	 *
	 * @param array     $posts Posts from the query.
	 * @param \WP_Query $query Query.
	 * @return array
	 */
	public function enforce_filters_on_search( $posts, $query ) {
		if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() || ! $query->is_search() ) {
			return $posts;
		}
		if ( empty( $posts ) ) {
			return $posts;
		}
		$post_type  = $query->get( 'post_type' );
		$is_product = ( 'product' === $post_type ) || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );
		if ( ! $is_product ) {
			return $posts;
		}
		$filters = $this->current_filters();
		if ( empty( $filters ) || ! $this->index_has_data() ) {
			return $posts;
		}
		return self::filter_posts_to_ids( $posts, $this->instock_product_ids( $filters ) );
	}

	/**
	 * Keep only the posts whose ID is in the allowed set, preserving order. An
	 * empty allow-list means nothing matches. Pure.
	 *
	 * @param array $posts       Post objects (or ids).
	 * @param int[] $allowed_ids Allowed product ids.
	 * @return array
	 */
	public static function filter_posts_to_ids( array $posts, array $allowed_ids ) {
		if ( empty( $allowed_ids ) ) {
			return array();
		}
		$allow = array_flip( array_map( 'intval', $allowed_ids ) );
		$kept  = array();
		foreach ( $posts as $post ) {
			$pid = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
			if ( isset( $allow[ $pid ] ) ) {
				$kept[] = $post;
			}
		}
		return $kept;
	}

	/**
	 * Intersect a list of id-sets (AND across facets); each set is the OR-union
	 * within one facet. An empty set short-circuits to no matches. Pure.
	 *
	 * @param array<int,int[]> $sets Per-facet product-id sets.
	 * @return int[]
	 */
	public static function intersect_id_sets( array $sets ) {
		$result = null;
		foreach ( $sets as $set ) {
			$set = array_map( 'intval', (array) $set );
			if ( null === $result ) {
				$result = array_values( array_unique( $set ) );
			} else {
				$result = array_values( array_intersect( $result, $set ) );
			}
			if ( empty( $result ) ) {
				return array();
			}
		}
		return null === $result ? array() : $result;
	}

	/**
	 * Resolve the active attribute selection to the index's in-stock product ids.
	 *
	 * @param array<string,string[]> $filters taxonomy => slugs.
	 * @return int[]
	 */
	private function instock_product_ids( array $filters ) {
		$in_stock_only = ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) );
		$repo          = $this->repo();
		$sets          = array();
		foreach ( $filters as $taxonomy => $slugs ) {
			$taxonomy = (string) $taxonomy;
			$term_ids = array();
			foreach ( (array) $slugs as $slug ) {
				$term = get_term_by( 'slug', (string) $slug, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$term_ids[] = (int) $term->term_id;
				}
			}
			$sets[] = empty( $term_ids ) ? array() : $repo->product_ids_in_terms( $taxonomy, $term_ids, $in_stock_only );
		}
		return self::intersect_id_sets( $sets );
	}

	/**
	 * Whether the index has any rows (cached). When empty, in-stock post__in is
	 * skipped in favour of the tax_query fallback.
	 *
	 * @return bool
	 */
	private function index_has_data() {
		if ( null === $this->index_has_data ) {
			$this->index_has_data = $this->repo()->count_rows() > 0;
		}
		return $this->index_has_data;
	}

	/**
	 * Lazy index repository.
	 *
	 * @return Index_Repository
	 */
	private function repo() {
		if ( null === $this->repo ) {
			$this->repo = new Index_Repository();
		}
		return $this->repo;
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
