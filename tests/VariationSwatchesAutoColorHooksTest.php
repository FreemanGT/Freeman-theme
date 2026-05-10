<?php
declare(strict_types=1);

use Freeman\Core\Modules\VariationSwatches\Sampler_Scheduler;
use Freeman\Core\Modules\VariationSwatches\Color_Sampler;
use PHPUnit\Framework\TestCase;

// WP-Cron stubs live in tests/bootstrap.php (smart promotion, Wave 2.2 / 4d).
// Post-meta lookups go through bootstrap's get_post_meta (uses fr_post_meta).
// `update_post_meta` / `delete_post_meta` / `metadata_exists` / `wp_get_post_parent_id`
// / `get_attached_file` are stubbed inline below — these are 4d-specific and
// not yet needed elsewhere; if a future PR needs them they can be promoted.
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['fr_post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['fr_post_meta'][ $post_id ][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( $type, $object_id, $key ) {
		return isset( $GLOBALS['fr_post_meta'][ $object_id ][ $key ] );
	}
}
if ( ! function_exists( 'wp_get_post_parent_id' ) ) {
	function wp_get_post_parent_id( $post_id ) {
		return (int) ( $GLOBALS['fr_post_parent'][ $post_id ] ?? 0 );
	}
}
if ( ! function_exists( 'get_attached_file' ) ) {
	function get_attached_file( $attachment_id ) {
		// Color_Sampler::resolve_attachment_path checks fr_attachment_paths first
		// before calling this; this stub is only reached when that bypass is
		// not configured. Return empty string to mean "no file".
		return $GLOBALS['fr_attachment_paths'][ $attachment_id ] ?? '';
	}
}

/**
 * Wave 2.2 / 4d — Sampler_Scheduler hooks + queue + batched cron.
 *
 * Tests drive the public listener methods directly (don't rely on do_action
 * routing), so the lack of a real WP hook system in bootstrap doesn't
 * matter — the stubs in bootstrap support add_action/do_action for the
 * filters that DO need to fire (e.g., the batch-size filter).
 *
 * @covers \Freeman\Core\Modules\VariationSwatches\Sampler_Scheduler
 */
final class VariationSwatchesAutoColorHooksTest extends TestCase {

	private const FLAG_OPT = 'freeman_core_variation_swatches_auto_color_enabled';

	public static function setUpBeforeClass(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			throw new \PHPUnit\Framework\SkippedTestSuiteError( 'GD extension not available — sampler scheduler tests skipped.' );
		}
	}

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                      = array();
		$GLOBALS['fr_post_meta']                 = array();
		$GLOBALS['fr_attachment_paths']          = array();
		$GLOBALS['fr_hooks']                     = array();
		$GLOBALS['fr_cron']                      = array();
		$GLOBALS['fr_transients']                = array();
		$GLOBALS['fr_post_parent']               = array();
		$GLOBALS['fr_unsampled_variations']      = null;
		$GLOBALS['fr_variations_using_attachment'] = array();
	}

	private function flag_on(): void {
		update_option( self::FLAG_OPT, 1 );
	}

	private function register_synthetic_image( int $attachment_id, int $variation_id, int $r, int $g, int $b ): void {
		$dir  = sys_get_temp_dir() . '/freeman-sched-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir, 0755, true );
		$im   = imagecreatetruecolor( 30, 30 );
		$col  = imagecolorallocate( $im, $r, $g, $b );
		imagefill( $im, 0, 0, $col );
		$path = $dir . "/$attachment_id.png";
		imagepng( $im, $path );
		imagedestroy( $im );

		$GLOBALS['fr_attachment_paths'][ $attachment_id ]              = $path;
		$GLOBALS['fr_post_meta'][ $variation_id ]['_thumbnail_id']     = $attachment_id;
	}

	/* ---- handle_save_variation ---- */

	public function test_save_variation_samples_inline_when_flag_on(): void {
		$this->flag_on();
		$this->register_synthetic_image( 500, 600, 200, 0, 0 );

		Sampler_Scheduler::handle_save_variation( 600 );

		$this->assertNotSame( '', get_post_meta( 600, Color_Sampler::META_KEY, true ) );
	}

	public function test_save_variation_no_op_when_flag_off(): void {
		// Flag intentionally OFF.
		$this->register_synthetic_image( 501, 601, 0, 200, 0 );

		Sampler_Scheduler::handle_save_variation( 601 );

		$this->assertSame( '', get_post_meta( 601, Color_Sampler::META_KEY, true ) );
	}

	/* ---- handle_flag_update / handle_flag_add ---- */

	public function test_flag_update_off_to_on_queues_unsampled_variations_and_schedules_cron(): void {
		$GLOBALS['fr_unsampled_variations'] = array( 700, 701, 702 );

		Sampler_Scheduler::handle_flag_update( 0, 1 );

		$this->assertSame( array( 700, 701, 702 ), Sampler_Scheduler::queue_get() );
		$this->assertNotFalse( wp_next_scheduled( Sampler_Scheduler::CRON_HOOK ) );
	}

	public function test_flag_update_on_to_off_does_not_schedule(): void {
		$GLOBALS['fr_unsampled_variations'] = array( 703 );

		Sampler_Scheduler::handle_flag_update( 1, 0 );

		$this->assertSame( array(), Sampler_Scheduler::queue_get() );
		$this->assertFalse( wp_next_scheduled( Sampler_Scheduler::CRON_HOOK ) );
	}

	public function test_flag_truthiness_change_purges_archive_payload_cache(): void {
		set_transient( 'freeman_vs_pd_archive_payload', array( 'pid' => 10 ), HOUR_IN_SECONDS );
		set_transient( 'other_payload', array( 'pid' => 11 ), HOUR_IN_SECONDS );
		$GLOBALS['fr_unsampled_variations'] = array();

		Sampler_Scheduler::handle_flag_update( 0, 1 );

		$this->assertFalse( get_transient( 'freeman_vs_pd_archive_payload' ) );
		$this->assertSame( array( 'pid' => 11 ), get_transient( 'other_payload' ) );
	}

	public function test_flag_update_already_on_does_not_reschedule(): void {
		$GLOBALS['fr_unsampled_variations'] = array( 704 );

		Sampler_Scheduler::handle_flag_update( 1, 1 );

		$this->assertSame( array(), Sampler_Scheduler::queue_get() );
		$this->assertFalse( wp_next_scheduled( Sampler_Scheduler::CRON_HOOK ) );
	}

	public function test_flag_add_first_write_off_to_on_pre_warms(): void {
		$GLOBALS['fr_unsampled_variations'] = array( 705, 706 );

		Sampler_Scheduler::handle_flag_add( self::FLAG_OPT, 1 );

		$this->assertSame( array( 705, 706 ), Sampler_Scheduler::queue_get() );
	}

	public function test_flag_update_with_empty_universe_does_not_schedule(): void {
		$GLOBALS['fr_unsampled_variations'] = array();

		Sampler_Scheduler::handle_flag_update( 0, 1 );

		$this->assertSame( array(), Sampler_Scheduler::queue_get() );
		$this->assertFalse( wp_next_scheduled( Sampler_Scheduler::CRON_HOOK ) );
	}

	/* ---- run_prewarm_batch ---- */

	public function test_cron_pops_default_batch_size_and_reschedules_when_queue_remains(): void {
		$this->flag_on();
		// Synthesize an oversized queue (60 ids) and per-variation images.
		$ids = array();
		for ( $i = 0; $i < 60; $i++ ) {
			$ids[] = 800 + $i;
			$this->register_synthetic_image( 900 + $i, 800 + $i, $i % 256, ( $i * 2 ) % 256, ( $i * 3 ) % 256 );
		}
		Sampler_Scheduler::queue_replace( $ids );

		Sampler_Scheduler::run_prewarm_batch();

		$remaining = Sampler_Scheduler::queue_get();
		$this->assertCount( 60 - Sampler_Scheduler::DEFAULT_BATCH_SIZE, $remaining );
		// Each of the first 50 should now have a cached hex.
		for ( $i = 0; $i < Sampler_Scheduler::DEFAULT_BATCH_SIZE; $i++ ) {
			$this->assertNotSame( '', get_post_meta( 800 + $i, Color_Sampler::META_KEY, true ) );
		}
		// And cron is rescheduled.
		$this->assertNotFalse( wp_next_scheduled( Sampler_Scheduler::CRON_HOOK ) );
	}

	public function test_cron_clears_queue_option_when_done(): void {
		$this->flag_on();
		// Single id, single tick.
		$ids = array( 850 );
		$this->register_synthetic_image( 950, 850, 100, 100, 100 );
		Sampler_Scheduler::queue_replace( $ids );

		Sampler_Scheduler::run_prewarm_batch();

		$this->assertSame( array(), Sampler_Scheduler::queue_get() );
		// Option deleted (not just empty array stored).
		$this->assertFalse( get_option( Sampler_Scheduler::QUEUE_OPTION, false ) );
	}

	public function test_batch_size_filter_overrides_default(): void {
		$this->flag_on();
		add_filter(
			Sampler_Scheduler::BATCH_SIZE_FILTER,
			static function () {
				return 3;
			}
		);
		$ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$ids[] = 870 + $i;
			$this->register_synthetic_image( 970 + $i, 870 + $i, 50, 50, 50 );
		}
		Sampler_Scheduler::queue_replace( $ids );

		Sampler_Scheduler::run_prewarm_batch();

		$this->assertCount( 5 - 3, Sampler_Scheduler::queue_get() );
	}

	public function test_cron_with_empty_queue_is_idempotent_no_op(): void {
		Sampler_Scheduler::run_prewarm_batch();

		$this->assertSame( array(), Sampler_Scheduler::queue_get() );
	}

	/* ---- queue management primitives ---- */

	public function test_queue_pop_n_returns_at_most_n_and_persists_remainder(): void {
		Sampler_Scheduler::queue_replace( array( 1, 2, 3, 4, 5 ) );

		$batch = Sampler_Scheduler::queue_pop_n( 2 );

		$this->assertSame( array( 1, 2 ), $batch );
		$this->assertSame( array( 3, 4, 5 ), Sampler_Scheduler::queue_get() );
	}

	public function test_queue_pop_n_with_empty_queue_returns_empty(): void {
		$this->assertSame( array(), Sampler_Scheduler::queue_pop_n( 10 ) );
	}

	public function test_queue_remove_drops_specific_id(): void {
		Sampler_Scheduler::queue_replace( array( 1, 2, 3 ) );

		Sampler_Scheduler::queue_remove( 2 );

		$this->assertSame( array( 1, 3 ), Sampler_Scheduler::queue_get() );
	}

	public function test_queue_remove_no_op_when_id_not_present(): void {
		Sampler_Scheduler::queue_replace( array( 1, 2, 3 ) );

		Sampler_Scheduler::queue_remove( 99 );

		$this->assertSame( array( 1, 2, 3 ), Sampler_Scheduler::queue_get() );
	}

	/* ---- handle_thumbnail_change ---- */

	public function test_thumbnail_change_clears_cached_hex_when_flag_on(): void {
		$this->flag_on();
		$GLOBALS['fr_post_meta'][1000][ Color_Sampler::META_KEY ] = '#FF00FF';
		// Bootstrap's get_post_type uses a single-global return; tests pin
		// the value per-test rather than per-id.
		$GLOBALS['fr_get_post_type_return'] = 'product_variation';

		Sampler_Scheduler::handle_thumbnail_change( 1, 1000, '_thumbnail_id', 42 );

		$this->assertSame( '', get_post_meta( 1000, Color_Sampler::META_KEY, true ) );
	}

	public function test_thumbnail_change_ignores_non_variation_post_types(): void {
		$this->flag_on();
		$GLOBALS['fr_post_meta'][1001][ Color_Sampler::META_KEY ] = '#ABABAB';
		$GLOBALS['fr_get_post_type_return']                       = 'post';

		Sampler_Scheduler::handle_thumbnail_change( 1, 1001, '_thumbnail_id', 42 );

		$this->assertSame( '#ABABAB', get_post_meta( 1001, Color_Sampler::META_KEY, true ) );
	}

	public function test_thumbnail_change_ignores_other_meta_keys(): void {
		$this->flag_on();
		$GLOBALS['fr_post_meta'][1002][ Color_Sampler::META_KEY ] = '#CDCDCD';
		$GLOBALS['fr_get_post_type_return']                       = 'product_variation';

		Sampler_Scheduler::handle_thumbnail_change( 1, 1002, '_some_other_key', 42 );

		$this->assertSame( '#CDCDCD', get_post_meta( 1002, Color_Sampler::META_KEY, true ) );
	}

	/* ---- handle_attachment_delete ---- */

	public function test_attachment_delete_clears_referenced_variations(): void {
		$this->flag_on();
		$GLOBALS['fr_post_meta'][1100][ Color_Sampler::META_KEY ] = '#111111';
		$GLOBALS['fr_post_meta'][1101][ Color_Sampler::META_KEY ] = '#222222';
		$GLOBALS['fr_variations_using_attachment'][7777]         = array( 1100, 1101 );

		Sampler_Scheduler::handle_attachment_delete( 7777 );

		$this->assertSame( '', get_post_meta( 1100, Color_Sampler::META_KEY, true ) );
		$this->assertSame( '', get_post_meta( 1101, Color_Sampler::META_KEY, true ) );
	}

	public function test_attachment_delete_no_op_when_no_variations_reference_it(): void {
		$this->flag_on();
		$GLOBALS['fr_variations_using_attachment'][7778] = array();

		// Should not throw.
		Sampler_Scheduler::handle_attachment_delete( 7778 );

		$this->assertTrue( true );
	}

	/* ---- handle_variation_delete ---- */

	public function test_variation_delete_clears_cached_hex_and_removes_from_queue(): void {
		$this->flag_on();
		$GLOBALS['fr_get_post_type_return']                       = 'product_variation';
		$GLOBALS['fr_post_meta'][1200][ Color_Sampler::META_KEY ] = '#DEADBE';
		Sampler_Scheduler::queue_replace( array( 1199, 1200, 1201 ) );

		Sampler_Scheduler::handle_variation_delete( 1200 );

		$this->assertSame( '', get_post_meta( 1200, Color_Sampler::META_KEY, true ) );
		$this->assertSame( array( 1199, 1201 ), Sampler_Scheduler::queue_get() );
	}
}
