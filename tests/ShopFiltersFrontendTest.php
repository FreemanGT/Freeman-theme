<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Ajax;
use Freeman\Core\Modules\ShopFilters\Module;
use Freeman\Core\Modules\ShopFilters\Shortcode;
use PHPUnit\Framework\TestCase;

/**
 * Frontend read-path wiring (Phase 6.3a): the shortcode and public AJAX
 * endpoint register on boot.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Ajax
 * @covers \Freeman\Core\Modules\ShopFilters\Shortcode
 * @covers \Freeman\Core\Modules\ShopFilters\Module
 */
final class ShopFiltersFrontendTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']       = array();
		$GLOBALS['fr_hooks']      = array();
		$GLOBALS['fr_cron']       = array();
		$GLOBALS['fr_shortcodes'] = array();
	}

	public function test_ajax_register_attaches_both_public_actions(): void {
		( new Ajax() )->register();

		$this->assertNotFalse( has_action( 'wp_ajax_' . Ajax::ACTION ) );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_' . Ajax::ACTION ) );
	}

	public function test_shortcode_register_adds_the_tag(): void {
		( new Shortcode() )->register();

		$this->assertArrayHasKey( Shortcode::TAG, $GLOBALS['fr_shortcodes'] );
	}

	public function test_boot_wires_frontend(): void {
		( new Module() )->boot();
		$this->assertArrayHasKey( Shortcode::TAG, $GLOBALS['fr_shortcodes'] );
		$this->assertNotFalse( has_action( 'wp_ajax_' . Ajax::ACTION ) );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_' . Ajax::ACTION ) );
	}
}
