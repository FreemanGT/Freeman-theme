<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Elementor base classes — same shim CategorySliderModuleTest uses, repeated
// here so this file can run in isolation. Defined before the autoloader sees
// the Widget class (which extends \Elementor\Widget_Base).
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
	function get_term_meta( $term_id, $key, $single = false ) {
		return '';
	}
}
if ( ! function_exists( 'wp_get_attachment_image_src' ) ) {
	function wp_get_attachment_image_src( $id, $size ) {
		return false;
	}
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $tax ) {
		return true;
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url ) {
		return $url . '?' . http_build_query( (array) $args );
	}
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

/**
 * @covers \Freeman\Core\Modules\CategorySlider\Widget
 */
final class CategorySliderHooksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']             = array();
		$GLOBALS['fr_hooks']            = array();
		$GLOBALS['fr_get_terms_return'] = array();
	}

	public function test_query_args_filter_receives_args_and_settings(): void {
		$captured = array();
		add_filter(
			'freeman_core/category_slider/query_args',
			static function ( $args, $settings ) use ( &$captured ) {
				$captured = array(
					'args'     => $args,
					'settings' => $settings,
				);
				return $args;
			},
			10,
			2
		);

		$widget = new Widget();
		$ref    = new \ReflectionClass( $widget );
		$m      = $ref->getMethod( 'fetch_terms' );
		$m->setAccessible( true );
		$m->invoke( $widget, array( 'orderby' => 'name', 'order' => 'ASC', 'limit' => array( 'size' => 12 ), 'parent_only' => 'yes' ) );

		$this->assertSame( 'name', $captured['args']['orderby'] );
		$this->assertSame( 'ASC', $captured['args']['order'] );
		$this->assertSame( 'product_cat', $captured['args']['taxonomy'] );
		$this->assertSame( 'yes', $captured['settings']['parent_only'] );
	}

	public function test_query_args_filter_can_mutate_args(): void {
		$seen_in_get_terms = null;
		// Override the get_terms stub for this test by intercepting via a
		// trailing-edge listener that captures what fetch_terms returned.
		add_filter(
			'freeman_core/category_slider/query_args',
			static function ( $args ) {
				$args['number'] = 999; // overwrite the limit
				return $args;
			}
		);

		// Replace the get_terms stub via $GLOBALS so we can capture what was
		// passed in. Since PHP's user-defined function can't be redeclared, we
		// rely on the existing stub returning $GLOBALS['fr_get_terms_return'].
		// To inspect the args, register a second listener that runs after ours.
		$captured = null;
		add_filter(
			'freeman_core/category_slider/query_args',
			static function ( $args ) use ( &$captured ) {
				$captured = $args;
				return $args;
			},
			20
		);

		$widget = new Widget();
		$ref    = new \ReflectionClass( $widget );
		$m      = $ref->getMethod( 'fetch_terms' );
		$m->setAccessible( true );
		$m->invoke( $widget, array( 'orderby' => 'name', 'order' => 'ASC', 'limit' => 12, 'parent_only' => 'yes' ) );

		$this->assertSame( 999, $captured['number'] );
	}

	public function test_render_card_filter_receives_html_and_context(): void {
		$captured = array();
		add_filter(
			'freeman_core/category_slider/render_card',
			static function ( $html, $term, $context ) use ( &$captured ) {
				$captured[] = array(
					'html'    => $html,
					'term'    => $term,
					'context' => $context,
				);
				return $html;
			},
			10,
			3
		);

		$GLOBALS['fr_get_terms_return'] = array(
			(object) array( 'term_id' => 1, 'name' => 'Shirts', 'slug' => 'shirts', 'count' => 3 ),
			(object) array( 'term_id' => 2, 'name' => 'Pants',  'slug' => 'pants',  'count' => 7 ),
		);

		ob_start();
		$this->render_widget();
		ob_end_clean();

		$this->assertCount( 2, $captured, 'render_card filter must fire once per term' );
		$this->assertSame( 'Shirts', $captured[0]['term']->name );
		$this->assertStringContainsString( 'cs-card', $captured[0]['html'] );
		$this->assertSame( 3, $captured[0]['context']['count'] );
		$this->assertArrayHasKey( 'shape', $captured[0]['context'] );
	}

	public function test_render_card_filter_can_replace_markup(): void {
		add_filter(
			'freeman_core/category_slider/render_card',
			static function ( $html, $term ) {
				return '<a class="custom-card" data-slug="' . esc_attr( $term->slug ) . '">x</a>';
			},
			10,
			2
		);

		$GLOBALS['fr_get_terms_return'] = array(
			(object) array( 'term_id' => 1, 'name' => 'Shirts', 'slug' => 'shirts', 'count' => 3 ),
		);

		ob_start();
		$this->render_widget();
		$out = (string) ob_get_clean();

		$this->assertStringContainsString( 'custom-card', $out );
		$this->assertStringContainsString( 'data-slug="shirts"', $out );
	}

	private function render_widget(): void {
		$widget = new Widget();
		$widget->fr_test_settings = array(
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
		$ref = new \ReflectionClass( $widget );
		$m   = $ref->getMethod( 'render' );
		$m->setAccessible( true );
		$m->invoke( $widget );
	}
}
