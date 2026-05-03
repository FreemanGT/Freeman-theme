<?php
declare(strict_types=1);

use Freeman\Core\Modules\VariationSwatches\Color_Sampler;
use Freeman\Core\Core\Logger;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../freeman-core/src/Modules/VariationSwatches/legacy/includes/class-plugin.php';

// Test-local stubs for the WP / WC bits that Color_Sampler::resolve_term_color
// reaches. Inline-stubbed (not promoted) — these are 4e-specific; promote if a
// future PR needs them. The post-meta / metadata_exists / parent-id / attached-
// file stubs are defined identically in tests/VariationSwatchesAutoColorHooksTest
// (4d); guarded here so this file is self-sufficient regardless of test order.
if ( ! function_exists( 'get_term' ) ) {
	function get_term( $term_id, $taxonomy = '' ) {
		$row = $GLOBALS['fr_terms_objects'][ (int) $term_id ] ?? null;
		if ( null === $row ) {
			return null;
		}
		return (object) $row;
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}
// wc_get_product is also stubbed by RestockNotifyFrontendTest et al. using
// `$GLOBALS['fr_wc_get_product_return']`. Match that convention so whichever
// stub wins under a full-suite run still resolves to our product double.
if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) {
		return $GLOBALS['fr_wc_get_product_return'] ?? null;
	}
}
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
		return $GLOBALS['fr_attachment_paths'][ $attachment_id ] ?? '';
	}
}

/**
 * Minimal variable-product double — declares only the methods
 * Color_Sampler::resolve_term_color reaches.
 */
final class Test_AutoColor_Variable_Product {
	/** @var array<int,array<string,mixed>> */
	private array $variations;

	public function __construct( array $variations ) {
		$this->variations = $variations;
	}

	public function get_available_variations(): array {
		return $this->variations;
	}
}

/**
 * Wave 2.2 / 4e — render-path color resolution.
 *
 * Drives Color_Sampler::resolve_term_color() through every branch of the
 * sealed resolution order (manual term-meta → sampled meta → fallback) plus
 * the disagreement filter, the empty-sentinel rule, and the flag-OFF
 * byte-identity contract.
 *
 * @covers \Freeman\Core\Modules\VariationSwatches\Color_Sampler::resolve_term_color
 */
final class VariationSwatchesAutoColorRenderTest extends TestCase {

	private const FLAG_OPT  = 'freeman_core_variation_swatches_auto_color_enabled';
	private const FILTER    = 'freeman_core/variation_swatches/auto_color_disagreement_fallback';
	private const COLOR_KEY = 'etucart_swatch_color';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                  = array();
		$GLOBALS['fr_term_meta']             = array();
		$GLOBALS['fr_terms_objects']         = array();
		$GLOBALS['fr_post_meta']             = array();
		$GLOBALS['fr_wc_get_product_return'] = null;
		$GLOBALS['fr_hooks']                 = array();
		$GLOBALS['fr_auto_color_logged']     = array();
		Logger::clear();
	}

	private function flag_on(): void {
		update_option( self::FLAG_OPT, 1 );
	}

	private function register_term( int $term_id, string $taxonomy, string $slug ): void {
		$GLOBALS['fr_terms_objects'][ $term_id ] = array(
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'slug'     => $slug,
			'name'     => ucfirst( $slug ),
		);
	}

	/**
	 * Build a variable product double with `$variations` (each is
	 * [variation_id, attribute_<tax> => slug, sampled_hex]).
	 */
	private function register_product( int $product_id, array $variations ): void {
		$rows = array();
		foreach ( $variations as $row ) {
			$variation_id = (int) $row['variation_id'];
			$attrs        = array();
			foreach ( $row as $k => $v ) {
				if ( 'variation_id' === $k || 'sampled_hex' === $k ) {
					continue;
				}
				$attrs[ (string) $k ] = (string) $v;
			}
			$rows[] = array( 'variation_id' => $variation_id, 'attributes' => $attrs );
			if ( array_key_exists( 'sampled_hex', $row ) ) {
				// Pre-seed the sampled-color post-meta so sample_if_missing returns it.
				$GLOBALS['fr_post_meta'][ $variation_id ][ Color_Sampler::META_KEY ] = (string) $row['sampled_hex'];
			}
		}
		// Single-slot stub convention (matches RestockNotifyFrontendTest et al.).
		// Tests in this file each touch a single product, so this is sufficient.
		$GLOBALS['fr_wc_get_product_return'] = new Test_AutoColor_Variable_Product( $rows );
	}

	/* ------------------------------------------------------------------ *
	 * Test 1 — Flag-OFF: byte-identical to term_color()
	 * ------------------------------------------------------------------ */

	public function test_flag_off_returns_term_color_byte_identical(): void {
		// Manual term-meta empty, flag OFF → both should return ''.
		$this->register_term( 10, 'pa_color', 'red' );

		$direct  = \Etucart_VS_Plugin::term_color( 10 );
		$wrapped = Color_Sampler::resolve_term_color( 10, 99 );

		$this->assertSame( '', $direct );
		$this->assertSame( $direct, $wrapped, 'Flag-OFF must be byte-identical to term_color().' );

		// Manual term-meta set, flag OFF → both should return the manual hex.
		update_term_meta( 10, self::COLOR_KEY, '#aabbcc' );
		$direct2  = \Etucart_VS_Plugin::term_color( 10 );
		$wrapped2 = Color_Sampler::resolve_term_color( 10, 99 );
		$this->assertSame( '#AABBCC', $direct2 );
		$this->assertSame( $direct2, $wrapped2, 'Flag-OFF with manual hex must match term_color().' );
	}

	/* ------------------------------------------------------------------ *
	 * Test 2 — Manual hex wins; sampler not consulted
	 * ------------------------------------------------------------------ */

	public function test_flag_on_manual_hex_wins_sampler_not_consulted(): void {
		$this->flag_on();
		$this->register_term( 10, 'pa_color', 'red' );
		update_term_meta( 10, self::COLOR_KEY, '#112233' );

		// Register a variation with a DIFFERENT sampled hex — must NOT be returned.
		$this->register_product( 99, array(
			array( 'variation_id' => 201, 'attribute_pa_color' => 'red', 'sampled_hex' => '#FF00FF' ),
		) );

		$this->assertSame( '#112233', Color_Sampler::resolve_term_color( 10, 99 ) );
	}

	/* ------------------------------------------------------------------ *
	 * Test 3 — Single variation with sampled hex
	 * ------------------------------------------------------------------ */

	public function test_flag_on_no_manual_single_sampled_hex_returned(): void {
		$this->flag_on();
		$this->register_term( 10, 'pa_color', 'red' );
		$this->register_product( 99, array(
			array( 'variation_id' => 201, 'attribute_pa_color' => 'red', 'sampled_hex' => '#3366CC' ),
		) );

		$this->assertSame( '#3366CC', Color_Sampler::resolve_term_color( 10, 99 ) );
	}

	/* ------------------------------------------------------------------ *
	 * Test 4 — Multiple variations agree
	 * ------------------------------------------------------------------ */

	public function test_flag_on_no_manual_agreement_returns_agreed_hex(): void {
		$this->flag_on();
		$this->register_term( 10, 'pa_color', 'red' );
		$this->register_product( 99, array(
			array( 'variation_id' => 201, 'attribute_pa_color' => 'red', 'sampled_hex' => '#3366cc' ),
			array( 'variation_id' => 202, 'attribute_pa_color' => 'red', 'sampled_hex' => '#3366CC' ),
			array( 'variation_id' => 203, 'attribute_pa_color' => 'red', 'sampled_hex' => '#3366CC' ),
			// Different term — must be excluded from this term's reconciliation.
			array( 'variation_id' => 204, 'attribute_pa_color' => 'blue', 'sampled_hex' => '#FF0000' ),
		) );

		$this->assertSame( '#3366CC', Color_Sampler::resolve_term_color( 10, 99 ) );
	}

	/* ------------------------------------------------------------------ *
	 * Test 5 — Disagreement → gray + logger fires once with canonical set
	 * ------------------------------------------------------------------ */

	public function test_flag_on_disagreement_returns_gray_and_logs_once(): void {
		$this->flag_on();
		$this->register_term( 10, 'pa_color', 'red' );
		$this->register_product( 99, array(
			array( 'variation_id' => 201, 'attribute_pa_color' => 'red', 'sampled_hex' => '#FF0000' ),
			array( 'variation_id' => 202, 'attribute_pa_color' => 'red', 'sampled_hex' => '#FF0001' ),
			array( 'variation_id' => 203, 'attribute_pa_color' => 'red', 'sampled_hex' => '#FF0001' ), // dedupes
		) );

		$first  = Color_Sampler::resolve_term_color( 10, 99 );
		$second = Color_Sampler::resolve_term_color( 10, 99 );

		$this->assertSame( '#CCCCCC', $first );
		$this->assertSame( '#CCCCCC', $second );

		$entries = array_values( array_filter(
			Logger::entries(),
			static function ( $e ) {
				return false !== strpos( (string) ( $e['message'] ?? '' ), 'auto-color: disagreement on term 10' );
			}
		) );
		$this->assertCount( 1, $entries, 'Disagreement logger must fire once per term per request.' );

		// Logger payload must contain the canonical sorted, deduped hex set.
		$msg = (string) $entries[0]['message'];
		$this->assertStringContainsString( 'hex set [#FF0000,#FF0001]', $msg );
	}

	/* ------------------------------------------------------------------ *
	 * Test 6 — Disagreement filter overrides; invalid filter return fails safe
	 * ------------------------------------------------------------------ */

	public function test_flag_on_disagreement_filter_overrides_and_invalid_falls_back(): void {
		$this->flag_on();
		$this->register_term( 10, 'pa_color', 'red' );
		$this->register_product( 99, array(
			array( 'variation_id' => 201, 'attribute_pa_color' => 'red', 'sampled_hex' => '#FF0000' ),
			array( 'variation_id' => 202, 'attribute_pa_color' => 'red', 'sampled_hex' => '#00FF00' ),
		) );

		$captured_set = null;
		add_filter( self::FILTER, function ( $default, $set, $term_id, $product_id ) use ( &$captured_set ) {
			$captured_set = $set;
			return '#778899'; // valid hex
		}, 10, 4 );

		$this->assertSame( '#778899', Color_Sampler::resolve_term_color( 10, 99 ) );
		$this->assertSame( array( '#00FF00', '#FF0000' ), $captured_set, '$disagreement_set must be deduped + sorted ascending.' );

		// Reset the rate-limit so the filter on the second resolution gets to fire.
		$GLOBALS['fr_hooks']             = array();
		$GLOBALS['fr_auto_color_logged'] = array();
		add_filter( self::FILTER, function () {
			return 'not-a-hex'; // invalid — must drop back to default gray.
		}, 10, 4 );

		$this->assertSame( '#CCCCCC', Color_Sampler::resolve_term_color( 10, 99 ) );
	}

	/* ------------------------------------------------------------------ *
	 * Test 7 — Mixed: real hex + empty sentinel → empties ignored
	 * ------------------------------------------------------------------ */

	public function test_flag_on_empty_sentinel_ignored_not_disagreement(): void {
		$this->flag_on();
		$this->register_term( 10, 'pa_color', 'red' );
		$this->register_product( 99, array(
			array( 'variation_id' => 201, 'attribute_pa_color' => 'red', 'sampled_hex' => '#3366CC' ),
			array( 'variation_id' => 202, 'attribute_pa_color' => 'red', 'sampled_hex' => '' ), // failed sample
		) );

		// Only #3366CC is a real signal — must be returned, no disagreement.
		$this->assertSame( '#3366CC', Color_Sampler::resolve_term_color( 10, 99 ) );
		$this->assertEmpty(
			array_filter(
				Logger::entries(),
				static function ( $e ) {
					return false !== strpos( (string) ( $e['message'] ?? '' ), 'auto-color: disagreement' );
				}
			),
			'Empty sentinel must not trigger disagreement.'
		);
	}

	/* ------------------------------------------------------------------ *
	 * Test 8 — No variations carry sampled meta → falls through to '' default
	 * ------------------------------------------------------------------ */

	public function test_flag_on_no_sampled_meta_falls_through_to_term_color_default(): void {
		$this->flag_on();
		$this->register_term( 10, 'pa_color', 'red' );
		$this->register_product( 99, array(
			// No sampled_hex on either — META_KEY post-meta absent.
			array( 'variation_id' => 201, 'attribute_pa_color' => 'red' ),
			array( 'variation_id' => 202, 'attribute_pa_color' => 'red' ),
		) );

		// metadata_exists is false (no sampled meta written), so sample_if_missing
		// triggers a fresh sample. With no attachment paths registered, sampling
		// returns '' (writes empty sentinel). After two empty sentinels: 0 real
		// hexes in the set → fall through to '' (legacy term_color() default).
		$this->assertSame( '', Color_Sampler::resolve_term_color( 10, 99 ) );
	}
}
