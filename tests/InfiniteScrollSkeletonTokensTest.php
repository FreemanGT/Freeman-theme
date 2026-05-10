<?php
declare(strict_types=1);

use Freeman\Core\Modules\InfiniteScroll\Module;
use PHPUnit\Framework\TestCase;

/**
 * Wave 4.3 — InfiniteScroll skeleton/fade tokens exposed as 5 settings,
 * emitted at runtime as `--fm-is-*` CSS custom properties on `:root` via
 * wp_add_inline_style. Defaults map byte-identically to the prior
 * hardcoded CSS values so flag-OFF / no-settings-saved is back-compat.
 *
 * @covers \Freeman\Core\Modules\InfiniteScroll\Module
 */

// Bootstrap stubs are missing is_feed (Module::enqueue() short-circuits
// on admin/feed contexts and the integration test exercises that path)
// and wp_style_is / wp_script_is (used in the deprecated-handle alias
// block). Guarded so other tests can rely on them too.
if ( ! function_exists( 'is_feed' ) ) {
	function is_feed() {
		return ( $GLOBALS['fr_page_type'] ?? '' ) === 'feed';
	}
}
if ( ! function_exists( 'wp_style_is' ) ) {
	function wp_style_is( $handle, $list = 'enqueued' ) {
		return false;
	}
}
if ( ! function_exists( 'wp_script_is' ) ) {
	function wp_script_is( $handle, $list = 'enqueued' ) {
		return false;
	}
}

final class InfiniteScrollSkeletonTokensTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']           = array();
		$GLOBALS['fr_hooks']          = array();
		$GLOBALS['fr_styles_inline']  = array();
		$GLOBALS['fr_localize_calls'] = array();
	}

	public function test_settings_schema_includes_5_new_token_keys_with_correct_types_and_defaults(): void {
		$schema = ( new Module() )->settings_schema();

		$this->assertArrayHasKey( 'shimmer_base_color', $schema );
		$this->assertSame( 'color', $schema['shimmer_base_color']['type'] );
		$this->assertSame( '#eceff3', $schema['shimmer_base_color']['default'] );

		$this->assertArrayHasKey( 'shimmer_highlight_color', $schema );
		$this->assertSame( 'color', $schema['shimmer_highlight_color']['type'] );
		$this->assertSame( '#f6f8fb', $schema['shimmer_highlight_color']['default'] );

		$this->assertArrayHasKey( 'shimmer_duration_ms', $schema );
		$this->assertSame( 'number', $schema['shimmer_duration_ms']['type'] );
		$this->assertSame( 1400, $schema['shimmer_duration_ms']['default'] );

		$this->assertArrayHasKey( 'fade_duration_ms', $schema );
		$this->assertSame( 'number', $schema['fade_duration_ms']['type'] );
		$this->assertSame( 550, $schema['fade_duration_ms']['default'] );

		$this->assertArrayHasKey( 'fade_transform_px', $schema );
		$this->assertSame( 'number', $schema['fade_transform_px']['type'] );
		$this->assertSame( 18, $schema['fade_transform_px']['default'] );
	}

	public function test_inline_css_emits_all_5_vars_at_defaults(): void {
		$css = ( new Module() )->inline_token_css();

		$this->assertStringContainsString( '--fm-is-shimmer-base:', $css );
		$this->assertStringContainsString( '--fm-is-shimmer-highlight:', $css );
		$this->assertStringContainsString( '--fm-is-shimmer-duration:', $css );
		$this->assertStringContainsString( '--fm-is-fade-duration:', $css );
		$this->assertStringContainsString( '--fm-is-fade-transform:', $css );
	}

	public function test_inline_css_default_values_match_existing_hardcoded_css_byte_for_byte(): void {
		// Pre-Wave-4.3 the CSS hardcoded #eceff3 / #f6f8fb / 1.4s / .55s /
		// translateY(18px). Wave 4.3 defaults must serialize so that the
		// :root cascade reproduces those exact values, otherwise flag-OFF
		// / no-settings-saved is not byte-identical to prior render.
		$css = ( new Module() )->inline_token_css();

		$this->assertStringContainsString( '--fm-is-shimmer-base:#eceff3;', $css );
		$this->assertStringContainsString( '--fm-is-shimmer-highlight:#f6f8fb;', $css );
		$this->assertStringContainsString( '--fm-is-shimmer-duration:1400ms;', $css );
		$this->assertStringContainsString( '--fm-is-fade-duration:550ms;', $css );
		$this->assertStringContainsString( '--fm-is-fade-transform:translateY(18px);', $css );
	}

	public function test_inline_css_reflects_custom_settings(): void {
		$GLOBALS['fr_opts']['freeman_core_infinite_scroll_shimmer_base_color']      = '#101010';
		$GLOBALS['fr_opts']['freeman_core_infinite_scroll_shimmer_highlight_color'] = '#fafafa';
		$GLOBALS['fr_opts']['freeman_core_infinite_scroll_shimmer_duration_ms']     = 2000;
		$GLOBALS['fr_opts']['freeman_core_infinite_scroll_fade_duration_ms']        = 800;
		$GLOBALS['fr_opts']['freeman_core_infinite_scroll_fade_transform_px']       = 32;

		$css = ( new Module() )->inline_token_css();

		$this->assertStringContainsString( '--fm-is-shimmer-base:#101010;', $css );
		$this->assertStringContainsString( '--fm-is-shimmer-highlight:#fafafa;', $css );
		$this->assertStringContainsString( '--fm-is-shimmer-duration:2000ms;', $css );
		$this->assertStringContainsString( '--fm-is-fade-duration:800ms;', $css );
		$this->assertStringContainsString( '--fm-is-fade-transform:translateY(32px);', $css );
	}

	public function test_empty_color_setting_falls_back_to_default(): void {
		// Settings_Hub `color` validator returns '' for invalid hex, so the
		// post-rejection persisted state is the empty string. Module must
		// treat empty as "use default" so a typo doesn't blank the shimmer.
		$GLOBALS['fr_opts']['freeman_core_infinite_scroll_shimmer_base_color']      = '';
		$GLOBALS['fr_opts']['freeman_core_infinite_scroll_shimmer_highlight_color'] = '';

		$css = ( new Module() )->inline_token_css();

		$this->assertStringContainsString( '--fm-is-shimmer-base:#eceff3;', $css );
		$this->assertStringContainsString( '--fm-is-shimmer-highlight:#f6f8fb;', $css );
	}

	public function test_wp_add_inline_style_attaches_token_block_to_handle(): void {
		( new Module() )->enqueue();

		$this->assertArrayHasKey( 'freeman-core-infinite-scroll', $GLOBALS['fr_styles_inline'] );
		$attached = $GLOBALS['fr_styles_inline']['freeman-core-infinite-scroll'];
		$this->assertNotEmpty( $attached, 'wp_add_inline_style was called but captured nothing' );
		$this->assertStringContainsString( '--fm-is-shimmer-base:', $attached[0] );
	}
}
