<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Elementor base classes — same shim CategorySliderHooksTest uses, repeated
// here so this file can run in isolation. The fr_test_settings property
// hook on Widget_Base lets tests inject settings without subclassing the
// final Widget class.
if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
	eval( 'namespace Elementor; class Widget_Base { public $fr_test_settings = array(); public function __construct( $data = array(), $args = null ) {} public function get_settings_for_display() { return $this->fr_test_settings; } }' );
}
if ( ! class_exists( '\\Elementor\\Controls_Manager' ) ) {
	eval( 'namespace Elementor; class Controls_Manager { const TAB_CONTENT = "content"; const TAB_STYLE = "style"; const TEXT = "text"; const NUMBER = "number"; const SELECT = "select"; const SWITCHER = "switcher"; const COLOR = "color"; const SLIDER = "slider"; const SELECT2 = "select2"; const CHOOSE = "choose"; const HEADING = "heading"; }' );
}
if ( ! class_exists( '\\Elementor\\Group_Control_Typography' ) ) {
	eval( 'namespace Elementor; class Group_Control_Typography { public static function get_type() { return "typography"; } }' );
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

use Freeman\Core\Modules\ProductSlider\Widget;

/**
 * @covers \Freeman\Core\Modules\ProductSlider\Widget
 */
final class ProductSliderHooksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                  = array();
		$GLOBALS['fr_hooks']                 = array();
		$GLOBALS['fr_wc_get_products_args']  = null;
		$GLOBALS['fr_wc_get_products_return'] = array();
	}

	public function test_query_args_filter_receives_args_and_settings(): void {
		$captured = array();
		add_filter(
			'freeman_core/product_slider/query_args',
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
		$m      = $ref->getMethod( 'fetch_products' );
		$m->setAccessible( true );
		$m->invoke( $widget, array(
			'limit'   => 10,
			'orderby' => 'date',
			'order'   => 'DESC',
			'source'  => 'all',
		) );

		$this->assertSame( 'publish', $captured['args']['status'] );
		$this->assertSame( 'date', $captured['args']['orderby'] );
		$this->assertSame( 10, $captured['args']['limit'] );
		$this->assertSame( 'all', $captured['settings']['source'] );
	}

	public function test_query_args_filter_can_mutate_args_seen_by_wc_get_products(): void {
		add_filter(
			'freeman_core/product_slider/query_args',
			static function ( $args ) {
				$args['limit'] = 99;
				return $args;
			}
		);

		$widget = new Widget();
		$ref    = new \ReflectionClass( $widget );
		$m      = $ref->getMethod( 'fetch_products' );
		$m->setAccessible( true );
		$m->invoke( $widget, array(
			'limit'   => 5,
			'orderby' => 'date',
			'order'   => 'DESC',
			'source'  => 'all',
		) );

		$this->assertSame( 99, $GLOBALS['fr_wc_get_products_args']['limit'] );
	}
}
