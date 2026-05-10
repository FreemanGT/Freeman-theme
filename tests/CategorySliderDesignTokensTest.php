<?php
declare(strict_types=1);

use Freeman\Core\Modules\CategorySlider\Widget;
use PHPUnit\Framework\TestCase;

/**
 * Wave 4.2 — CategorySlider design tokens (4 colors + 3 arrow values)
 * exposed as Elementor controls. Defaults preserve the pre-4.2 render:
 * colors default to '' (Elementor omits selector → CSS `.cs` block's
 * existing oklch() declaration stays) and arrow tokens default to the
 * exact prior hardcoded values (40px / 50% / 180ms ≡ .18s).
 *
 * Tests drive `register_controls()` against the capable Widget_Base stub
 * declared in tests/bootstrap.php (`$fr_test_controls` capture).
 *
 * @covers \Freeman\Core\Modules\CategorySlider\Widget
 */
final class CategorySliderDesignTokensTest extends TestCase {

	private function register(): Widget {
		$widget = new Widget();
		// register_controls() is protected; call via Closure::bind for test
		// purposes. Same pattern any unit test of a protected Elementor
		// hook would use.
		( function () { $this->register_controls(); } )->call( $widget );
		return $widget;
	}

	public function test_register_controls_adds_4_color_controls(): void {
		$w = $this->register();

		$this->assertArrayHasKey( 'cs_bg_color', $w->fr_test_controls );
		$this->assertArrayHasKey( 'cs_ink_color', $w->fr_test_controls );
		$this->assertArrayHasKey( 'cs_mute_color', $w->fr_test_controls );
		$this->assertArrayHasKey( 'cs_line_color', $w->fr_test_controls );
	}

	public function test_color_controls_emit_correct_css_variable_selectors(): void {
		$w = $this->register();

		$this->assertSame(
			array( '{{WRAPPER}} .cs' => '--cs-bg: {{VALUE}};' ),
			$w->fr_test_controls['cs_bg_color']['selectors']
		);
		$this->assertSame(
			array( '{{WRAPPER}} .cs' => '--cs-ink: {{VALUE}};' ),
			$w->fr_test_controls['cs_ink_color']['selectors']
		);
		$this->assertSame(
			array( '{{WRAPPER}} .cs' => '--cs-mute: {{VALUE}};' ),
			$w->fr_test_controls['cs_mute_color']['selectors']
		);
		$this->assertSame(
			array( '{{WRAPPER}} .cs' => '--cs-line: {{VALUE}};' ),
			$w->fr_test_controls['cs_line_color']['selectors']
		);
	}

	public function test_color_controls_default_to_empty_for_backcompat(): void {
		// Empty default → Elementor omits the selector → CSS `.cs` block's
		// existing oklch() declaration remains as the rendered value.
		// Pinned because non-empty defaults would break the byte-identical
		// pre-4.2 render the Hard Rule #1 additive exemption depends on.
		$w = $this->register();
		$this->assertSame( '', $w->fr_test_controls['cs_bg_color']['default'] );
		$this->assertSame( '', $w->fr_test_controls['cs_ink_color']['default'] );
		$this->assertSame( '', $w->fr_test_controls['cs_mute_color']['default'] );
		$this->assertSame( '', $w->fr_test_controls['cs_line_color']['default'] );
	}

	public function test_register_controls_adds_3_arrow_controls(): void {
		$w = $this->register();

		$this->assertArrayHasKey( 'cs_arrow_size', $w->fr_test_controls );
		$this->assertArrayHasKey( 'cs_arrow_radius', $w->fr_test_controls );
		$this->assertArrayHasKey( 'cs_arrow_duration', $w->fr_test_controls );
	}

	public function test_arrow_size_default_40px(): void {
		$ctrl = $this->register()->fr_test_controls['cs_arrow_size'];

		$this->assertSame( array( 'px' ), $ctrl['size_units'] );
		$this->assertSame( array( 'size' => 40, 'unit' => 'px' ), $ctrl['default'] );
		$this->assertSame(
			array( '{{WRAPPER}} .cs' => '--cs-arrow-size: {{SIZE}}{{UNIT}};' ),
			$ctrl['selectors']
		);
	}

	public function test_arrow_radius_default_50_percent(): void {
		$ctrl = $this->register()->fr_test_controls['cs_arrow_radius'];

		$this->assertSame( array( 'px', '%' ), $ctrl['size_units'] );
		$this->assertSame( array( 'size' => 50, 'unit' => '%' ), $ctrl['default'] );
		$this->assertSame(
			array( '{{WRAPPER}} .cs' => '--cs-arrow-radius: {{SIZE}}{{UNIT}};' ),
			$ctrl['selectors']
		);
	}

	public function test_arrow_duration_default_180ms(): void {
		$ctrl = $this->register()->fr_test_controls['cs_arrow_duration'];

		$this->assertSame( array( 'ms' ), $ctrl['size_units'] );
		$this->assertSame( array( 'size' => 180, 'unit' => 'ms' ), $ctrl['default'] );
		$this->assertSame(
			array( '{{WRAPPER}} .cs' => '--cs-arrow-duration: {{SIZE}}{{UNIT}};' ),
			$ctrl['selectors']
		);
	}

	public function test_css_arrow_size_consumed_with_40px_fallback(): void {
		$css = file_get_contents( FREEMAN_CORE_PATH . 'src/Modules/CategorySlider/assets/css/category-slider.css' );

		// width / height / min-width / min-height each consume --cs-arrow-size
		// with the prior 40px hardcoded value as fallback.
		$this->assertSame(
			4,
			substr_count( $css, 'var(--cs-arrow-size, 40px)' ),
			'.cs-arrow declares 4 size properties — all four must consume --cs-arrow-size with the 40px fallback'
		);
	}

	public function test_css_arrow_radius_consumed_with_50_percent_fallback(): void {
		$css = file_get_contents( FREEMAN_CORE_PATH . 'src/Modules/CategorySlider/assets/css/category-slider.css' );

		$this->assertStringContainsString( 'border-radius: var(--cs-arrow-radius, 50%)', $css );
	}

	public function test_css_arrow_duration_consumed_with_18s_fallback_four_times(): void {
		$css = file_get_contents( FREEMAN_CORE_PATH . 'src/Modules/CategorySlider/assets/css/category-slider.css' );

		// The .cs-arrow transition declares 4 properties (background /
		// border-color / color / opacity) — all four reference
		// --cs-arrow-duration with the prior .18s fallback. Tight count
		// catches accidental refactor (consolidation to `transition: all
		// var(...)` would be a deliberate change worth re-baselining).
		$this->assertSame(
			4,
			substr_count( $css, 'var(--cs-arrow-duration, .18s)' ),
			'.cs-arrow transition has 4 timing slots — all four must reference --cs-arrow-duration with the .18s fallback'
		);
	}
}
