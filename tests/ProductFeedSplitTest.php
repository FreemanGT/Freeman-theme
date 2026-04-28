<?php
declare(strict_types=1);

use Freeman\Core\Modules\ProductFeed\Generator;
use Freeman\Core\Modules\ProductFeed\Module;
use Freeman\Core\Modules\ProductFeed\Server;
use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the 1.4.0 ProductFeed split. The refactor carved
 * generation into Generator and serving into Server — these tests make
 * sure the backward-compat surface on Module still works so third-party
 * callers of `$module->generate_feed()` / `feed_file()` etc. don't break.
 */
final class ProductFeedSplitTest extends TestCase {

	private Module $module;

	protected function setUp(): void {
		parent::setUp();
		$this->module = new Module();
	}

	public function test_generator_and_server_lazy_instantiate(): void {
		$this->assertInstanceOf( Generator::class, $this->module->generator() );
		$this->assertInstanceOf( Server::class, $this->module->server() );
	}

	public function test_bc_proxies_delegate_to_generator(): void {
		$gen = $this->module->generator();
		$this->assertSame( $gen->feed_dir(),  $this->module->feed_dir() );
		$this->assertSame( $gen->feed_url(),  $this->module->feed_url() );
		$this->assertSame( $gen->feed_file(), $this->module->feed_file() );
		$this->assertSame( $gen->lock_file(), $this->module->lock_file() );
	}

	public function test_bc_class_constants_still_exist(): void {
		$this->assertTrue( defined( Module::class . '::BATCH' ) );
		$this->assertTrue( defined( Module::class . '::REWRITE_SLUG' ) );
		$this->assertTrue( defined( Module::class . '::QUERY_VAR' ) );
		$this->assertTrue( defined( Module::class . '::OPT_LAST_GEN' ) );
		$this->assertSame( Generator::BATCH,          Module::BATCH );
		$this->assertSame( Server::REWRITE_SLUG,      Module::REWRITE_SLUG );
		$this->assertSame( Server::QUERY_VAR,         Module::QUERY_VAR );
		$this->assertSame( Generator::OPT_LAST_GEN,   Module::OPT_LAST_GEN );
	}

	public function test_server_uses_same_generator_paths(): void {
		$server = $this->module->server();
		// public_url should be derived — we can't call home_url() meaningfully
		// under the stub, but it should at least contain the rewrite slug.
		$this->assertStringContainsString( Server::REWRITE_SLUG, $server->public_url() );
	}
}
