<?php
declare(strict_types=1);

use Freeman\Core\Modules\InfiniteScroll\Module;
use PHPUnit\Framework\TestCase;

/**
 * Wave 3.1b — PHP wrapper render path + 4 deferred extension hooks.
 *
 * Covers `should_render_wrapper()` predicate, `render_grid_wrapper_open/_close()`
 * render methods (before_render / after_render action fires + envelope markup),
 * `resolve_container_selector()` (selector filter resolution), and CONTRACT 2
 * (flag is master switch — listeners cannot force-enable when flag is off).
 *
 * @covers \Freeman\Core\Modules\InfiniteScroll\Module
 */

// Bootstrap stubs are missing is_search + is_post_type_archive (only is_shop /
// is_product_taxonomy etc. live in tests/bootstrap.php's foreach loop). Define
// guarded so they win once and stay available for any later test that needs
// them.
if ( ! function_exists( 'is_search' ) ) {
	function is_search() {
		return ( $GLOBALS['fr_page_type'] ?? '' ) === 'search';
	}
}
if ( ! function_exists( 'is_post_type_archive' ) ) {
	function is_post_type_archive( $post_types = '' ) {
		$current = $GLOBALS['fr_post_type_archive'] ?? '';
		if ( '' === $current ) {
			return false;
		}
		if ( '' === $post_types || array() === $post_types ) {
			return true;
		}
		return in_array( $current, (array) $post_types, true );
	}
}

final class InfiniteScrollHooksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']               = array();
		$GLOBALS['fr_hooks']              = array();
		$GLOBALS['fr_page_type']          = '';
		$GLOBALS['fr_post_type_archive']  = '';
		$GLOBALS['fr_query_vars']         = array();
	}

	private function flag_on(): void {
		update_option( 'freeman_core_infinite_scroll_trigger_modes_enabled', 1 );
	}

	// -----------------------------------------------------------------
	// Selector filter (test #1–#4)
	// -----------------------------------------------------------------

	public function test_resolve_container_selector_empty_when_no_setting_no_listener(): void {
		$this->assertSame( array(), ( new Module() )->resolve_container_selector() );
	}

	public function test_resolve_container_selector_setting_normalized_to_array(): void {
		update_option( 'freeman_core_infinite_scroll_container_selector', '.my-grid' );
		$this->assertSame( array( '.my-grid' ), ( new Module() )->resolve_container_selector() );
	}

	public function test_selector_filter_overrides_setting(): void {
		update_option( 'freeman_core_infinite_scroll_container_selector', '.from-setting' );
		add_filter(
			'freeman_core/infinite_scroll/selector',
			static function () {
				return '.from-filter';
			}
		);
		$this->assertSame( array( '.from-filter' ), ( new Module() )->resolve_container_selector() );
	}

	public function test_selector_filter_empty_string_falls_back_to_default(): void {
		update_option( 'freeman_core_infinite_scroll_container_selector', '.from-setting' );
		add_filter(
			'freeman_core/infinite_scroll/selector',
			static function () {
				return '';
			}
		);
		// Empty string from filter → resolve to array() (footgun guard);
		// JS-side IIFE then falls back to its hardcoded FALLBACK list.
		$this->assertSame( array(), ( new Module() )->resolve_container_selector() );
	}

	// -----------------------------------------------------------------
	// should_render_wrapper predicate (test #5–#8)
	// -----------------------------------------------------------------

	public function test_should_render_wrapper_returns_false_under_flag_off(): void {
		$GLOBALS['fr_page_type'] = 'shop'; // is_shop() → true
		$this->assertFalse( ( new Module() )->should_render_wrapper() );
	}

	public function test_should_render_wrapper_filter_does_not_fire_under_flag_off(): void {
		$called = 0;
		add_filter(
			'freeman_core/infinite_scroll/should_render_wrapper',
			static function ( $v ) use ( &$called ) {
				$called++;
				return $v;
			}
		);
		$GLOBALS['fr_page_type'] = 'shop';
		( new Module() )->should_render_wrapper();
		$this->assertSame( 0, $called, 'CONTRACT 2: filter must not fire under flag-OFF' );
	}

	public function test_should_render_wrapper_returns_true_under_flag_on_archive(): void {
		$this->flag_on();
		$GLOBALS['fr_page_type'] = 'shop';
		$this->assertTrue( ( new Module() )->should_render_wrapper() );
	}

	public function test_should_render_wrapper_filter_can_override_within_flag_on(): void {
		$this->flag_on();

		// Force-true on a non-archive context (custom archive use case).
		$GLOBALS['fr_page_type'] = '';
		add_filter(
			'freeman_core/infinite_scroll/should_render_wrapper',
			static function () {
				return true;
			}
		);
		$this->assertTrue( ( new Module() )->should_render_wrapper(), 'listener can force-enable on custom context' );

		// Reset listeners; verify force-disable on an otherwise-archive context.
		$GLOBALS['fr_hooks']     = array();
		$GLOBALS['fr_page_type'] = 'shop';
		add_filter(
			'freeman_core/infinite_scroll/should_render_wrapper',
			static function () {
				return false;
			}
		);
		$this->assertFalse( ( new Module() )->should_render_wrapper(), 'listener can force-disable on archive' );
	}

	// -----------------------------------------------------------------
	// Render methods (test #9–#10)
	// -----------------------------------------------------------------

	public function test_before_and_after_render_fire_when_predicate_true(): void {
		$this->flag_on();
		$GLOBALS['fr_page_type'] = 'shop';

		$before = 0;
		$after  = 0;
		add_action(
			'freeman_core/infinite_scroll/before_render',
			static function () use ( &$before ) {
				$before++;
			}
		);
		add_action(
			'freeman_core/infinite_scroll/after_render',
			static function () use ( &$after ) {
				$after++;
			}
		);

		$module = new Module();

		ob_start();
		$module->render_grid_wrapper_open();
		$open_output = ob_get_clean();

		$this->assertSame( 1, $before, 'before_render fires once on open bracket' );
		$this->assertSame( 0, $after, 'after_render does not fire yet on open bracket' );
		$this->assertStringContainsString( '<div class="freeman-is-wrapper">', $open_output );

		ob_start();
		$module->render_grid_wrapper_close();
		$close_output = ob_get_clean();

		$this->assertSame( 1, $after, 'after_render fires once on close bracket' );
		$this->assertStringContainsString( '</div>', $close_output );
	}

	public function test_render_methods_are_noop_when_predicate_false(): void {
		// Flag-OFF, on a context where is_shop() would otherwise be true.
		$GLOBALS['fr_page_type'] = 'shop';

		$before = 0;
		$after  = 0;
		add_action(
			'freeman_core/infinite_scroll/before_render',
			static function () use ( &$before ) {
				$before++;
			}
		);
		add_action(
			'freeman_core/infinite_scroll/after_render',
			static function () use ( &$after ) {
				$after++;
			}
		);

		$module = new Module();

		ob_start();
		$module->render_grid_wrapper_open();
		$module->render_grid_wrapper_close();
		$output = ob_get_clean();

		$this->assertSame( '', $output, 'no output under flag-OFF' );
		$this->assertSame( 0, $before, 'before_render does not fire under flag-OFF' );
		$this->assertSame( 0, $after, 'after_render does not fire under flag-OFF' );
	}
}
