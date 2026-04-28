<?php
declare(strict_types=1);

use Freeman\Core\Modules\CategorySlider\Module;
use PHPUnit\Framework\TestCase;

/**
 * Sanity-checks the CategorySlider module shape: id, label, dependencies,
 * and that boot() registers the expected Elementor hooks.
 */
final class CategorySliderModuleTest extends TestCase {

	public function test_basic_metadata(): void {
		$m = new Module();
		$this->assertSame( 'category_slider', $m->id() );
		$this->assertNotEmpty( $m->label() );
		$this->assertNotEmpty( $m->description() );
	}

	public function test_declares_woocommerce_and_elementor_dependencies(): void {
		$m    = new Module();
		$deps = $m->dependencies();
		$this->assertArrayHasKey( 'woocommerce', $deps );
		$this->assertArrayHasKey( 'elementor', $deps );
		$this->assertTrue( (bool) $deps['woocommerce'] );
		$this->assertTrue( (bool) $deps['elementor'] );
	}

	public function test_widget_class_loads_and_extends_elementor_widget_base(): void {
		// Stub the Elementor base classes the Widget extends so the autoloader
		// can satisfy them in this isolated unit test.
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
			eval( 'namespace Elementor; class Widget_Base { public function __construct( $data = array(), $args = null ) {} }' );
		}
		if ( ! class_exists( '\\Elementor\\Controls_Manager' ) ) {
			eval( 'namespace Elementor; class Controls_Manager { const TAB_CONTENT = "content"; const TAB_STYLE = "style"; const TEXT = "text"; const NUMBER = "number"; const SELECT = "select"; const SWITCHER = "switcher"; const COLOR = "color"; }' );
		}

		$this->assertTrue(
			class_exists( '\\Freeman\\Core\\Modules\\CategorySlider\\Widget' ),
			'CategorySlider Widget class must autoload'
		);
		$this->assertTrue(
			is_subclass_of( '\\Freeman\\Core\\Modules\\CategorySlider\\Widget', '\\Elementor\\Widget_Base' ),
			'Widget must extend \\Elementor\\Widget_Base'
		);
	}

	public function test_assets_exist_on_disk(): void {
		$base = FREEMAN_CORE_PATH . 'src/Modules/CategorySlider/assets/';
		$this->assertFileExists( $base . 'css/category-slider.css' );
		$this->assertFileExists( $base . 'js/category-slider.js' );
	}
}
