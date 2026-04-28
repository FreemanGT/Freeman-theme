<?php
declare(strict_types=1);

require_once __DIR__ . '/SnapshotTestCase.php';
require_once __DIR__ . '/Scrubber.php';

use Freeman\Tests\Snapshots\SnapshotTestCase;
use PHPUnit\Framework\TestCase;

// Elementor + WP stubs — match the shapes used in CategorySliderHooksTest. The
// snapshot test exists to guarantee that rendered HTML is byte-identical when
// no `freeman_core/category_slider/render_card` listener is attached, so any
// future drift in the inline card markup surfaces as a snapshot failure.
if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	eval( 'namespace Elementor; class Widget_Base { public $fr_test_settings = array(); public function __construct( $data = array(), $args = null ) {} public function get_settings_for_display() { return $this->fr_test_settings; } }' );
}
if ( ! class_exists( '\\Elementor\\Controls_Manager' ) ) {
	eval( 'namespace Elementor; class Controls_Manager { const TAB_CONTENT = "content"; const TAB_STYLE = "style"; const TEXT = "text"; const NUMBER = "number"; const SELECT = "select"; const SWITCHER = "switcher"; const COLOR = "color"; const SLIDER = "slider"; const SELECT2 = "select2"; const CHOOSE = "choose"; const HEADING = "heading"; }' );
}
if ( ! class_exists( '\\Elementor\\Group_Control_Typography' ) ) {
	eval( 'namespace Elementor; class Group_Control_Typography { public static function get_type() { return "typography"; } }' );
}
if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		return $GLOBALS['fr_get_terms_return'] ?? array();
	}
}
if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term ) {
		return is_object( $term ) ? 'https://example.test/category/' . $term->slug : '#';
	}
}
if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $key, $single = false ) { return ''; }
}
if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
	function wp_get_attachment_image_src( $id, $size ) { return false; }
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $tax ) { return true; }
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) { return false; }
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d ); }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $count, $domain = 'default' ) {
		return 1 === (int) $count ? $single : $plural;
	}
}

use Freeman\Core\Modules\CategorySlider\Widget;

final class CategorySliderSnapshotTest extends TestCase {
	use SnapshotTestCase;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_render_with_no_listeners_matches_golden(): void {
		$GLOBALS['fr_get_terms_return'] = array(
			(object) array( 'term_id' => 1, 'name' => 'Shirts', 'slug' => 'shirts', 'count' => 3 ),
			(object) array( 'term_id' => 2, 'name' => 'Pants',  'slug' => 'pants',  'count' => 7 ),
		);

		$widget                   = new Widget();
		$widget->fr_test_settings = $this->fixed_settings();

		ob_start();
		$ref = new \ReflectionClass( $widget );
		$m   = $ref->getMethod( 'render' );
		$m->setAccessible( true );
		$m->invoke( $widget );
		$html = (string) ob_get_clean();

		$this->assertSnapshotMatches( 'category_slider_two_terms.html', $html );
	}

	private function fixed_settings(): array {
		return array(
			'shape'           => 'soft',
			'show_count'      => 'hover',
			'snap'            => 'none',
			'show_arrows'     => 'no',
			'show_progress'   => 'no',
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
		);
	}
}
