<?php
/**
 * Shop Filters query builder — the glue between the index and the pure facet
 * engine, plus the response shaping the AJAX endpoint and the shortcode emit.
 *
 * query() is integration code: it resolves the page context, loads the index
 * slice via Index_Repository, hands it to Facet_Engine, and shapes the result
 * for the wire. Two seams are split out as PURE statics so the request →
 * active-selection mapping and the engine-counts → facets[] shaping are
 * unit-testable without a WordPress bootstrap:
 *   - resolve_active(): selected slugs → term-id selection (drops unknowns);
 *   - shape_facets():   engine term counts + term display data → facets[].
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Query builder.
 */
final class Query_Builder {

	/**
	 * Index repository.
	 *
	 * @var Index_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param Index_Repository|null $repo Repository (injected for tests).
	 */
	public function __construct( Index_Repository $repo = null ) {
		$this->repo = $repo ? $repo : new Index_Repository();
	}

	/* -----------------------------------------------------------------
	 * Pure seams (unit-tested)
	 * ----------------------------------------------------------------- */

	/**
	 * Map a parsed slug selection to the term-id selection the facet engine
	 * consumes. Unknown slugs (not in the resolution map) are dropped, ids are
	 * deduped, and a taxonomy left with no resolvable term is omitted. Pure.
	 *
	 * @param array<string,string[]>          $filters         taxonomy => selected slugs.
	 * @param array<string,array<string,int>> $slug_to_term_id taxonomy => (slug => term id).
	 * @return array<string,int[]> taxonomy => term ids.
	 */
	public static function resolve_active( array $filters, array $slug_to_term_id ) {
		$active = array();
		foreach ( $filters as $taxonomy => $slugs ) {
			$taxonomy = (string) $taxonomy;
			$map      = isset( $slug_to_term_id[ $taxonomy ] ) && is_array( $slug_to_term_id[ $taxonomy ] )
				? $slug_to_term_id[ $taxonomy ]
				: array();
			$ids = array();
			foreach ( (array) $slugs as $slug ) {
				$slug = (string) $slug;
				if ( isset( $map[ $slug ] ) ) {
					$ids[ (int) $map[ $slug ] ] = true;
				}
			}
			if ( ! empty( $ids ) ) {
				$active[ $taxonomy ] = array_keys( $ids );
			}
		}
		return $active;
	}

	/**
	 * Shape the engine's per-facet term counts into the wire `facets[]` array.
	 * Only terms the engine returned (count > 0 after self-exclusion + context)
	 * appear — hide-zero is therefore reflected here. Terms are ordered by the
	 * term-index `order` then name; the selected slugs are flagged. Pure.
	 *
	 * @param array $facet_defs    Ordered visible facet defs; each carries
	 *                             'taxonomy', 'type' and an injected 'label'.
	 * @param array $engine_facets taxonomy => (term_id => count).
	 * @param array $term_index    taxonomy => (term_id => ['slug','name','order']).
	 * @param array $active_slugs  taxonomy => selected slugs (for 'selected').
	 * @return array<int,array<string,mixed>> facets[].
	 */
	public static function shape_facets( array $facet_defs, array $engine_facets, array $term_index, array $active_slugs ) {
		$facets = array();

		foreach ( $facet_defs as $def ) {
			$taxonomy = isset( $def['taxonomy'] ) ? (string) $def['taxonomy'] : '';
			if ( '' === $taxonomy || empty( $engine_facets[ $taxonomy ] ) ) {
				continue; // hide-empty-facet (no available term in context).
			}

			$selected = array();
			foreach ( (array) ( $active_slugs[ $taxonomy ] ?? array() ) as $slug ) {
				$selected[ (string) $slug ] = true;
			}

			$meta  = isset( $term_index[ $taxonomy ] ) && is_array( $term_index[ $taxonomy ] ) ? $term_index[ $taxonomy ] : array();
			$terms = array();
			foreach ( $engine_facets[ $taxonomy ] as $term_id => $count ) {
				$term_id = (int) $term_id;
				$info    = isset( $meta[ $term_id ] ) ? $meta[ $term_id ] : array();
				$slug    = (string) ( $info['slug'] ?? '' );
				if ( '' === $slug ) {
					continue; // a term we can't address by slug can't be a checkbox.
				}
				$terms[] = array(
					'slug'     => $slug,
					'label'    => (string) ( $info['name'] ?? $slug ),
					'count'    => (int) $count,
					'selected' => isset( $selected[ $slug ] ),
					'order'    => (int) ( $info['order'] ?? 0 ),
				);
			}

			if ( empty( $terms ) ) {
				continue;
			}

			usort(
				$terms,
				static function ( $a, $b ) {
					return ( $a['order'] <=> $b['order'] ) ?: strcmp( $a['label'], $b['label'] );
				}
			);
			// 'order' was only needed for sorting — drop it from the wire shape.
			$terms = array_map(
				static function ( $term ) {
					unset( $term['order'] );
					return $term;
				},
				$terms
			);

			$facets[] = array(
				'taxonomy' => $taxonomy,
				'label'    => (string) ( $def['label'] ?? $taxonomy ),
				'type'     => (string) ( $def['type'] ?? 'checkbox' ),
				'terms'    => $terms,
				'hidden'   => false,
			);
		}

		return $facets;
	}

	/* -----------------------------------------------------------------
	 * Integration entry point (live QA)
	 * ----------------------------------------------------------------- */

	/**
	 * Run a filter query and build the full response payload.
	 *
	 * @param array $request Raw request params (context, context_id, filter_* …).
	 * @return array{facets:array,category_tree:array,count:int,pagination:array,url:string}
	 */
	public function query( array $request ) {
		$state       = Url_State::parse( $request );
		$context_id  = isset( $request['context_id'] ) ? (int) $request['context_id'] : 0;
		$filters     = $state['filters'];

		// Mirror the storefront: when the store hides out-of-stock items, the
		// grid excludes them, so the facet base + counts must too — otherwise a
		// value backed only by a hidden out-of-stock product shows "(1)" but the
		// filtered grid is empty.
		$hide_oos = ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) );

		// Base universe: a category page expands to the queried term + all its
		// descendants (the index stores only directly-assigned product_cat rows);
		// the shop page is every indexed product.
		if ( $context_id > 0 ) {
			$term_ids = array_merge( array( $context_id ), $this->descendant_category_ids( $context_id ) );
			$base     = $this->repo->product_ids_in_terms( 'product_cat', $term_ids, $hide_oos );
		} else {
			$base = $this->repo->all_product_ids( $hide_oos );
		}

		// Count attribute values by product-level presence within the base (the
		// base already excludes hidden out-of-stock products), matching how
		// WooCommerce's tax_query matches a product to a term.
		$postings   = $this->repo->postings_for_products( $base, false );
		$available  = $this->repo->available_taxonomies();
		$facet_defs = Facet_Config::resolve( $available, $context_id );

		// Checkbox facets only in 6.3a — the category-tree facet renders in 6.3b.
		$facet_defs = array_values(
			array_filter(
				$facet_defs,
				static function ( $def ) {
					return isset( $def['type'] ) && 'category' !== $def['type'];
				}
			)
		);
		$facet_taxonomies = array();
		foreach ( $facet_defs as &$def ) {
			$def['label']       = $this->taxonomy_label( (string) $def['taxonomy'] );
			$facet_taxonomies[] = (string) $def['taxonomy'];
		}
		unset( $def );

		$slug_to_term_id = $this->resolve_slug_map( $filters );
		$active          = self::resolve_active( $filters, $slug_to_term_id );

		$computed   = Facet_Engine::compute( $base, $postings, $active, $facet_taxonomies );
		$term_index = $this->build_term_index( $computed['facets'] );
		$facets     = self::shape_facets( $facet_defs, $computed['facets'], $term_index, $filters );

		$count    = count( $computed['products'] );
		$per_page = $this->products_per_page();
		$paged    = max( 1, (int) $state['paged'] );

		return array(
			'facets'        => $facets,
			'category_tree' => array(), // populated in 6.3b.
			'count'         => $count,
			'pagination'    => array(
				'current'     => $paged,
				'total_pages' => $per_page > 0 ? (int) ceil( $count / $per_page ) : 1,
				'next_url'    => $this->page_url( $context_id, $state, $paged + 1 ),
			),
			'url'           => $this->page_url( $context_id, $state, $paged ),
		);
	}

	/* -----------------------------------------------------------------
	 * Integration helpers
	 * ----------------------------------------------------------------- */

	/**
	 * Descendant product_cat term ids (cached by WP core's term-children cache).
	 *
	 * @param int $category_id Category term id.
	 * @return int[]
	 */
	private function descendant_category_ids( $category_id ) {
		$children = get_term_children( (int) $category_id, 'product_cat' );
		if ( is_wp_error( $children ) || empty( $children ) ) {
			return array();
		}
		return array_map( 'intval', $children );
	}

	/**
	 * Resolve the slug → term-id map for the selected filters, one WP lookup per
	 * slug. Keeps the pure resolve_active() free of WordPress.
	 *
	 * @param array<string,string[]> $filters taxonomy => slugs.
	 * @return array<string,array<string,int>>
	 */
	private function resolve_slug_map( array $filters ) {
		$map = array();
		foreach ( $filters as $taxonomy => $slugs ) {
			$taxonomy = (string) $taxonomy;
			foreach ( (array) $slugs as $slug ) {
				$slug = (string) $slug;
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$map[ $taxonomy ][ $slug ] = (int) $term->term_id;
				}
			}
		}
		return $map;
	}

	/**
	 * Display metadata (slug, name, menu order) for every term the engine
	 * returned, so shape_facets() can render and order checkboxes.
	 *
	 * @param array $engine_facets taxonomy => (term_id => count).
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private function build_term_index( array $engine_facets ) {
		$index = array();
		foreach ( $engine_facets as $taxonomy => $counts ) {
			$taxonomy = (string) $taxonomy;
			foreach ( array_keys( $counts ) as $term_id ) {
				$term_id = (int) $term_id;
				$term    = get_term( $term_id, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$index[ $taxonomy ][ $term_id ] = array(
					'slug'  => (string) $term->slug,
					'name'  => (string) $term->name,
					'order' => (int) get_term_meta( $term_id, 'order', true ),
				);
			}
		}
		return $index;
	}

	/**
	 * Human label for a facet taxonomy (attribute label for pa_*, taxonomy
	 * label otherwise).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	private function taxonomy_label( $taxonomy ) {
		if ( 0 === strpos( $taxonomy, 'pa_' ) && function_exists( 'wc_attribute_label' ) ) {
			return (string) wc_attribute_label( $taxonomy );
		}
		$object = get_taxonomy( $taxonomy );
		if ( $object && isset( $object->labels->singular_name ) ) {
			return (string) $object->labels->singular_name;
		}
		return (string) $taxonomy;
	}

	/**
	 * Products-per-page for the advisory total_pages count. Reads the site's
	 * posts-per-page setting; the swapped grid is the front-end URL itself, so
	 * WooCommerce remains the source of truth for actual pagination — this only
	 * sizes the count shown in the panel.
	 *
	 * @return int
	 */
	private function products_per_page() {
		$per_page = (int) get_option( 'posts_per_page', 12 );
		return $per_page > 0 ? $per_page : 12;
	}

	/**
	 * Build the filtered front-end URL for a context + state at a given page.
	 *
	 * @param int   $context_id Category term id (0 = shop).
	 * @param array $state      Parsed URL state.
	 * @param int   $paged      Page number.
	 * @return string
	 */
	private function page_url( $context_id, array $state, $paged ) {
		$base = '';
		if ( $context_id > 0 ) {
			$link = get_term_link( (int) $context_id, 'product_cat' );
			$base = is_wp_error( $link ) ? '' : (string) $link;
		}
		if ( '' === $base ) {
			$base = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' );
		}

		$state['paged'] = (int) $paged;
		$params         = Url_State::serialize( $state );
		return empty( $params ) ? $base : add_query_arg( $params, $base );
	}
}
