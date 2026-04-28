<?php
declare(strict_types=1);

require_once __DIR__ . '/snapshots/__fixtures__/wc_product_stub.php';

use Freeman\Core\Modules\ProductFeed\Generator;
use Freeman\Core\Modules\ProductFeed\Server;
use PHPUnit\Framework\TestCase;

// WP/WC stubs — guarded so they don't collide with XmlSnapshotTest's stubs.
// `get_posts` and `wc_get_product` are smart: they consult $GLOBALS so each
// test can inject its own return shape.
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $key = '' ) {
		return 'url' === $key ? 'https://example.test' : '';
	}
}
if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency() {
		return 'USD';
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$GLOBALS['fr_get_posts_args_history'][] = $args;
		// Pop one batch off the queue per call so we can return ids on the
		// first call and [] on the second to terminate the while loop.
		$queue = $GLOBALS['fr_get_posts_queue'] ?? array();
		if ( empty( $queue ) ) {
			return array();
		}
		return array_shift( $GLOBALS['fr_get_posts_queue'] );
	}
}
if ( ! function_exists( '_prime_post_caches' ) ) {
	function _prime_post_caches( $ids, $u1 = true, $u2 = true ) {}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $id ) { return 'https://example.test/?p=' . (int) $id; }
}
if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $id, $tax ) { return array(); }
}
if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return ''; }
}
if ( ! function_exists( 'wc_attribute_label' ) ) {
	function wc_attribute_label( $name ) { return (string) $name; }
}
if ( ! function_exists( 'wc_get_product_terms' ) ) {
	function wc_get_product_terms( $pid, $name, $args = array() ) { return array(); }
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $tax ) { return false; }
}
if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( $field, $value, $tax ) { return null; }
}
if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) {
		return $GLOBALS['fr_wc_get_product_return'] ?? null;
	}
}
if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( $key, $group = '' ) {}
}
if ( ! function_exists( 'gc_collect_cycles' ) ) {
	// Built-in in PHP — guard anyway in case of CLI flag oddity.
}

/**
 * @covers \Freeman\Core\Modules\ProductFeed\Generator
 * @covers \Freeman\Core\Modules\ProductFeed\Server
 */
final class ProductFeedHooksTest extends TestCase {

	private string $tmp_file = '';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                  = array();
		$GLOBALS['fr_hooks']                 = array();
		$GLOBALS['fr_get_posts_queue']       = array();
		$GLOBALS['fr_get_posts_args_history'] = array();
		$GLOBALS['fr_wc_get_product_return'] = null;
		$GLOBALS['fr_query_vars']            = array();

		$this->tmp_file = sys_get_temp_dir() . '/freeman_feed_hooks_' . uniqid() . '.xml.gz';
	}

	protected function tearDown(): void {
		if ( $this->tmp_file && file_exists( $this->tmp_file ) ) {
			unlink( $this->tmp_file );
		}
		if ( $this->tmp_file && file_exists( $this->tmp_file . '.size' ) ) {
			unlink( $this->tmp_file . '.size' );
		}
		parent::tearDown();
	}

	public function test_query_args_filter_fires_with_offset_and_can_mutate_args(): void {
		// Empty queue → write_feed bails after first get_posts. Filter still fires.
		$captured = array();
		add_filter(
			'freeman_core/product_feed/query_args',
			static function ( $args, $offset ) use ( &$captured ) {
				$captured[] = array( 'args' => $args, 'offset' => $offset );
				$args['post_status'] = 'private'; // mutate
				return $args;
			},
			10,
			2
		);

		$this->invoke_write_feed();

		$this->assertCount( 1, $captured );
		$this->assertSame( 'product', $captured[0]['args']['post_type'] );
		$this->assertSame( 0, $captured[0]['offset'] );
		// Mutation reached get_posts.
		$this->assertSame( 'private', $GLOBALS['fr_get_posts_args_history'][0]['post_status'] );
	}

	public function test_item_filter_can_mutate_xml_per_product(): void {
		$GLOBALS['fr_get_posts_queue']       = array( array( 42 ) );
		$GLOBALS['fr_wc_get_product_return'] = new \WC_Product();

		$captured = array();
		add_filter(
			'freeman_core/product_feed/item',
			static function ( $xml, $product ) use ( &$captured ) {
				$captured[] = array( 'xml_len' => strlen( $xml ), 'product' => $product );
				return $xml . "\n  <!-- injected -->\n";
			},
			10,
			2
		);

		$this->invoke_write_feed();

		$this->assertCount( 1, $captured );
		$this->assertGreaterThan( 100, $captured[0]['xml_len'], 'Filter should receive a non-trivial XML block' );
		$this->assertInstanceOf( \WC_Product::class, $captured[0]['product'] );

		// Mutation must reach the gzipped output.
		$gz  = gzopen( $this->tmp_file, 'rb' );
		$xml = '';
		while ( ! gzeof( $gz ) ) {
			$xml .= gzread( $gz, 8192 );
		}
		gzclose( $gz );
		$this->assertStringContainsString( 'injected', $xml );
	}

	public function test_before_serve_fires_when_query_var_is_set(): void {
		$fired = 0;
		add_action(
			'freeman_core/product_feed/before_serve',
			static function () use ( &$fired ) {
				++$fired;
				// Throw to escape serve_feed before headers/exit run.
				throw new \RuntimeException( 'before_serve listener short-circuit' );
			}
		);

		$GLOBALS['fr_query_vars'][ Server::QUERY_VAR ] = 1;

		try {
			( new Server( new Generator() ) )->serve_feed();
			$this->fail( 'Expected listener exception was not thrown' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'before_serve listener short-circuit', $e->getMessage() );
		}

		$this->assertSame( 1, $fired );
	}

	public function test_before_serve_does_not_fire_on_unrelated_request(): void {
		$fired = 0;
		add_action(
			'freeman_core/product_feed/before_serve',
			static function () use ( &$fired ) {
				++$fired;
			}
		);

		// No query var set — serve_feed returns early.
		( new Server( new Generator() ) )->serve_feed();

		$this->assertSame( 0, $fired );
	}

	/**
	 * after_generate fires deep inside generate(), which acquires a flock and
	 * runs write_feed. We can't fully exercise that under PHPUnit (no
	 * WooCommerce loaded → generate's class_exists guard returns early), so
	 * the most reliable signal that the call site is wired correctly is the
	 * source-presence assertion in BaselinesIntegrityTest. This test gives us
	 * a unit-level sanity check by verifying the hook *name* the source uses.
	 *
	 * If you rename the action and forget to update this test, it'll fail —
	 * making the rename visible during review.
	 */
	public function test_after_generate_hook_name_is_documented_constant(): void {
		$src = file_get_contents( FREEMAN_CORE_PATH . 'src/Modules/ProductFeed/Generator.php' );
		$this->assertStringContainsString(
			"do_action( 'freeman_core/product_feed/after_generate'",
			$src,
			'Hook name drift — update tests/ProductFeedHooksTest.php and HOOKS.md to match'
		);
	}

	private function invoke_write_feed(): void {
		$gen = new Generator();
		$ref = new \ReflectionClass( $gen );
		$m   = $ref->getMethod( 'write_feed' );
		$m->setAccessible( true );
		$m->invoke( $gen, $this->tmp_file );
	}
}
