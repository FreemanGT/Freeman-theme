<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Wave 3.2a — Category Slider autoplay / loop / indicator. Drives render()
// under the `freeman_core_sliders_advanced_controls_enabled` flag and
// asserts the emitted DOM. Mirrors the Elementor + WC stub shape used by
// CategorySliderSnapshotTest so both files can co-exist (each `eval` /
// `function` is `*_exists`-guarded; whichever test loads first wins).

if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	eval( 'namespace Elementor; class Widget_Base { public $fr_test_settings = array(); public function __construct( $data = array(), $args = null ) {} public function get_settings_for_display() { return $this->fr_test_settings; } }' );
}
if ( ! class_exists( '\\Elementor\\Controls_Manager' ) ) {
	eval( 'namespace Elementor; class Controls_Manager { const TAB_CONTENT = "content"; const TAB_STYLE = "style"; const TEXT = "text"; const NUMBER = "number"; const SELECT = "select"; const SWITCHER = "switcher"; const COLOR = "color"; const SLIDER = "slider"; const SELECT2 = "select2"; const CHOOSE = "choose"; const HEADING = "heading"; }' );
}
if ( ! class_exists( '\\Elementor\\Group_Control_Typography' ) ) {
	eval( 'namespace Elementor; class Group_Control_Typography { public static function get_type() { return "typography"; } }' );
}
if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term ) { return is_object( $term ) ? 'https://example.test/category/' . $term->slug : '#'; }
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) { return false; }
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $tax ) { return true; }
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d ); }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $count, $domain = 'default' ) { return 1 === (int) $count ? $single : $plural; }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) { echo htmlspecialchars( (string) $text, ENT_QUOTES ); }
}

use Freeman\Core\Modules\CategorySlider\Widget;

final class CategorySliderAutoplayTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
		$GLOBALS['fr_get_terms_return'] = array(
			(object) array( 'term_id' => 1, 'name' => 'Shirts', 'slug' => 'shirts', 'count' => 3 ),
			(object) array( 'term_id' => 2, 'name' => 'Pants',  'slug' => 'pants',  'count' => 7 ),
		);
	}

	private function render_widget( array $settings ): string {
		$widget = new Widget();
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
				'shape'           => 'soft',
				'show_count'      => 'hover',
				'snap'            => 'none',
				'show_arrows'     => 'no',
				'show_progress'   => 'yes',
				'mouse_drag'      => '',
				'direction'       => 'ltr',
				'per_view'        => 5,
				'per_view_tablet' => 4,
				'per_view_mobile' => 2,
				'gap'             => 20,
				'card_height'     => 280,
				'eyebrow'         => '',
				'headline'        => '',
				'headline_mute'   => '',
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
		// `indicator` deliberately omitted to simulate a pre-3.2a-saved widget.
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
}
