<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Stubs needed by 4b's helpers (term meta + attachment image src + EXISTS-filter
// `get_terms`) live in tests/bootstrap.php so they load before any test file's
// function_exists guards. Backward-compatible: existing tests that set
// $GLOBALS['fr_get_terms_return'] still get that array verbatim from the
// promoted stub.

/**
 * Wave 2.2 / 4b — image-swatches term-meta read path + filter contract.
 *
 * Exercises the new helpers on Etucart_VS_Plugin (image_meta_key,
 * term_image_id, term_image_url, attribute_has_images) and the additive
 * `freeman_core/variation_swatches/term_image_url` filter.
 *
 * Does NOT cover the option-payload assembly (that path runs through
 * Etucart_VS_Archive::prepare_product_data which depends on the full
 * \WC_Product_Variable stack — see VariationSwatchesCardImagePayloadSnapshotTest
 * for the precedent of unit-testing extracted helpers without the WC stack).
 *
 * @covers \Etucart_VS_Plugin::image_meta_key
 * @covers \Etucart_VS_Plugin::term_image_id
 * @covers \Etucart_VS_Plugin::term_image_url
 * @covers \Etucart_VS_Plugin::attribute_has_images
 */
final class VariationSwatchesImageSwatchesSnapshotTest extends TestCase {

	private const PLUGIN_FILE = __DIR__ . '/../freeman-core/src/Modules/VariationSwatches/legacy/includes/class-plugin.php';
	private const META_KEY    = 'freeman_core_variation_swatches_term_image_id';

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ETUCART_VS_VERSION' ) ) {
			define( 'ETUCART_VS_VERSION', '1.11.24' );
		}
		if ( ! defined( 'ETUCART_VS_DIR' ) ) {
			define( 'ETUCART_VS_DIR', dirname( self::PLUGIN_FILE ) . '/' );
		}
		if ( ! defined( 'ETUCART_VS_URL' ) ) {
			define( 'ETUCART_VS_URL', 'https://example.test/' );
		}
		require_once self::PLUGIN_FILE;
	}

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']        = array();
		$GLOBALS['fr_hooks']       = array();
		$GLOBALS['fr_term_meta']   = array();
		$GLOBALS['fr_terms']       = array();
		$GLOBALS['fr_attachments'] = array();
		// CategorySliderHooksTest sets this global; clear it so the bootstrap
		// stub uses the 4b path (taxonomy + meta_query) instead of returning
		// whatever the previous test left in fr_get_terms_return.
		unset( $GLOBALS['fr_get_terms_return'] );
	}

	public function test_image_meta_key_is_namespaced_under_freeman_core(): void {
		$this->assertSame( self::META_KEY, \Etucart_VS_Plugin::image_meta_key() );
		$this->assertStringStartsWith( 'freeman_core_', \Etucart_VS_Plugin::image_meta_key() );
		$this->assertStringNotContainsString( 'etucart_', \Etucart_VS_Plugin::image_meta_key() );
	}

	public function test_term_image_id_returns_zero_when_meta_unset(): void {
		$this->assertSame( 0, \Etucart_VS_Plugin::term_image_id( 7 ) );
	}

	public function test_term_image_id_returns_int_when_meta_set(): void {
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = '42';

		$this->assertSame( 42, \Etucart_VS_Plugin::term_image_id( 7 ) );
	}

	public function test_term_image_url_returns_empty_when_no_image_id(): void {
		$this->assertSame( '', \Etucart_VS_Plugin::term_image_url( 7 ) );
	}

	public function test_term_image_url_returns_attachment_url_when_set(): void {
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = 42;
		$GLOBALS['fr_attachments'][42]['thumbnail']   = 'https://example.test/blue-thumb.jpg';

		$this->assertSame( 'https://example.test/blue-thumb.jpg', \Etucart_VS_Plugin::term_image_url( 7 ) );
	}

	public function test_term_image_url_returns_empty_when_attachment_missing(): void {
		// Term meta points to a non-existent attachment.
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = 999;

		$this->assertSame( '', \Etucart_VS_Plugin::term_image_url( 7 ) );
	}

	public function test_term_image_url_filter_runs_with_full_signature(): void {
		$captured = array();
		add_filter(
			'freeman_core/variation_swatches/term_image_url',
			static function ( $url, $term_id, $size ) use ( &$captured ) {
				$captured = array(
					'url'     => $url,
					'term_id' => $term_id,
					'size'    => $size,
				);
				return $url . '?cdn=replaced';
			},
			10,
			3
		);
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = 42;
		$GLOBALS['fr_attachments'][42]['thumbnail']   = 'https://example.test/blue.jpg';

		$out = \Etucart_VS_Plugin::term_image_url( 7, 'thumbnail' );

		$this->assertSame( 'https://example.test/blue.jpg?cdn=replaced', $out );
		$this->assertSame( 7, $captured['term_id'] );
		$this->assertSame( 'thumbnail', $captured['size'] );
		$this->assertSame( 'https://example.test/blue.jpg', $captured['url'] );
	}

	public function test_term_image_url_filter_fires_even_with_empty_url(): void {
		// Filter consumers may want to substitute a default image when none is set.
		$fired = false;
		add_filter(
			'freeman_core/variation_swatches/term_image_url',
			static function ( $url ) use ( &$fired ) {
				$fired = true;
				return $url;
			}
		);

		\Etucart_VS_Plugin::term_image_url( 7 );

		$this->assertTrue( $fired, 'Filter must fire even when no image is set so consumers can substitute defaults.' );
	}

	public function test_attribute_has_images_returns_false_when_no_term_has_image(): void {
		// Unique taxonomy name per test — attribute_has_images uses a static
		// per-request cache on the taxonomy key, so reusing a name across
		// tests would leak cached results.
		$GLOBALS['fr_terms']['pa_no_images'] = array();

		$this->assertFalse( \Etucart_VS_Plugin::attribute_has_images( 'pa_no_images' ) );
	}

	public function test_attribute_has_images_returns_true_when_at_least_one_term_has_image(): void {
		$GLOBALS['fr_terms']['pa_with_images']         = array( 7 );
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = 42;

		$this->assertTrue( \Etucart_VS_Plugin::attribute_has_images( 'pa_with_images' ) );
	}

	public function test_attribute_has_images_caches_per_request(): void {
		// First call populates the static cache. A subsequent change to the
		// underlying terms must not affect the second call's return value.
		$GLOBALS['fr_terms']['pa_cache_check'] = array();
		$first = \Etucart_VS_Plugin::attribute_has_images( 'pa_cache_check' );

		$GLOBALS['fr_terms']['pa_cache_check']         = array( 9 );
		$GLOBALS['fr_term_meta'][9][ self::META_KEY ] = 100;
		$second = \Etucart_VS_Plugin::attribute_has_images( 'pa_cache_check' );

		$this->assertFalse( $first );
		$this->assertSame( $first, $second, 'Static cache must hold for the request.' );
	}
}
