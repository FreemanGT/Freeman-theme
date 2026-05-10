<?php
/**
 * Infinite Scroll module.
 *
 * Enqueues the front-end JS/CSS that drives infinite scroll for Woo product
 * grids (stock Woo, Elementor widgets, block-based grids).
 *
 * Ported from bookomers-infinite-scroll v1.0.5.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\InfiniteScroll;

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Core\Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'infinite_scroll';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Infinite Scroll', 'freeman-core' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Infinite scroll for WooCommerce product grids (shop, Elementor widgets, block grids) with skeleton placeholders and preserved /page/N/ URLs.', 'freeman-core' );
	}

	/**
	 * Settings schema.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array(
			'skeleton_count' => array(
				'label'       => __( 'Skeleton cards', 'freeman-core' ),
				'type'        => 'number',
				'description' => __( 'How many placeholder cards to show while loading.', 'freeman-core' ),
				'default'     => 6,
			),
			'max_pages'      => array(
				'label'       => __( 'Max pages', 'freeman-core' ),
				'type'        => 'number',
				'description' => __( 'Absolute safety limit — no more than this many pages will ever auto-load.', 'freeman-core' ),
				'default'     => 50,
			),
			'end_message'    => array(
				'label'       => __( 'End-of-list message', 'freeman-core' ),
				'type'        => 'text',
				'description' => __( 'Shown once there are no more products.', 'freeman-core' ),
				'default'     => __( 'You have reached the end.', 'freeman-core' ),
			),
			'trigger_mode'      => array(
				'label'       => __( 'Trigger mode', 'freeman-core' ),
				'type'        => 'select',
				'choices'     => array(
					'auto'   => __( 'Auto — load on scroll / observer (current behavior)', 'freeman-core' ),
					'button' => __( 'Button — render a "Load more" button (Wave 3.1b)', 'freeman-core' ),
					'hybrid' => __( 'Hybrid — auto for first N pages, then button (Wave 3.1b)', 'freeman-core' ),
				),
				'default'     => 'auto',
				'description' => __( 'Which mechanism advances pages. Only takes effect when the Trigger Modes feature flag is on. Button and Hybrid render the button server-side, which ships in Wave 3.1b — Auto is fully functional in 3.1a.', 'freeman-core' ),
			),
			'history_mode'      => array(
				'label'       => __( 'URL update on page advance', 'freeman-core' ),
				'type'        => 'select',
				'choices'     => array(
					'pushState'    => __( 'pushState — update URL and create back-button entries (current behavior)', 'freeman-core' ),
					'replaceState' => __( 'replaceState — update URL without back-button entries', 'freeman-core' ),
					'disabled'     => __( 'Disabled — leave URL unchanged on page advance', 'freeman-core' ),
				),
				'default'     => 'pushState',
				'description' => __( 'How the browser URL is updated when an additional page loads. Default preserves current pushState behavior. Only takes effect when the Trigger Modes feature flag is on.', 'freeman-core' ),
			),
			'hybrid_threshold'  => array(
				'label'       => __( 'Hybrid threshold (pages)', 'freeman-core' ),
				'type'        => 'number',
				'default'     => 2,
				'description' => __( 'In Hybrid mode, the number of pages to auto-load before switching to the Load-more button. Ignored unless trigger mode is set to Hybrid.', 'freeman-core' ),
			),
		);
	}

	/**
	 * Boot hooks.
	 */
	public function boot() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue assets on the front end (skipping admin/feed).
	 */
	public function enqueue() {
		if ( is_admin() || is_feed() ) {
			return;
		}

		$handle            = 'freeman-core-infinite-scroll';
		$deprecated_handle = 'freeman-infinite-scroll';

		wp_enqueue_style(
			$handle,
			$this->asset_min_url( 'css/infinite-scroll.css' ),
			array(),
			FREEMAN_CORE_VERSION
		);

		wp_enqueue_script(
			$handle,
			$this->asset_min_url( 'js/infinite-scroll.js' ),
			array(),
			FREEMAN_CORE_VERSION,
			true
		);

		// Deprecated handle aliases — kept for one release cycle (1.9.x).
		// Removed in 2.0.0. Resolve via dependency on the canonical handle.
		if ( ! wp_style_is( $deprecated_handle, 'registered' ) ) {
			wp_register_style( $deprecated_handle, false, array( $handle ), FREEMAN_CORE_VERSION );
		}
		if ( ! wp_script_is( $deprecated_handle, 'registered' ) ) {
			wp_register_script( $deprecated_handle, false, array( $handle ), FREEMAN_CORE_VERSION, true );
		}

		wp_localize_script(
			$handle,
			'FreemanInfiniteScroll',
			$this->localized_payload()
		);
	}

	/**
	 * Build the FreemanInfiniteScroll JS payload.
	 *
	 * Extracted from `enqueue()` so PHPUnit can assert payload shape under
	 * different flag / setting combinations (see InfiniteScrollSettingsTest).
	 *
	 * @return array<string,mixed>
	 */
	public function localized_payload() {
		return array(
			'skeletonCount'       => (int) $this->get_option( 'skeleton_count', 6 ),
			'maxPages'            => (int) $this->get_option( 'max_pages', 50 ),
			'endMessage'          => (string) $this->get_option( 'end_message', __( 'You have reached the end.', 'freeman-core' ) ),
			'errorMessage'        => __( 'Could not load more.', 'freeman-core' ),
			'loadMoreLabel'       => __( 'Load more', 'freeman-core' ),
			/* translators: %d = number of products just loaded. Used for screen-reader aria-live announcement. */
			'announceTemplate'    => __( 'Loaded %d more products.', 'freeman-core' ),
			'triggerModesEnabled' => Feature_Flags::is_enabled( 'infinite_scroll', 'trigger_modes' ),
			'triggerMode'         => (string) $this->get_option( 'trigger_mode', 'auto' ),
			'historyMode'         => (string) $this->get_option( 'history_mode', 'pushState' ),
			'hybridThreshold'     => (int) $this->get_option( 'hybrid_threshold', 2 ),
		);
	}
}
