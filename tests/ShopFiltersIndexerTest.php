<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Indexer;
use PHPUnit\Framework\TestCase;

/**
 * Dirty-queue mechanics (pure, option-backed). The per-product reindex and the
 * reconcile sweep touch WooCommerce / $wpdb and are integration / live QA.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Indexer
 */
final class ShopFiltersIndexerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts'] = array();
		$GLOBALS['fr_cron'] = array();
	}

	public function test_mark_dirty_queues_and_dedupes(): void {
		$indexer = new Indexer();
		$indexer->mark_dirty( 5 );
		$indexer->mark_dirty( 7 );
		$indexer->mark_dirty( 5 ); // Duplicate collapses.

		$this->assertSame( array( 5, 7 ), array_keys( $indexer->get_dirty_queue() ) );
	}

	public function test_mark_dirty_schedules_drain_once(): void {
		$indexer = new Indexer();
		$indexer->mark_dirty( 5 );
		$indexer->mark_dirty( 7 );

		$this->assertArrayHasKey( Indexer::DRAIN_HOOK, $GLOBALS['fr_cron'] );
		$this->assertCount( 1, $GLOBALS['fr_cron'][ Indexer::DRAIN_HOOK ] );
	}

	public function test_mark_dirty_ignores_non_positive_ids(): void {
		$indexer = new Indexer();
		$indexer->mark_dirty( 0 );
		$indexer->mark_dirty( -3 );

		$this->assertSame( array(), $indexer->get_dirty_queue() );
		$this->assertArrayNotHasKey( Indexer::DRAIN_HOOK, $GLOBALS['fr_cron'] );
	}

	public function test_drain_clears_queue(): void {
		$indexer = new Indexer();
		$indexer->mark_dirty( 5 );
		// No \WooCommerce class in the test env, so the reindex loop is skipped,
		// but the queue is still cleared (the batch is considered drained).
		$indexer->drain_queue();

		$this->assertSame( array(), $indexer->get_dirty_queue() );
	}
}
