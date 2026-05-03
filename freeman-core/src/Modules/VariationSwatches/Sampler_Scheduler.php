<?php
/**
 * Wave 2.2 / 4d (1.11.27) — auto-color sampler scheduler.
 *
 * Owns the WP-Cron + queue + batching surface for the auto-color sampler.
 * Stores that exclude product pages from cache need every variation
 * pre-sampled before any shopper sees it; this scheduler ensures that.
 *
 * Three layers of cold-cache prevention, fired in order:
 *
 *   1. Sample-on-save — woocommerce_save_product_variation hook samples
 *      inline at admin time, so new and edited variations always have a
 *      cached hex by the time a shopper requests the page.
 *   2. Pre-warm on flag-flip — when admin enables the feature, this class
 *      enumerates all existing variable-product variations missing a
 *      cached hex, queues them, and schedules a batched cron event. The
 *      callback pops a configurable batch (default 50, filterable) per
 *      tick and reschedules itself ~5s out until the queue is empty.
 *   3. Hot-path lazy fallback — Color_Sampler::sample_if_missing is also
 *      callable from 4e's render path as the absolute safety net, for
 *      variations slipped through layers 1-2 (direct DB writes, import
 *      tools that bypass save hooks, etc.).
 *
 * First-of-kind WP-Cron precedent for freeman-core. Future scheduled
 * work should mirror this shape: queue option suffixed `_queue`, single
 * cron event, batched callback that self-reschedules until done.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariationSwatches;

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Core\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Sampler scheduler.
 */
final class Sampler_Scheduler {

	const CRON_HOOK = 'freeman_core/variation_swatches/sampler_prewarm';

	const QUEUE_OPTION = 'freeman_core_variation_swatches_sampler_prewarm_queue';

	const DEFAULT_BATCH_SIZE = 50;

	const BATCH_SIZE_FILTER = 'freeman_core/variation_swatches/sampler_prewarm_batch_size';

	/** Seconds between batches when the queue still has work. */
	const RESCHEDULE_DELAY_SECONDS = 5;

	/* ------------------------------------------------------------------ *
	 * Boot — register listeners. Caller (Module::boot) gates on the flag.
	 * ------------------------------------------------------------------ */

	/**
	 * Register every WP hook the scheduler needs. Idempotent — Module::boot
	 * may call this twice on flag-flip + page load.
	 */
	public static function register() {
		// Layer 1 — sample-on-save.
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'handle_save_variation' ) );

		// Layer 2 — pre-warm on flag-flip. Both `add_option_*` (first-write,
		// when the option didn't exist before) and `update_option_*`
		// (subsequent writes) need wiring; WP fires only one of the two
		// depending on the prior state.
		$flag_option = 'freeman_core_variation_swatches_auto_color_enabled';
		add_action( 'add_option_' . $flag_option, array( __CLASS__, 'handle_flag_add' ), 10, 2 );
		add_action( 'update_option_' . $flag_option, array( __CLASS__, 'handle_flag_update' ), 10, 2 );

		// Cache invalidation.
		add_action( 'updated_post_meta', array( __CLASS__, 'handle_thumbnail_change' ), 10, 4 );
		add_action( 'before_delete_post', array( __CLASS__, 'handle_variation_delete' ) );
		add_action( 'delete_attachment', array( __CLASS__, 'handle_attachment_delete' ) );

		// Cron callback.
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_prewarm_batch' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Listener handlers. Each is public so tests can call directly.
	 * ------------------------------------------------------------------ */

	/**
	 * woocommerce_save_product_variation — admin saved a variation. Sample
	 * inline so admin pays the cost, not the shopper. Flag-gated.
	 *
	 * @param int $variation_id Variation post id.
	 */
	public static function handle_save_variation( $variation_id ) {
		if ( ! self::is_flag_on() ) {
			return;
		}
		Color_Sampler::sample( (int) $variation_id );
	}

	/**
	 * `add_option_<flag>` — first time the flag is written. WP passes the
	 * option name + value, NOT old/new; reuse the update path with old=''.
	 */
	public static function handle_flag_add( $option, $value ) {
		self::handle_flag_update( '', $value );
	}

	/**
	 * `update_option_<flag>` — pre-warm scheduler. When the flag flips
	 * truthy, enumerate all variations missing a cached hex, queue them,
	 * schedule the cron event.
	 */
	public static function handle_flag_update( $old_value, $new_value ) {
		$was_on = filter_var( $old_value, FILTER_VALIDATE_BOOLEAN );
		$is_on  = filter_var( $new_value, FILTER_VALIDATE_BOOLEAN );
		if ( $was_on || ! $is_on ) {
			return; // already on, or being turned off.
		}

		$ids = self::enumerate_unsampled_variations();
		if ( empty( $ids ) ) {
			return;
		}

		self::queue_replace( $ids );
		self::schedule_next_batch();
	}

	/**
	 * `updated_post_meta` — when a variation's `_thumbnail_id` changes,
	 * clear the cached hex so the next sampling pass re-runs. Flag-gated.
	 */
	public static function handle_thumbnail_change( $meta_id, $object_id, $meta_key, $meta_value = null ) {
		if ( ! self::is_flag_on() ) {
			return;
		}
		if ( '_thumbnail_id' !== $meta_key ) {
			return;
		}
		if ( ! self::is_variation( (int) $object_id ) ) {
			return;
		}
		Color_Sampler::clear( (int) $object_id );
	}

	/**
	 * `before_delete_post` — defense-in-depth cleanup. WP cleans up post-meta
	 * automatically on post deletion, so this is belt-and-suspenders. Also
	 * removes the variation from the pre-warm queue if it's pending, so the
	 * cron callback doesn't waste a tick on a deleted variation.
	 */
	public static function handle_variation_delete( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! self::is_variation( $post_id ) ) {
			return;
		}
		Color_Sampler::clear( $post_id );
		self::queue_remove( $post_id );
	}

	/**
	 * `delete_attachment` — find variations whose `_thumbnail_id` referenced
	 * this attachment and clear their cached hex. WP doesn't auto-clear
	 * `_thumbnail_id` references when an attachment is deleted, so without
	 * this hook those variations would render with the now-stale hex
	 * forever. Clearing forces 4e's render path to fall through to the
	 * neutral-gray fallback.
	 */
	public static function handle_attachment_delete( $post_id ) {
		$post_id = (int) $post_id;
		$ids     = self::find_variations_using_attachment( $post_id );
		if ( empty( $ids ) ) {
			return;
		}
		foreach ( $ids as $variation_id ) {
			Color_Sampler::clear( (int) $variation_id );
		}
		Logger::log(
			sprintf(
				'auto-color: cleared cached hex on %d variation(s) after attachment %d deleted',
				count( $ids ),
				$post_id
			)
		);
	}

	/**
	 * Cron callback — pop a batch from the queue, sample each, save the
	 * remaining queue, reschedule self if non-empty. Filterable batch size.
	 */
	public static function run_prewarm_batch() {
		$batch_size = (int) apply_filters( self::BATCH_SIZE_FILTER, self::DEFAULT_BATCH_SIZE );
		if ( $batch_size < 1 ) {
			$batch_size = self::DEFAULT_BATCH_SIZE;
		}

		$batch = self::queue_pop_n( $batch_size );
		if ( empty( $batch ) ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}

		foreach ( $batch as $variation_id ) {
			Color_Sampler::sample( (int) $variation_id );
		}

		if ( ! empty( self::queue_get() ) ) {
			self::schedule_next_batch();
		} else {
			delete_option( self::QUEUE_OPTION );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Queue management — separate methods so tests can drive them directly.
	 * ------------------------------------------------------------------ */

	/**
	 * Replace the queue with a fresh ID list (called on flag-flip).
	 *
	 * @param array<int> $ids
	 */
	public static function queue_replace( array $ids ) {
		$ids = array_values( array_filter( array_map( 'intval', $ids ), static function ( $i ) { return $i > 0; } ) );
		update_option( self::QUEUE_OPTION, $ids, false );
	}

	/**
	 * Get the current queue contents.
	 *
	 * @return array<int>
	 */
	public static function queue_get() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		return is_array( $queue ) ? array_values( array_map( 'intval', $queue ) ) : array();
	}

	/**
	 * Pop N IDs from the front of the queue and return them. Saves the
	 * remainder back to the option.
	 *
	 * @param int $n
	 * @return array<int>
	 */
	public static function queue_pop_n( $n ) {
		$queue = self::queue_get();
		if ( empty( $queue ) ) {
			return array();
		}
		$batch     = array_slice( $queue, 0, max( 0, (int) $n ) );
		$remainder = array_slice( $queue, count( $batch ) );
		if ( empty( $remainder ) ) {
			delete_option( self::QUEUE_OPTION );
		} else {
			update_option( self::QUEUE_OPTION, $remainder, false );
		}
		return $batch;
	}

	/**
	 * Remove a specific variation id from the queue (used by deletion
	 * cleanup so the cron callback doesn't waste a tick).
	 */
	public static function queue_remove( $variation_id ) {
		$variation_id = (int) $variation_id;
		$queue        = self::queue_get();
		if ( empty( $queue ) ) {
			return;
		}
		$filtered = array_values( array_filter( $queue, static function ( $id ) use ( $variation_id ) {
			return (int) $id !== $variation_id;
		} ) );
		if ( count( $filtered ) === count( $queue ) ) {
			return; // not in queue.
		}
		if ( empty( $filtered ) ) {
			delete_option( self::QUEUE_OPTION );
		} else {
			update_option( self::QUEUE_OPTION, $filtered, false );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Internals
	 * ------------------------------------------------------------------ */

	/**
	 * Schedule the next cron tick. Idempotent — if an event is already
	 * scheduled, leave it; otherwise schedule one ~5s out.
	 */
	private static function schedule_next_batch() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		wp_schedule_single_event( time() + self::RESCHEDULE_DELAY_SECONDS, self::CRON_HOOK );
	}

	/**
	 * Enumerate all variable-product variations that don't yet have a
	 * cached hex. Test-friendly: tests can populate
	 * $GLOBALS['fr_unsampled_variations'] to bypass the get_posts call.
	 *
	 * @return array<int>
	 */
	private static function enumerate_unsampled_variations() {
		if ( isset( $GLOBALS['fr_unsampled_variations'] ) && is_array( $GLOBALS['fr_unsampled_variations'] ) ) {
			return array_map( 'intval', $GLOBALS['fr_unsampled_variations'] );
		}
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => Color_Sampler::META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		return is_array( $posts ) ? array_map( 'intval', $posts ) : array();
	}

	/**
	 * Find variations whose `_thumbnail_id` matches the given attachment.
	 * Test-friendly: tests can populate
	 * $GLOBALS['fr_variations_using_attachment'][$attachment_id].
	 *
	 * @param int $attachment_id
	 * @return array<int>
	 */
	private static function find_variations_using_attachment( $attachment_id ) {
		if ( isset( $GLOBALS['fr_variations_using_attachment'][ $attachment_id ] )
			&& is_array( $GLOBALS['fr_variations_using_attachment'][ $attachment_id ] )
		) {
			return array_map( 'intval', $GLOBALS['fr_variations_using_attachment'][ $attachment_id ] );
		}
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_thumbnail_id',
						'value' => (int) $attachment_id,
					),
				),
			)
		);
		return is_array( $posts ) ? array_map( 'intval', $posts ) : array();
	}

	/**
	 * Is this post a product_variation? Avoids hammering Color_Sampler
	 * on unrelated post-meta updates.
	 */
	private static function is_variation( $post_id ) {
		if ( ! function_exists( 'get_post_type' ) ) {
			return false;
		}
		return 'product_variation' === get_post_type( (int) $post_id );
	}

	/**
	 * Is the auto_color feature flag on? Used to gate the save / thumbnail /
	 * attachment / cron handlers — they're cheap no-ops when the flag is off,
	 * but `Color_Sampler::sample` does real work (file IO + GD/Imagick), so
	 * gating is worth the one extra option read.
	 *
	 * The flag-flip handlers (handle_flag_add / handle_flag_update) are
	 * intentionally NOT gated — they ARE the gate-flipping detector.
	 */
	private static function is_flag_on() {
		return Feature_Flags::is_enabled( 'variation_swatches', 'auto_color' );
	}
}
