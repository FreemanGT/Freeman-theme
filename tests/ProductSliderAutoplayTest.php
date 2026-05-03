<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Wave 3.2b — Product Slider autoplay / loop / indicator. Mirrors
// CategorySliderAutoplayTest's coverage on the ProductSlider's render
// path under the shared `freeman_core_sliders_advanced_controls_enabled`
// flag, with two additional grid-mode suppression cases (no analog on
// CategorySlider, which is slider-only). Stub shapes match the ones
// already used by ProductSliderClampAttrTest so this file co-exists
// with it; each `eval` / `function` is `*_exists`-guarded.

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	eval( 'namespace Elementor; class Widget_Base { public $fr_test_settings = array(); public function __construct( $data = array(), $args = null ) {} public function get_settings_for_display() { return $this->fr_test_settings; } }' );
}
if ( ! class_exists( '\\Elementor\\Controls_Manager' ) ) {
	eval( 'namespace Elementor; class Controls_Manager { const TAB_CONTENT = "content"; const TAB_STYLE = "style"; const TEXT = "text"; const NUMBER = "number"; const SELECT = "select"; const SWITCHER = "switcher"; const COLOR = "color"; const SLIDER = "slider"; const SELECT2 = "select2"; const CHOOSE = "choose"; const HEADING = "heading"; }' );
}
if ( ! class_exists( '\\Elementor\\Group_Control_Typography' ) ) {
	eval( 'namespace Elementor; class Group_Control_Typography { public static function get_type() { return "typography"; } }' );
}
if ( ! class_exists( '\\WC_Product' ) ) {
	eval( 'class WC_Product { private $id; public function __construct( $id ) { $this->id = $id; } public function get_id() { return $this->id; } }' );
}

if ( ! function_exists( 'wc_get_products' ) ) {
	function wc_get_products( $args = array() ) {
		$GLOBALS['fr_wc_get_products_args'] = $args;
		return $GLOBALS['fr_wc_get_products_return'] ?? array();
	}
}
if ( ! function_exists( 'is_post_type_archive' ) ) {
	function is_post_type_archive( $type ) { return false; }
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $type ) { return false; }
}
if ( ! function_exists( 'is_tax' ) ) {
	function is_tax( $tax ) { return false; }
}
if ( ! function_exists( 'wc_get_template_part' ) ) {
	function wc_get_template_part( $slug, $name = '' ) { /* no-op */ }
}
if ( ! function_exists( 'setup_postdata' ) ) {
	function setup_postdata( $post ) {}
}
if ( ! function_exists( 'wp_reset_postdata' ) ) {
	function wp_reset_postdata() {}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) { return (object) array( 'ID' => is_object( $id ) ? $id->ID : $id ); }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) { echo htmlspecialchars( (string) $text, ENT_QUOTES ); }
}

use Freeman\Core\Modules\ProductSlider\Widget;

final class ProductSliderAutoplayTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                   = array();
		$GLOBALS['fr_hooks']                  = array();
		$GLOBALS['fr_wc_get_products_return'] = array( new \WC_Product( 42 ) );
	}

	private function render_widget( array $settings ): string {
		$widget                   = new Widget();
		$widget->fr_test_settings = $this->base_settings( $settings );
		$ref = new \ReflectionClass( $widget );
		$m   = $ref->getMethod( 'render' );
		$m->setAccessible( true );
		ob_start();
		$m->invoke( $widget );
		return (string) ob_get_clean();
	}

	private function base_settings( array $overrides ): array {
		return array_merge(
			array(
				'display_mode'    => 'slider',
				'shape'           => 'soft',
				'snap'            => 'none',
				'show_arrows'     => 'no',
				'show_progress'   => 'yes',
				'show_cart'       => 'yes',
				'show_sale_badge' => 'yes',
				'mouse_drag'      => '',
				'direction'       => 'ltr',
				'per_view'        => 4,
				'per_view_tablet' => 3,
				'per_view_mobile' => 1.4,
				'gap'             => 20,
				'card_height'     => 320,
				'eyebrow'         => '',
				'headline'        => '',
				'headline_mute'   => '',
				'source'          => 'all',
				'limit'           => 10,
				'orderby'         => 'date',
				'order'           => 'DESC',
			),
			$overrides
		);
	}

	private function enable_flag(): void {
		$GLOBALS['fr_opts']['freeman_core_sliders_advanced_controls_enabled'] = '1';
	}

	public function test_flag_off_omits_advanced_data_attributes_on_root(): void {
		$html = $this->render_widget( array( 'indicator' => 'dots', 'autoplay' => 'yes', 'loop' => 'yes' ) );
		$this->assertStringNotContainsString( 'data-cs-indicator', $html, 'flag-off must not emit data-cs-indicator regardless of saved settings' );
		$this->assertStringNotContainsString( 'data-cs-autoplay',  $html );
		$this->assertStringNotContainsString( 'data-cs-loop',      $html );
		$this->assertStringNotContainsString( 'cs-dots',           $html, 'flag-off must not emit dots markup even when saved indicator=dots' );
	}

	public function test_flag_off_legacy_show_progress_drives_indicator_state(): void {
		$on  = $this->render_widget( array( 'show_progress' => 'yes' ) );
		$off = $this->render_widget( array( 'show_progress' => '' ) );
		$this->assertStringContainsString( 'cs-progress', $on,  'flag-off + show_progress=yes emits legacy progress markup' );
		$this->assertStringNotContainsString( 'cs-progress', $off, 'flag-off + show_progress=no suppresses legacy progress markup' );
		$this->assertStringNotContainsString( 'cs-dots', $on );
		$this->assertStringNotContainsString( 'cs-dots', $off );
	}

	public function test_flag_on_default_indicator_emits_progress_markup(): void {
		$this->enable_flag();
		$html = $this->render_widget( array( 'indicator' => 'progress' ) );
		$this->assertStringContainsString( 'data-cs-indicator="progress"', $html );
		$this->assertStringContainsString( 'cs-progress', $html );
		$this->assertStringNotContainsString( 'cs-dots', $html );
	}

	public function test_flag_on_indicator_dots_emits_dots_and_suppresses_progress(): void {
		$this->enable_flag();
		$html = $this->render_widget( array( 'indicator' => 'dots' ) );
		$this->assertStringContainsString( 'data-cs-indicator="dots"', $html );
		$this->assertStringContainsString( 'cs-dots',     $html );
		$this->assertStringContainsString( 'data-cs-dot=', $html );
		$this->assertStringNotContainsString( 'cs-progress', $html, 'dots mode must not emit progress markup' );
	}

	public function test_flag_on_indicator_none_emits_neither(): void {
		$this->enable_flag();
		$html = $this->render_widget( array( 'indicator' => 'none' ) );
		$this->assertStringContainsString( 'data-cs-indicator="none"', $html );
		$this->assertStringNotContainsString( 'cs-progress', $html );
		$this->assertStringNotContainsString( 'cs-dots',     $html );
	}

	public function test_flag_on_back_compat_shim_falls_back_to_show_progress_when_indicator_unset(): void {
		$this->enable_flag();
		// `indicator` deliberately omitted to simulate a pre-3.2b-saved widget.
		$with_progress = $this->render_widget( array( 'show_progress' => 'yes' ) );
		$without       = $this->render_widget( array( 'show_progress' => '' ) );
		$this->assertStringContainsString( 'data-cs-indicator="progress"', $with_progress );
		$this->assertStringContainsString( 'cs-progress', $with_progress );
		$this->assertStringContainsString( 'data-cs-indicator="none"', $without );
		$this->assertStringNotContainsString( 'cs-progress', $without );
		$this->assertStringNotContainsString( 'cs-dots',     $without );
	}

	public function test_flag_on_autoplay_emits_data_attrs_with_clamped_delay(): void {
		$this->enable_flag();
		$over   = $this->render_widget( array( 'autoplay' => 'yes', 'autoplay_delay' => array( 'unit' => 'ms', 'size' => 99999 ) ) );
		$under  = $this->render_widget( array( 'autoplay' => 'yes', 'autoplay_delay' => array( 'unit' => 'ms', 'size' => 200 ) ) );
		$normal = $this->render_widget( array( 'autoplay' => 'yes', 'autoplay_delay' => array( 'unit' => 'ms', 'size' => 5000 ) ) );
		$off    = $this->render_widget( array( 'autoplay' => '' ) );
		$this->assertStringContainsString( 'data-cs-autoplay="1"',            $over );
		$this->assertStringContainsString( 'data-cs-autoplay-delay="15000"',  $over,    'autoplay_delay must clamp to max 15000' );
		$this->assertStringContainsString( 'data-cs-autoplay-delay="1000"',   $under,   'autoplay_delay must clamp to min 1000' );
		$this->assertStringContainsString( 'data-cs-autoplay-delay="5000"',   $normal );
		$this->assertStringNotContainsString( 'data-cs-autoplay',             $off,     'autoplay=off must not emit data-cs-autoplay' );
	}

	public function test_flag_on_loop_only_emitted_when_autoplay_yes(): void {
		$this->enable_flag();
		$loop_without_autoplay = $this->render_widget( array( 'autoplay' => '',    'loop' => 'yes' ) );
		$loop_with_autoplay    = $this->render_widget( array( 'autoplay' => 'yes', 'loop' => 'yes' ) );
		$this->assertStringNotContainsString( 'data-cs-loop', $loop_without_autoplay, 'loop must be inert without autoplay' );
		$this->assertStringContainsString(    'data-cs-loop="1"', $loop_with_autoplay );
	}

	// Net-new for Wave 3.2b — ProductSlider's display_mode dimension has no
	// CategorySlider analog. Grid mode must suppress every advanced data
	// attr and indicator markup regardless of saved values, because
	// autoplay / loop / pagination dots are slider chrome with no meaning
	// when products are laid out as a static grid.
	public function test_flag_on_grid_mode_omits_advanced_data_attributes(): void {
		$this->enable_flag();
		$html = $this->render_widget( array(
			'display_mode'   => 'grid',
			'indicator'      => 'dots',
			'autoplay'       => 'yes',
			'autoplay_delay' => array( 'unit' => 'ms', 'size' => 5000 ),
			'loop'           => 'yes',
		) );
		$this->assertStringNotContainsString( 'data-cs-indicator', $html, 'grid mode must not emit data-cs-indicator' );
		$this->assertStringNotContainsString( 'data-cs-autoplay',  $html, 'grid mode must not emit data-cs-autoplay' );
		$this->assertStringNotContainsString( 'data-cs-loop',      $html, 'grid mode must not emit data-cs-loop' );
	}

	public function test_flag_on_grid_mode_omits_indicator_markup(): void {
		$this->enable_flag();
		$dots = $this->render_widget( array( 'display_mode' => 'grid', 'indicator' => 'dots' ) );
		$prog = $this->render_widget( array( 'display_mode' => 'grid', 'indicator' => 'progress' ) );
		$this->assertStringNotContainsString( 'cs-dots',     $dots, 'grid mode must not render dots indicator' );
		$this->assertStringNotContainsString( 'cs-progress', $dots, 'grid mode must not render progress indicator' );
		$this->assertStringNotContainsString( 'cs-progress', $prog, 'grid mode must not render progress indicator even when chosen' );
		$this->assertStringNotContainsString( 'cs-dots',     $prog );
	}
}
