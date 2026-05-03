<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Wave 2.2 / 4c — tooltip helper + payload contract.
 *
 * Exercises Etucart_VS_Plugin::tooltip_meta_key / term_tooltip_text. Test
 * stubs (get_term_meta) live in tests/bootstrap.php (promoted in 4b).
 *
 * @covers \Etucart_VS_Plugin::tooltip_meta_key
 * @covers \Etucart_VS_Plugin::term_tooltip_text
 */
final class VariationSwatchesTooltipTest extends TestCase {

	private const PLUGIN_FILE = __DIR__ . '/../freeman-core/src/Modules/VariationSwatches/legacy/includes/class-plugin.php';
	private const META_KEY    = 'freeman_core_variation_swatches_term_tooltip_text';

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ETUCART_VS_VERSION' ) ) {
			define( 'ETUCART_VS_VERSION', '1.11.25' );
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
		$GLOBALS['fr_opts']      = array();
		$GLOBALS['fr_hooks']     = array();
		$GLOBALS['fr_term_meta'] = array();
	}

	public function test_tooltip_meta_key_is_namespaced_under_freeman_core(): void {
		$this->assertSame( self::META_KEY, \Etucart_VS_Plugin::tooltip_meta_key() );
		$this->assertStringStartsWith( 'freeman_core_', \Etucart_VS_Plugin::tooltip_meta_key() );
		$this->assertStringNotContainsString( 'etucart_', \Etucart_VS_Plugin::tooltip_meta_key() );
	}

	public function test_term_tooltip_text_returns_default_when_meta_unset(): void {
		$this->assertSame( 'Blue', \Etucart_VS_Plugin::term_tooltip_text( 7, 'Blue' ) );
	}

	public function test_term_tooltip_text_returns_override_when_meta_set(): void {
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = 'Royal Blue Sapphire';

		$this->assertSame( 'Royal Blue Sapphire', \Etucart_VS_Plugin::term_tooltip_text( 7, 'Blue' ) );
	}

	public function test_term_tooltip_text_returns_empty_string_when_both_unset_and_no_default(): void {
		$this->assertSame( '', \Etucart_VS_Plugin::term_tooltip_text( 7 ) );
	}

	public function test_term_tooltip_text_falls_back_to_default_when_override_is_empty_string(): void {
		// Empty stored value should not shadow a non-empty default.
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = '';

		$this->assertSame( 'Blue', \Etucart_VS_Plugin::term_tooltip_text( 7, 'Blue' ) );
	}

	public function test_term_tooltip_text_uses_override_even_when_default_empty(): void {
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = 'Blue';

		$this->assertSame( 'Blue', \Etucart_VS_Plugin::term_tooltip_text( 7 ) );
	}

	public function test_term_tooltip_text_returns_string_type(): void {
		// term-meta could store a non-string (legacy data, hand-edited DB).
		// term_tooltip_text must always return a string for the template's esc_attr.
		$GLOBALS['fr_term_meta'][7][ self::META_KEY ] = 42;

		$this->assertSame( 'fallback', \Etucart_VS_Plugin::term_tooltip_text( 7, 'fallback' ) );
	}
}
