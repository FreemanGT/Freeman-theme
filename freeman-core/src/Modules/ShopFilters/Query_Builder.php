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

			$meta       = isset( $term_index[ $taxonomy ] ) && is_array( $term_index[ $taxonomy ] ) ? $term_index[ $taxonomy ] : array();
			$terms      = array();
			$has_swatch = false;
			foreach ( $engine_facets[ $taxonomy ] as $term_id => $count ) {
				$term_id = (int) $term_id;
				$info    = isset( $meta[ $term_id ] ) ? $meta[ $term_id ] : array();
				$slug    = (string) ( $info['slug'] ?? '' );
				if ( '' === $slug ) {
					continue; // a term we can't address by slug can't be a checkbox.
				}
				$term = array(
					'slug'     => $slug,
					'label'    => (string) ( $info['name'] ?? $slug ),
					'count'    => (int) $count,
					'selected' => isset( $selected[ $slug ] ),
					'order'    => (int) ( $info['order'] ?? 0 ),
				);
				// Swatch data (read by build_term_index from term meta) makes this a
				// colour/image facet rather than a plain checkbox list.
				if ( ! empty( $info['color'] ) ) {
					$term['color'] = (string) $info['color'];
					$has_swatch    = true;
				}
				if ( ! empty( $info['image'] ) ) {
					$term['image'] = (string) $info['image'];
					$has_swatch    = true;
				}
				$terms[] = $term;
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
				'type'     => $has_swatch ? 'color' : (string) ( $def['type'] ?? 'checkbox' ),
				'terms'    => $terms,
				'hidden'   => false,
			);
		}

		return $facets;
	}

	/**
	 * Shape the engine's product_cat counts into the flat node list
	 * Category_Tree::build() consumes. Counts come from the facet engine; the
	 * display metadata (parent, name, slug, menu order) is looked up by the
	 * caller and passed in. A term with no metadata (deleted between index and
	 * read) is dropped. Pure.
	 *
	 * @param array $counts term_id => count.
	 * @param array $meta   term_id => ['parent','name','slug','order'].
	 * @return array<int,array<string,mixed>> flat nodes for Category_Tree::build().
	 */
	public static function shape_category_nodes( array $counts, array $meta ) {
		$nodes = array();
		foreach ( $counts as $term_id => $count ) {
			$term_id = (int) $term_id;
			if ( empty( $meta[ $term_id ] ) || ! is_array( $meta[ $term_id ] ) ) {
				continue;
			}
			$info    = $meta[ $term_id ];
			$nodes[] = array(
				'term_id' => $term_id,
				'parent'  => (int) ( $info['parent'] ?? 0 ),
				'name'    => (string) ( $info['name'] ?? '' ),
				'slug'    => (string) ( $info['slug'] ?? '' ),
				'count'   => (int) $count,
				'order'   => (int) ( $info['order'] ?? 0 ),
			);
		}
		return $nodes;
	}

	/**
	 * Turn a sorted list of upper bounds into contiguous price bands: the first
	 * runs from 0 to the first bound, each subsequent from the previous bound to
	 * the next, and the last is open-ended (max = null). Non-numeric / non-positive
	 * bounds are dropped; bounds are deduped and sorted. Pure.
	 *
	 * @param float[] $bounds Upper bounds, e.g. [50, 100, 200].
	 * @return array<int,array{min:float,max:?float}>
	 */
	public static function bands_from_bounds( array $bounds ) {
		$clean = array();
		foreach ( $bounds as $bound ) {
			if ( is_numeric( $bound ) && (float) $bound > 0 ) {
				$clean[ (string) ( (float) $bound ) ] = (float) $bound;
			}
		}
		$clean = array_values( $clean );
		sort( $clean );
		if ( empty( $clean ) ) {
			return array();
		}

		$bands = array();
		$lo    = 0.0;
		foreach ( $clean as $bound ) {
			$bands[] = array(
				'min' => $lo,
				'max' => $bound,
			);
			$lo = $bound;
		}
		$bands[] = array(
			'min' => $lo,
			'max' => null,
		);
		return $bands;
	}

	/**
	 * Auto-derive ~$count contiguous bands from 0 up to a rounded step, with the
	 * last band open-ended. Used when no explicit bands are configured. Pure.
	 *
	 * @param float $max   Highest price in the candidate set.
	 * @param int   $count Target band count.
	 * @return array<int,array{min:float,max:?float}>
	 */
	public static function auto_bands( $max, $count = 4 ) {
		$max   = (float) $max;
		$count = max( 1, (int) $count );
		if ( $max <= 0 ) {
			return array( array( 'min' => 0.0, 'max' => null ) );
		}

		$step = self::nice_round( $max / $count );
		$bounds = array();
		for ( $i = 1; $i < $count; $i++ ) {
			$bound = $step * $i;
			if ( $bound >= $max ) {
				break;
			}
			$bounds[] = $bound;
		}
		return self::bands_from_bounds( $bounds );
	}

	/**
	 * Round a step up to a "nice" 1/2/5 × power-of-ten value (50, 100, 250, …) so
	 * auto bands read cleanly. Pure.
	 *
	 * @param float $step Raw step.
	 * @return float
	 */
	public static function nice_round( $step ) {
		$step = (float) $step;
		if ( $step <= 1 ) {
			return 1.0;
		}
		$mag  = pow( 10, floor( log10( $step ) ) );
		$frac = $step / $mag;
		if ( $frac <= 1 ) {
			$nice = 1;
		} elseif ( $frac <= 2 ) {
			$nice = 2;
		} elseif ( $frac <= 5 ) {
			$nice = 5;
		} else {
			$nice = 10;
		}
		return (float) ( $nice * $mag );
	}

	/**
	 * Count how many products fall in each band (price range overlaps the band).
	 * Pure. Parallel to $bands.
	 *
	 * @param array $prices product_id => ['min'=>float,'max'=>float].
	 * @param array $bands  Bands.
	 * @return int[] Count per band, same order as $bands.
	 */
	public static function count_in_bands( array $prices, array $bands ) {
		$counts = array();
		foreach ( $bands as $band ) {
			$counts[] = count( self::ids_in_band( $prices, $band ) );
		}
		return $counts;
	}

	/**
	 * Product ids whose price range overlaps ANY of the selected bands (OR within
	 * the price facet). Pure.
	 *
	 * @param int[] $products Candidate product ids.
	 * @param array $prices   product_id => ['min'=>float,'max'=>float].
	 * @param array $selected Selected bands.
	 * @return int[]
	 */
	public static function filter_by_bands( array $products, array $prices, array $selected ) {
		if ( empty( $selected ) ) {
			return array_values( $products );
		}
		$keep = array();
		foreach ( $selected as $band ) {
			foreach ( self::ids_in_band( $prices, $band ) as $id ) {
				$keep[ $id ] = true;
			}
		}
		$result = array();
		foreach ( $products as $id ) {
			if ( isset( $keep[ (int) $id ] ) ) {
				$result[] = (int) $id;
			}
		}
		return $result;
	}

	/**
	 * Shape bands + counts into the wire price facet, dropping zero-count bands and
	 * flagging the selected ones. Pure.
	 *
	 * @param array $bands    Bands.
	 * @param int[] $counts   Count per band (parallel to $bands).
	 * @param array $selected Selected bands.
	 * @return array{bands:array<int,array<string,mixed>>}
	 */
	public static function shape_price_facet( array $bands, array $counts, array $selected ) {
		$selected_sigs = array();
		foreach ( $selected as $band ) {
			$selected_sigs[ self::band_signature( $band ) ] = true;
		}

		$out = array();
		foreach ( $bands as $i => $band ) {
			$count = isset( $counts[ $i ] ) ? (int) $counts[ $i ] : 0;
			if ( $count <= 0 ) {
				continue;
			}
			$out[] = array(
				'min'      => (float) $band['min'],
				'max'      => isset( $band['max'] ) && null !== $band['max'] ? (float) $band['max'] : null,
				'count'    => $count,
				'selected' => isset( $selected_sigs[ self::band_signature( $band ) ] ),
			);
		}
		return array( 'bands' => $out );
	}

	/**
	 * Stable signature for a band, used to match selected against candidate bands.
	 *
	 * @param array $band Band.
	 * @return string
	 */
	private static function band_signature( array $band ) {
		$min = (float) ( $band['min'] ?? 0 );
		$max = isset( $band['max'] ) && null !== $band['max'] ? (float) $band['max'] : null;
		return ( $min + 0 ) . ':' . ( null === $max ? '' : ( $max + 0 ) );
	}

	/**
	 * Ids whose price range overlaps a single band. Pure.
	 *
	 * @param array $prices product_id => ['min'=>float,'max'=>float].
	 * @param array $band   Band.
	 * @return int[]
	 */
	private static function ids_in_band( array $prices, array $band ) {
		$min = (float) ( $band['min'] ?? 0 );
		$max = isset( $band['max'] ) && null !== $band['max'] ? (float) $band['max'] : null;
		$ids = array();
		foreach ( $prices as $id => $range ) {
			$p_min = (float) ( $range['min'] ?? 0 );
			$p_max = (float) ( $range['max'] ?? $p_min );
			if ( $p_max >= $min && ( null === $max || $p_min <= $max ) ) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
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

		// Attribute facets render in facets[] (checkbox or colour/image swatches);
		// the product_cat facet renders separately as a navigable category tree.
		$attr_defs        = array();
		$facet_taxonomies = array();
		$show_category    = false;
		foreach ( $facet_defs as $def ) {
			$taxonomy = isset( $def['taxonomy'] ) ? (string) $def['taxonomy'] : '';
			if ( '' === $taxonomy ) {
				continue;
			}
			if ( 'product_cat' === $taxonomy || 'category' === ( $def['type'] ?? '' ) ) {
				$show_category      = true;
				$facet_taxonomies[] = 'product_cat';
				continue;
			}
			$def['label']       = $this->taxonomy_label( $taxonomy );
			$attr_defs[]        = $def;
			$facet_taxonomies[] = $taxonomy;
		}

		$slug_to_term_id = $this->resolve_slug_map( $filters );
		$active          = self::resolve_active( $filters, $slug_to_term_id );

		$computed   = Facet_Engine::compute( $base, $postings, $active, $facet_taxonomies );
		$term_index = $this->build_term_index( $computed['facets'] );
		$facets     = self::shape_facets( $attr_defs, $computed['facets'], $term_index, $filters );

		// Category tree (req #3): turn the engine's product_cat counts into a
		// pruned, count-rolled-up parent → child hierarchy for the current context.
		$category_tree = array();
		if ( $show_category && ! empty( $computed['facets']['product_cat'] ) ) {
			$cat_counts    = $computed['facets']['product_cat'];
			$cat_meta      = $this->build_category_meta( array_keys( $cat_counts ) );
			$category_tree = Category_Tree::build( self::shape_category_nodes( $cat_counts, $cat_meta ) );
		}

		// Price facet (numeric bands). Price isn't in the index (§5.2) — read it
		// from wc_product_meta_lookup for the term-filtered set. Band counts are
		// self-excluded w.r.t. the price selection (computed over the term-filtered
		// set); the grid count below additionally applies the selected bands.
		$selected_bands = isset( $state['price_bands'] ) && is_array( $state['price_bands'] ) ? $state['price_bands'] : array();
		$prices         = $this->product_prices( $computed['products'] );
		$price_facet    = array();
		$grid_products  = $computed['products'];
		if ( ! empty( $prices ) ) {
			$bands       = $this->resolve_price_bands( $prices );
			$band_counts = self::count_in_bands( $prices, $bands );
			$price_facet = self::shape_price_facet( $bands, $band_counts, $selected_bands );
			if ( ! empty( $selected_bands ) ) {
				$grid_products = self::filter_by_bands( $computed['products'], $prices, $selected_bands );
			}
		}

		$count    = count( $grid_products );
		$per_page = $this->products_per_page();
		$paged    = max( 1, (int) $state['paged'] );

		return array(
			'facets'        => $facets,
			'category_tree' => $category_tree,
			'price'         => $price_facet,
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
				$entry = array(
					'slug'  => (string) $term->slug,
					'name'  => (string) $term->name,
					'order' => (int) get_term_meta( $term_id, 'order', true ),
				);
				if ( 0 === strpos( $taxonomy, 'pa_' ) ) {
					$swatch = $this->swatch_data( $term_id );
					if ( '' !== $swatch['color'] ) {
						$entry['color'] = $swatch['color'];
					}
					if ( '' !== $swatch['image'] ) {
						$entry['image'] = $swatch['image'];
					}
				}
				$index[ $taxonomy ][ $term_id ] = $entry;
			}
		}
		return $index;
	}

	/**
	 * Read the VariationSwatches term meta for an attribute term DIRECTLY (no call
	 * into Etucart_VS_Plugin, which only loads when that module is enabled — see
	 * decisions §5.7). Returns the hex colour and a resolved image URL, each '' when
	 * unset.
	 *
	 * @param int $term_id Attribute term id.
	 * @return array{color:string,image:string}
	 */
	private function swatch_data( $term_id ) {
		$term_id = (int) $term_id;
		$color   = (string) get_term_meta( $term_id, 'etucart_swatch_color', true );

		$image    = '';
		$image_id = (int) get_term_meta( $term_id, 'freeman_core_variation_swatches_term_image_id', true );
		if ( $image_id > 0 && function_exists( 'wp_get_attachment_image_src' ) ) {
			$src = wp_get_attachment_image_src( $image_id, 'thumbnail' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$image = (string) $src[0];
			}
		}

		return array(
			'color' => $color,
			'image' => $image,
		);
	}

	/**
	 * Display metadata (parent, name, slug, menu order) for the product_cat terms
	 * the engine returned, so shape_category_nodes() can build the tree. Keeps the
	 * pure seam free of WordPress.
	 *
	 * @param int[] $term_ids product_cat term ids.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_category_meta( array $term_ids ) {
		$meta = array();
		foreach ( $term_ids as $term_id ) {
			$term_id = (int) $term_id;
			$term    = get_term( $term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$meta[ $term_id ] = array(
				'parent' => (int) $term->parent,
				'name'   => (string) $term->name,
				'slug'   => (string) $term->slug,
				'order'  => (int) get_term_meta( $term_id, 'order', true ),
			);
		}
		return $meta;
	}

	/**
	 * Read min/max price for a set of products from wc_product_meta_lookup (§5.2 —
	 * price lives in WooCommerce's lookup, never duplicated in our index).
	 *
	 * @param int[] $product_ids Product ids.
	 * @return array<int,array{min:float,max:float}>
	 */
	private function product_prices( array $product_ids ) {
		$product_ids = array_values( array_unique( array_map( 'intval', $product_ids ) ) );
		if ( empty( $product_ids ) ) {
			return array();
		}

		global $wpdb;
		$lookup       = $wpdb->prefix . 'wc_product_meta_lookup';
		$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, min_price, max_price FROM {$lookup} WHERE product_id IN ({$placeholders})",
				$product_ids
			)
		);
		// phpcs:enable

		$prices = array();
		foreach ( (array) $rows as $row ) {
			$prices[ (int) $row->product_id ] = array(
				'min' => (float) $row->min_price,
				'max' => (float) $row->max_price,
			);
		}
		return $prices;
	}

	/**
	 * Resolve the candidate price bands: the configured `price_bands` upper bounds
	 * if set, otherwise auto-derived from the highest price in the candidate set.
	 *
	 * @param array $prices product_id => ['min'=>float,'max'=>float].
	 * @return array<int,array{min:float,max:?float}>
	 */
	private function resolve_price_bands( array $prices ) {
		$setting = (string) get_option( 'freeman_core_shop_filters_price_bands', '' );
		$bounds  = array();
		foreach ( explode( ',', $setting ) as $token ) {
			$token = trim( $token );
			if ( is_numeric( $token ) ) {
				$bounds[] = (float) $token;
			}
		}
		if ( ! empty( $bounds ) ) {
			return self::bands_from_bounds( $bounds );
		}

		$max = 0.0;
		foreach ( $prices as $range ) {
			$max = max( $max, (float) ( $range['max'] ?? 0 ) );
		}
		return self::auto_bands( $max );
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
