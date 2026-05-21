<?php
/**
 * Shop Filters indexer.
 *
 * Keeps the index table fresh with minimal impact: an event-driven dirty queue
 * (WC product/stock lifecycle hooks → 30s debounced drain) plus a periodic
 * reconciliation sweep that re-indexes products modified since the last sweep,
 * batched and self-chaining so one tick never runs long. The sweep is scheduled
 * via Action Scheduler when WooCommerce makes it available, falling back to
 * wp-cron — never a hard dependency.
 *
 * The queue mechanics are pure (option-backed) and unit-tested; the per-product
 * reindex and the sweep query touch WooCommerce / $wpdb and are integration /
 * live-QA territory.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

use Freeman\Core\Core\Feature_Flags;

defined( 'ABSPATH' ) || exit;

/**
 * Indexer.
 */
final class Indexer {

	const QUEUE_OPTION     = 'freeman_core_shop_filters_dirty_queue';
	const WATERMARK_OPTION = 'freeman_core_shop_filters_last_sweep_gmt';
	const LAST_RUN_OPTION  = 'freeman_core_shop_filters_last_run_gmt';
	const DRAIN_HOOK       = 'freeman_core_shop_filters_drain_queue';
	const RECONCILE_HOOK   = 'freeman_core_shop_filters_reconcile';
	const CRON_SCHEDULE    = 'freeman_shop_filters_5min';
	const BATCH_SIZE       = 50;
	const SWEEP_INTERVAL   = 300; // 5 minutes.
	const DEBOUNCE_DELAY   = 30;

	/**
	 * Index storage.
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
	 * Wiring
	 * ----------------------------------------------------------------- */

	/**
	 * Register the WC lifecycle listeners + cron callbacks. Only called from
	 * Module::boot() when the indexer feature flag is on.
	 */
	public function register_hooks() {
		add_action( 'woocommerce_update_product', array( $this, 'handle_product_event' ), 20, 1 );
		add_action( 'woocommerce_new_product', array( $this, 'handle_product_event' ), 20, 1 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'handle_product_event' ), 20, 1 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_product_event' ), 20, 1 );
		add_action( 'before_delete_post', array( $this, 'handle_product_event' ), 20, 1 );
		add_action( 'wp_trash_post', array( $this, 'handle_product_event' ), 20, 1 );

		add_action( self::DRAIN_HOOK, array( $this, 'drain_queue' ) );
		add_action( self::RECONCILE_HOOK, array( $this, 'run_reconcile' ) );
	}

	/**
	 * Ensure the recurring reconcile sweep is scheduled (idempotent). Prefers
	 * Action Scheduler; falls back to a custom-recurrence wp-cron event.
	 */
	public function ensure_scheduled() {
		if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_next_scheduled_action' ) ) {
			if ( ! as_next_scheduled_action( self::RECONCILE_HOOK ) ) {
				as_schedule_recurring_action( time() + self::SWEEP_INTERVAL, self::SWEEP_INTERVAL, self::RECONCILE_HOOK, array(), 'freeman-shop-filters' );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::RECONCILE_HOOK ) ) {
			wp_schedule_event( time() + self::SWEEP_INTERVAL, self::CRON_SCHEDULE, self::RECONCILE_HOOK );
		}
	}

	/**
	 * Tear down all scheduling + the queue (deactivation / uninstall).
	 */
	public function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::RECONCILE_HOOK );
		}
		wp_clear_scheduled_hook( self::RECONCILE_HOOK );
		wp_clear_scheduled_hook( self::DRAIN_HOOK );
		delete_option( self::QUEUE_OPTION );
	}

	/* -----------------------------------------------------------------
	 * Dirty queue (pure, option-backed)
	 * ----------------------------------------------------------------- */

	/**
	 * Route a WC lifecycle event to the dirty queue. Accepts an id or a
	 * WC_Product (or variation, whose parent is queued). Non-products are
	 * ignored, so the broad before_delete_post / wp_trash_post hooks don't
	 * pollute the queue with pages, orders, etc.
	 *
	 * @param int|\WC_Product $arg Event payload.
	 */
	public function handle_product_event( $arg ) {
		if ( ! class_exists( '\\WooCommerce' ) ) {
			return;
		}
		$product = $arg instanceof \WC_Product ? $arg : ( is_numeric( $arg ) ? wc_get_product( (int) $arg ) : null );
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$id = $product instanceof \WC_Product_Variation ? $product->get_parent_id() : $product->get_id();
		$this->mark_dirty( (int) $id );
	}

	/**
	 * Add a product id to the dirty queue and ensure the debounced drain is
	 * scheduled. Keyed by id so repeated touches during a bulk import collapse
	 * to one reindex.
	 *
	 * @param int $product_id Product id.
	 */
	public function mark_dirty( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return;
		}
		$queue                = $this->get_dirty_queue();
		$queue[ $product_id ] = true;
		update_option( self::QUEUE_OPTION, $queue, false );

		if ( ! wp_next_scheduled( self::DRAIN_HOOK ) ) {
			wp_schedule_single_event( time() + self::DEBOUNCE_DELAY, self::DRAIN_HOOK );
		}
	}

	/**
	 * Current dirty queue as an id => true map.
	 *
	 * @return array<int,bool>
	 */
	public function get_dirty_queue() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Drain the queue — reindex each queued product, then clear it.
	 */
	public function drain_queue() {
		if ( ! Feature_Flags::is_enabled( 'shop_filters', 'indexer' ) ) {
			return;
		}
		$queue = $this->get_dirty_queue();
		delete_option( self::QUEUE_OPTION );
		if ( empty( $queue ) || ! class_exists( '\\WooCommerce' ) ) {
			return;
		}
		foreach ( array_keys( $queue ) as $product_id ) {
			$this->reindex_product( (int) $product_id );
		}
		$this->flush_runtime_cache();
	}

	/* -----------------------------------------------------------------
	 * Reconciliation sweep (integration)
	 * ----------------------------------------------------------------- */

	/**
	 * Re-index products modified since the last sweep, oldest first, one batch
	 * per tick. Advances a watermark and self-chains a near-term follow-up while
	 * batches keep coming, so the initial full index and large catalogues make
	 * progress without ever running long.
	 */
	public function run_reconcile() {
		if ( ! Feature_Flags::is_enabled( 'shop_filters', 'indexer' ) || ! class_exists( '\\WooCommerce' ) ) {
			return;
		}

		// Record when the sweep actually ran — distinct from the resume watermark
		// below, which tracks how far through the catalogue (by modified-date)
		// the sweep has reached.
		update_option( self::LAST_RUN_OPTION, gmdate( 'Y-m-d H:i:s' ), false );

		$since = (string) get_option( self::WATERMARK_OPTION, '' );

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => self::BATCH_SIZE,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( '' !== $since ) {
			$args['date_query'] = array(
				array(
					'column'    => 'post_modified_gmt',
					'after'     => $since,
					'inclusive' => false,
				),
			);
		}

		$query = new \WP_Query( $args );
		if ( empty( $query->posts ) ) {
			// Caught up — park the watermark at "now" so the next sweep only
			// looks at genuinely new changes.
			update_option( self::WATERMARK_OPTION, gmdate( 'Y-m-d H:i:s' ), false );
			return;
		}

		$last_modified = $since;
		foreach ( $query->posts as $product_id ) {
			$this->reindex_product( (int) $product_id );
			$post = get_post( $product_id );
			if ( $post && ! empty( $post->post_modified_gmt ) ) {
				$last_modified = $post->post_modified_gmt;
			}
		}
		update_option( self::WATERMARK_OPTION, $last_modified, false );
		$this->flush_runtime_cache();

		if ( count( $query->posts ) === self::BATCH_SIZE ) {
			$this->chain_reconcile();
		}
	}

	/**
	 * Queue an immediate follow-up sweep while batches keep coming. Uses Action
	 * Scheduler's runner when available (the same path as the recurring sweep),
	 * with a wp-cron single event as the fallback.
	 */
	private function chain_reconcile() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::RECONCILE_HOOK, array(), 'freeman-shop-filters' );
			return;
		}
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::RECONCILE_HOOK );
	}

	/* -----------------------------------------------------------------
	 * Per-product indexing (integration)
	 * ----------------------------------------------------------------- */

	/**
	 * Rebuild a single product's index rows.
	 *
	 * @param int|\WC_Product $product_or_id Product or id.
	 */
	public function reindex_product( $product_or_id ) {
		$product = $product_or_id instanceof \WC_Product ? $product_or_id : wc_get_product( $product_or_id );

		if ( ! $product instanceof \WC_Product ) {
			if ( is_numeric( $product_or_id ) ) {
				$this->repo->delete_product( (int) $product_or_id );
			}
			return;
		}

		$pid = (int) $product->get_id();

		if ( ! $this->is_indexable( $product ) ) {
			$this->repo->delete_product( $pid );
			return;
		}

		$overall_in_stock = $product->is_in_stock() ? 1 : 0;
		$rows             = array();

		foreach ( (array) $product->get_category_ids() as $cat_id ) {
			$rows[] = array(
				'product_id' => $pid,
				'taxonomy'   => 'product_cat',
				'term_id'    => (int) $cat_id,
				'in_stock'   => $overall_in_stock,
			);
		}

		$rows = array_merge( $rows, $this->attribute_rows( $product, $pid, $overall_in_stock ) );

		$this->repo->replace_product( $pid, $rows );
	}

	/**
	 * Build the attribute (pa_*) rows for a product. For variable products the
	 * per-term in_stock flag reflects whether any in-stock variation carries
	 * that value; for simple products it follows the product's overall stock.
	 *
	 * @param \WC_Product $product          Product.
	 * @param int         $pid              Product id.
	 * @param int         $overall_in_stock 1/0 product stock.
	 * @return array
	 */
	private function attribute_rows( $product, $pid, $overall_in_stock ) {
		$rows       = array();
		$attributes = $product->get_attributes();
		if ( empty( $attributes ) ) {
			return $rows;
		}

		$is_variable = $product->is_type( 'variable' );
		$stock_map   = array(
			'values' => array(),
			'any'    => array(),
		);
		if ( $is_variable ) {
			$stock_map = Term_Helpers::in_stock_values_by_attribute( $product->get_available_variations() );
		}

		foreach ( $attributes as $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute || ! $attribute->is_taxonomy() ) {
				continue; // Only global pa_* attributes are facetable.
			}
			$taxonomy  = $attribute->get_taxonomy();
			$input_key = 'attribute_' . $taxonomy;
			// Only variation-axis attributes are stock-gated by their variations;
			// a non-variation global attribute (e.g. Brand) applies to the whole
			// product, so it follows overall stock like a simple product would.
			$variation_gated = $is_variable && $attribute->get_variation();

			foreach ( (array) $attribute->get_options() as $term_id ) {
				$term_id = (int) $term_id;
				$slug    = '';
				if ( $variation_gated ) {
					$term = get_term( $term_id, $taxonomy );
					$slug = ( $term && ! is_wp_error( $term ) ) ? $term->slug : '';
				}

				$rows[] = array(
					'product_id' => $pid,
					'taxonomy'   => $taxonomy,
					'term_id'    => $term_id,
					'in_stock'   => Term_Helpers::resolve_in_stock( $variation_gated, $stock_map, $input_key, $slug, $overall_in_stock ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Whether a product should be in the index — published and visible in the
	 * catalogue, so facet counts match the shop grid (hidden / search-only
	 * products are excluded).
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	private function is_indexable( $product ) {
		if ( 'publish' !== $product->get_status() ) {
			return false;
		}
		return in_array( $product->get_catalog_visibility(), array( 'visible', 'catalog' ), true );
	}

	/* -----------------------------------------------------------------
	 * Full rebuild (used by the admin tool)
	 * ----------------------------------------------------------------- */

	/**
	 * Re-index one offset-paged batch of all products. Returns the number
	 * processed so the caller can advance the offset / show progress.
	 *
	 * @param int $offset     Offset.
	 * @param int $batch_size Batch size.
	 * @return int Products processed.
	 */
	public function reindex_batch( $offset, $batch_size ) {
		if ( ! class_exists( '\\WooCommerce' ) ) {
			return 0;
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => (int) $batch_size,
				'offset'         => (int) $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$count = count( $query->posts );
		foreach ( $query->posts as $product_id ) {
			$this->reindex_product( (int) $product_id );
		}

		// An empty batch means the full reindex reached the end of the catalogue;
		// park the watermark + last-run at "now" so the periodic sweep treats the
		// index as current and doesn't re-process everything just rebuilt.
		if ( 0 === $count ) {
			$now = gmdate( 'Y-m-d H:i:s' );
			update_option( self::WATERMARK_OPTION, $now, false );
			update_option( self::LAST_RUN_OPTION, $now, false );
		}

		$this->flush_runtime_cache();
		return $count;
	}

	/**
	 * Total published products (the denominator for the reindex progress bar).
	 *
	 * @return int
	 */
	public function count_products() {
		if ( ! class_exists( '\\WooCommerce' ) ) {
			return 0;
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Repository accessor (for the admin tool's index stats).
	 *
	 * @return Index_Repository
	 */
	public function repository() {
		return $this->repo;
	}

	/**
	 * Flush the per-request object cache between batches to keep memory flat.
	 */
	private function flush_runtime_cache() {
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
	}
}
