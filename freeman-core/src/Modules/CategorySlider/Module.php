<?php
/**
 * Category Slider module.
 *
 * Registers an Elementor widget that renders WooCommerce product categories as
 * an editorial horizontal slider — drag-scroll with momentum, optional
 * card/page snap, hover ring, progress bar. Ported pixel-for-pixel from the
 * Claude Design "Category Slider" handoff bundle.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\CategorySlider;

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
		return 'category_slider';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Category Slider', 'freeman-core' );
	}

	/**
	 * @return string
	 */
	public function description() {
		return __( 'Editorial Elementor widget for WooCommerce product categories — drag-scroll slider with momentum, hover ring, progress bar.', 'freeman-core' );
	}

	/**
	 * Needs both Woo (for product_cat terms + thumbnails) and Elementor (for
	 * the widget host).
	 *
	 * @return array
	 */
	public function dependencies() {
		return array(
			'woocommerce' => true,
			'elementor'   => true,
		);
	}

	/**
	 * No global settings — every knob lives on the widget itself so it can be
	 * tuned per-instance in the Elementor editor.
	 *
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
	 * Register front-end stylesheet — enqueued on demand via the widget's
	 * get_style_depends(). Idempotent: the Product Slider module reuses the
	 * same handle, so whichever module runs first wins.
	 */
	public function register_styles() {
		if ( wp_style_is( 'freeman-core-category-slider', 'registered' ) ) {
			return;
		}
		wp_register_style(
			'freeman-core-category-slider',
			$this->asset_min_url( 'css/category-slider.css' ),
			array(),
			FREEMAN_CORE_VERSION
		);
		// Deprecated handle alias — kept for one release cycle (1.9.x).
		// Removed in 2.0.0. Resolves the CSS through the canonical handle.
		if ( ! wp_style_is( 'freeman-category-slider', 'registered' ) ) {
			wp_register_style(
				'freeman-category-slider',
				false,
				array( 'freeman-core-category-slider' ),
				FREEMAN_CORE_VERSION
			);
		}
	}

	/**
	 * Register front-end script. Idempotent — see register_styles().
	 */
	public function register_scripts() {
		if ( wp_script_is( 'freeman-core-category-slider', 'registered' ) ) {
			return;
		}
		wp_register_script(
			'freeman-core-category-slider',
			$this->asset_min_url( 'js/category-slider.js' ),
			array(),
			FREEMAN_CORE_VERSION,
			true
		);
		// Deprecated handle alias — see register_styles().
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
	 * Editor needs the stylesheet so the preview renders correctly.
	 */
	public function enqueue_editor_style() {
		wp_enqueue_style(
			'freeman-core-category-slider',
			$this->asset_min_url( 'css/category-slider.css' ),
			array(),
			FREEMAN_CORE_VERSION
		);
	}
}
