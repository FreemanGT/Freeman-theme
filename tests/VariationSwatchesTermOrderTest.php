<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Bug fix (1.11.50) — variation swatch options now render in
 * wc_get_product_terms() order rather than variation-insertion order.
 *
 * Tests the pure half (apply_term_order). The dispatch half
 * (reorder_options_to_match_terms) is exercised in integration on the live site
 * — pre-flight 0 capture from PR description.
 *
 * @covers \Etucart_VS_Plugin::apply_term_order
 */
final class VariationSwatchesTermOrderTest extends TestCase {

	private const PLUGIN_FILE = __DIR__ . '/../freeman-core/src/Modules/VariationSwatches/legacy/includes/class-plugin.php';

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ETUCART_VS_VERSION' ) ) {
			define( 'ETUCART_VS_VERSION', '1.11.50' );
		}
		if ( ! defined( 'ETUCART_VS_DIR' ) ) {
			define( 'ETUCART_VS_DIR', dirname( self::PLUGIN_FILE ) . '/' );
		}
		if ( ! defined( 'ETUCART_VS_URL' ) ) {
			define( 'ETUCART_VS_URL', 'https://example.test/' );
		}
		require_once self::PLUGIN_FILE;
	}

	public function test_reorders_options_to_match_term_slug_order(): void {
		$out = \Etucart_VS_Plugin::apply_term_order(
			array( 'm', 's', 'xl', 'l' ),
			array( 's', 'm', 'l', 'xl' )
		);

		$this->assertSame( array( 's', 'm', 'l', 'xl' ), $out );
	}

	public function test_returns_input_unchanged_for_empty_term_list(): void {
		$out = \Etucart_VS_Plugin::apply_term_order(
			array( 'm', 's' ),
			array()
		);

		$this->assertSame( array( 'm', 's' ), $out );
	}

	public function test_appends_unknown_slugs_to_tail(): void {
		// 'custom-slug' has no matching term — must not be dropped, must land at the tail.
		$out = \Etucart_VS_Plugin::apply_term_order(
			array( 'm', 's', 'custom-slug' ),
			array( 's', 'm' )
		);

		$this->assertSame( array( 's', 'm', 'custom-slug' ), $out );
	}
}
