<?php
declare(strict_types=1);

require_once __DIR__ . '/SnapshotTestCase.php';
require_once __DIR__ . '/Scrubber.php';
require_once __DIR__ . '/__fixtures__/wc_product_stub.php';

use Freeman\Core\Modules\ProductFeed\Generator;
use Freeman\Tests\Snapshots\Scrubber;
use Freeman\Tests\Snapshots\SnapshotTestCase;
use PHPUnit\Framework\TestCase;

// WP/WC stubs reached only by ProductFeed snapshot tests. Each is guarded so
// it does not collide with bootstrap.php or other tests.
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
		return array();
	}
}
if ( ! function_exists( '_prime_post_caches' ) ) {
	function _prime_post_caches( $ids, $update_term = true, $update_meta = true ) {}
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
	function wc_get_product( $id ) { return null; }
}

final class XmlSnapshotTest extends TestCase {
	use SnapshotTestCase;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_empty_feed_shell_matches_golden(): void {
		$gen = new Generator();

		// Generator::write_feed() is private; invoke via Reflection. Its only
		// observable side effect under our stubs is the gzipped tmp file.
		$tmp = sys_get_temp_dir() . '/freeman_xml_snapshot_' . uniqid() . '.xml.gz';
		try {
			$ref = new \ReflectionClass( $gen );
			$m   = $ref->getMethod( 'write_feed' );
			$m->setAccessible( true );
			$m->invoke( $gen, $tmp );

			$this->assertFileExists( $tmp );
			$gz = gzopen( $tmp, 'rb' );
			$xml = '';
			while ( ! gzeof( $gz ) ) {
				$xml .= gzread( $gz, 8192 );
			}
			gzclose( $gz );
		} finally {
			if ( file_exists( $tmp ) ) {
				unlink( $tmp );
			}
			if ( file_exists( $tmp . '.size' ) ) {
				unlink( $tmp . '.size' );
			}
		}

		$scrubbed = Scrubber::versions( Scrubber::timestamps( $xml ) );
		$scrubbed = Scrubber::site_url( $scrubbed, 'https://example.test' );

		$this->assertSnapshotMatches( 'product_feed_empty_shell.xml', $scrubbed );
	}

	public function test_single_product_xml_matches_golden(): void {
		$gen     = new Generator();
		$product = new \WC_Product();

		$ref = new \ReflectionClass( $gen );
		$m   = $ref->getMethod( 'product_xml' );
		$m->setAccessible( true );
		$xml = $m->invoke( $gen, $product );

		// product_xml output is deterministic against the stub; no scrubbing
		// needed. Currency comes from price_fields() / get_woocommerce_currency().
		$this->assertSnapshotMatches( 'product_feed_single_product.xml', $xml );
	}
}
