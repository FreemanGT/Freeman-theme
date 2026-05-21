<?php
declare(strict_types=1);

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Modules\ShopFilters\Module;
use PHPUnit\Framework\TestCase;

/**
 * Module identity, default-disabled state, and flag-gated boot wiring.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Module
 */
final class ShopFiltersModuleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
		$GLOBALS['fr_cron']  = array();
	}

	public function test_id_and_option_names(): void {
		$module = new Module();

		$this->assertSame( 'shop_filters', $module->id() );
		$this->assertSame( 'freeman_core_shop_filters_dirty_queue', $module->option_name( 'dirty_queue' ) );
		$this->assertSame(
			'freeman_core_shop_filters_indexer_enabled',
			Feature_Flags::option_name( 'shop_filters', 'indexer' )
		);
	}

	public function test_disabled_by_default(): void {
		// No freeman_core_modules option set → a newly-added module is absent → off.
		$this->assertFalse( ( new Module() )->is_enabled() );
	}

	public function test_boot_attaches_no_indexer_hooks_when_flag_off(): void {
		( new Module() )->boot();

		$this->assertFalse( has_action( 'woocommerce_update_product' ) );
		$this->assertFalse( has_action( 'before_delete_post' ) );
		// The cron-schedule recurrence filter is always registered (harmless, additive).
		$this->assertNotFalse( has_filter( 'cron_schedules' ) );
	}

	public function test_boot_wires_indexer_hooks_when_flag_on(): void {
		$GLOBALS['fr_opts']['freeman_core_shop_filters_indexer_enabled'] = '1';

		( new Module() )->boot();

		$this->assertNotFalse( has_action( 'woocommerce_update_product' ) );
		$this->assertNotFalse( has_action( 'woocommerce_variation_set_stock' ) );
	}
}
