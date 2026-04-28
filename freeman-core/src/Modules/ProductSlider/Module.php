<?php
/**
 * Product Slider module.
 *
 * Registers an Elementor widget that renders WooCommerce products as either
 * an editorial horizontal slider — drag-scroll with momentum, optional
 * card/page snap, progress bar — or a static grid, controlled per-instance
 * by the "Display as slider" toggle.
 *
 * Reuses the Category Slider's CSS + JS runtime: both widgets emit the same
 * `.cs[data-cs-snap]` skeleton, so a single drag/progress engine drives
 * both. Each slider item is a *standard* WooCommerce shop-loop entry
 * rendered via `wc_get_template_part( 'content', 'product' )`, so plugins
 * that target the default product grid (sale flash, wishlist, quick-view,
 * image-swap, ratings, etc.) light up the slider with no extra wiring.
 * Layered product-specific styles (sizing of the standard `.product` /
 * `.woocommerce-loop-product__title` / `.price` / `.button` / `.onsale`
 * inside the slider scope, plus `.cs-grid-mode`) live in this module's
 * own stylesheet.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ProductSlider;

use Freeman\Core\Core\Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	/**
	 * @return string
	 */
	public function id() {
		return 'product_slider';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Product Slider', 'freeman-core' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Editorial Elementor widget for WooCommerce products — drag-scroll slider or static grid, with price, sale badge, and add-to-cart.', 'freeman-core' );
	}

	/**
	 * @return array
	 */
	public function dependencies() {
		return array(
			'woocommerce' => true,
			'elementor'   => true,
		);
	}

	/**
	 * @return array
	 */
	public function settings_schema() {
		return array();
	}

	/**
	 * Boot — register the widget with Elementor and enqueue assets only when
	 * the widget is present on a page (handled by Elementor's
	 * `elementor/frontend/before_enqueue_scripts` hook + the widget's own
	 * get_script_depends()).
	 */
	public function boot() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_scripts' ) );
		// Editor preview also needs the assets.
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue_editor_style' ) );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public function register_widget( $widgets_manager ) {
		$widgets_manager->register( new Widget( array(), array( 'fc_module' => $this ) ) );
	}

	/**
	 * Register front-end stylesheets:
	 *   1. Shared `.cs-*` skeleton (same handle the Category Slider uses) —
	 *      registered defensively so this module works even if the Category
	 *      Slider module is disabled.
	 *   2. Product-specific overlay (`freeman-product-slider`) — price, cart
	 *      button, sale badge, grid-mode container.
	 */
	public function register_styles() {
		$slider_fs  = FREEMAN_CORE_PATH . 'src/Modules/CategorySlider/assets/';
		$slider_url = FREEMAN_CORE_URL . 'src/Modules/CategorySlider/assets/';

		if ( ! wp_style_is( 'freeman-core-category-slider', 'registered' ) ) {
			wp_register_style(
				'freeman-core-category-slider',
				Module_Base::pick_min_url( $slider_fs, $slider_url, 'css/category-slider.css' ),
				array(),
				FREEMAN_CORE_VERSION
			);
			// Deprecated handle alias — see CategorySlider/Module.php.
			if ( ! wp_style_is( 'freeman-category-slider', 'registered' ) ) {
				wp_register_style(
					'freeman-category-slider',
					false,
					array( 'freeman-core-category-slider' ),
					FREEMAN_CORE_VERSION
				);
			}
		}

		wp_register_style(
			'freeman-core-product-slider',
			$this->asset_min_url( 'css/product-slider.css' ),
			array( 'freeman-core-category-slider' ),
			FREEMAN_CORE_VERSION
		);
		// Deprecated handle alias — kept for one release cycle (1.9.x).
		// Removed in 2.0.0.
		if ( ! wp_style_is( 'freeman-product-slider', 'registered' ) ) {
			wp_register_style(
				'freeman-product-slider',
				false,
				array( 'freeman-core-product-slider' ),
				FREEMAN_CORE_VERSION
			);
		}
	}

	/**
	 * Register the shared slider runtime under its canonical handle. Points
	 * at the Category Slider module's JS file — both modules ship in the
	 * same plugin so the relative path is stable. Idempotent — see
	 * register_styles().
	 */
	public function register_scripts() {
		if ( wp_script_is( 'freeman-core-category-slider', 'registered' ) ) {
			return;
		}
		$slider_fs  = FREEMAN_CORE_PATH . 'src/Modules/CategorySlider/assets/';
		$slider_url = FREEMAN_CORE_URL . 'src/Modules/CategorySlider/assets/';
		wp_register_script(
			'freeman-core-category-slider',
			Module_Base::pick_min_url( $slider_fs, $slider_url, 'js/category-slider.js' ),
			array(),
			FREEMAN_CORE_VERSION,
			true
		);
		// Deprecated handle alias.
		if ( ! wp_script_is( 'freeman-category-slider', 'registered' ) ) {
			wp_register_script(
				'freeman-category-slider',
				false,
				array( 'freeman-core-category-slider' ),
				FREEMAN_CORE_VERSION,
				true
			);
		}
	}

	/**
	 * Editor needs both stylesheets so the preview renders correctly.
	 */
	public function enqueue_editor_style() {
		$slider_fs  = FREEMAN_CORE_PATH . 'src/Modules/CategorySlider/assets/';
		$slider_url = FREEMAN_CORE_URL . 'src/Modules/CategorySlider/assets/';

		wp_enqueue_style(
			'freeman-core-category-slider',
			Module_Base::pick_min_url( $slider_fs, $slider_url, 'css/category-slider.css' ),
			array(),
			FREEMAN_CORE_VERSION
		);
		wp_enqueue_style(
			'freeman-core-product-slider',
			$this->asset_min_url( 'css/product-slider.css' ),
			array( 'freeman-core-category-slider' ),
			FREEMAN_CORE_VERSION
		);
	}
}
