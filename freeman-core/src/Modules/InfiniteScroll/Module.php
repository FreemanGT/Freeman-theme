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
	 * Cached predicate state for the wrapper-render bracket. Set inside
	 * render_grid_wrapper_open(); reused (not re-evaluated) in
	 * render_grid_wrapper_close() so a listener can't return true on the
	 * opening bracket and false on the closing bracket and produce an
	 * orphan <div>.
	 *
	 * @var bool
	 */
	private $wrapper_active = false;

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
					'button' => __( 'Button — halt auto-loading (button UI deferred — see roadmap)', 'freeman-core' ),
					'hybrid' => __( 'Hybrid — auto for first N pages, then halt (button UI deferred — see roadmap)', 'freeman-core' ),
				),
				'default'     => 'auto',
				'description' => __( 'Which mechanism advances pages. Only takes effect when the Trigger Modes feature flag is on. Auto is fully functional. Button halts auto-loading after the first page (functionally max_pages=1). Hybrid auto-loads up to the threshold then halts. The user-facing "Load more" button UI is deferred to a future wave.', 'freeman-core' ),
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
			'container_selector' => array(
				'label'       => __( 'Container selector override', 'freeman-core' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'CSS selector(s) for the product grid container. Empty = use built-in selector list. Listeners can further override via the freeman_core/infinite_scroll/selector filter.', 'freeman-core' ),
			),
			'shimmer_base_color'      => array(
				'label'       => __( 'Skeleton shimmer — base color', 'freeman-core' ),
				'type'        => 'color',
				'default'     => '#eceff3',
				'description' => __( 'Base color of the skeleton-card shimmer gradient.', 'freeman-core' ),
			),
			'shimmer_highlight_color' => array(
				'label'       => __( 'Skeleton shimmer — highlight color', 'freeman-core' ),
				'type'        => 'color',
				'default'     => '#f6f8fb',
				'description' => __( 'Highlight color that sweeps across the shimmer gradient.', 'freeman-core' ),
			),
			'shimmer_duration_ms'     => array(
				'label'       => __( 'Skeleton shimmer — duration (ms)', 'freeman-core' ),
				'type'        => 'number',
				'default'     => 1400,
				'description' => __( 'How long one shimmer sweep takes, in milliseconds.', 'freeman-core' ),
			),
			'fade_duration_ms'        => array(
				'label'       => __( 'New product fade-in — duration (ms)', 'freeman-core' ),
				'type'        => 'number',
				'default'     => 550,
				'description' => __( 'Duration of the appear-animation on newly-loaded product cards.', 'freeman-core' ),
			),
			'fade_transform_px'       => array(
				'label'       => __( 'New product fade-in — translate distance (px)', 'freeman-core' ),
				'type'        => 'number',
				'default'     => 18,
				'description' => __( 'Vertical offset (px) that newly-loaded product cards rise from during fade-in.', 'freeman-core' ),
			),
		);
	}

	/**
	 * Boot hooks.
	 */
	public function boot() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		if ( Feature_Flags::is_enabled( 'infinite_scroll', 'trigger_modes' ) ) {
			add_action( 'woocommerce_before_shop_loop', array( $this, 'render_grid_wrapper_open' ), 5 );
			add_action( 'woocommerce_after_shop_loop', array( $this, 'render_grid_wrapper_close' ), 999 );
		}
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

		wp_add_inline_style( $handle, $this->inline_token_css() );

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
		$flag_on = Feature_Flags::is_enabled( 'infinite_scroll', 'trigger_modes' );
		return array(
			'skeletonCount'       => (int) $this->get_option( 'skeleton_count', 6 ),
			'maxPages'            => (int) $this->get_option( 'max_pages', 50 ),
			'endMessage'          => (string) $this->get_option( 'end_message', __( 'You have reached the end.', 'freeman-core' ) ),
			'errorMessage'        => __( 'Could not load more.', 'freeman-core' ),
			'loadMoreLabel'       => __( 'Load more', 'freeman-core' ),
			/* translators: %d = number of products just loaded. Used for screen-reader aria-live announcement. */
			'announceTemplate'    => __( 'Loaded %d more products.', 'freeman-core' ),
			'triggerModesEnabled' => $flag_on,
			'triggerMode'         => (string) $this->get_option( 'trigger_mode', 'auto' ),
			'historyMode'         => (string) $this->get_option( 'history_mode', 'pushState' ),
			'hybridThreshold'     => (int) $this->get_option( 'hybrid_threshold', 2 ),
			// Wave 3.1b: containerSelector follows Mechanism A (always
			// emitted) but the resolve only runs under flag-ON — flag-OFF
			// returns an empty array without invoking the
			// freeman_core/infinite_scroll/selector filter (CONTRACT 2:
			// flag is master switch). JS-side IIFE treats empty array as
			// "use FALLBACK", so flag-OFF behavior stays byte-identical.
			'containerSelector'   => $flag_on ? $this->resolve_container_selector() : array(),
		);
	}

	/**
	 * Wrapper-render predicate.
	 *
	 * Wave 3.1b. Flag is master switch — under flag-OFF returns false
	 * without firing the filter (listeners cannot force-enable when the
	 * flag is off). Under flag-ON, returns true on standard WC archive
	 * contexts (shop, product taxonomy, search-as-product-archive); the
	 * filter then runs as the final word, so listeners can force-enable
	 * on custom contexts or force-disable on a specific archive.
	 *
	 * Block-based standalone Product Collection / Query Loop contexts
	 * do not fire `woocommerce_before_shop_loop`, so this predicate is
	 * never consulted there — the wrapper hooks simply don't engage on
	 * those contexts. JS-side IS still works on block grids via the
	 * existing CONTAINER_SELECTORS coverage.
	 *
	 * @return bool
	 */
	public function should_render_wrapper() {
		if ( ! Feature_Flags::is_enabled( 'infinite_scroll', 'trigger_modes' ) ) {
			return false;
		}
		$resolved = is_shop()
			|| is_product_taxonomy()
			|| ( is_search() && ( 'product' === get_query_var( 'post_type' ) || is_post_type_archive( 'product' ) ) );
		$resolved = apply_filters( 'freeman_core/infinite_scroll/should_render_wrapper', $resolved );
		return (bool) $resolved;
	}

	/**
	 * Render the opening wrapper around the WC product loop.
	 *
	 * Attached to `woocommerce_before_shop_loop` priority 5 inside boot()
	 * (only when the trigger-modes flag is on). Caches the predicate to
	 * $this->wrapper_active so the closing bracket reuses the same
	 * decision and can't produce an orphan <div>.
	 *
	 * @return void
	 */
	public function render_grid_wrapper_open() {
		$this->wrapper_active = $this->should_render_wrapper();
		if ( ! $this->wrapper_active ) {
			return;
		}
		do_action( 'freeman_core/infinite_scroll/before_render' );
		echo '<div class="freeman-is-wrapper">';
	}

	/**
	 * Render the closing wrapper around the WC product loop.
	 *
	 * Attached to `woocommerce_after_shop_loop` priority 999 inside boot()
	 * (only when the trigger-modes flag is on). Reuses the cached
	 * $this->wrapper_active set by render_grid_wrapper_open().
	 *
	 * @return void
	 */
	public function render_grid_wrapper_close() {
		if ( ! $this->wrapper_active ) {
			return;
		}
		echo '</div>';
		do_action( 'freeman_core/infinite_scroll/after_render' );
		$this->wrapper_active = false;
	}

	/**
	 * Resolve the JS-side container selector list.
	 *
	 * Reads the `container_selector` setting, applies the
	 * `freeman_core/infinite_scroll/selector` filter (final word per the
	 * freeman-core setting+filter convention), and normalizes to an array
	 * of non-empty strings. Empty result tells the JS-side IIFE to fall
	 * back to its hardcoded 11-entry FALLBACK list (footgun guard against
	 * a merchant typo blanking IS site-wide).
	 *
	 * Called from localized_payload() under flag-ON only.
	 *
	 * @return array<int,string>
	 */
	public function resolve_container_selector() {
		$setting  = $this->get_option( 'container_selector', '' );
		$resolved = apply_filters( 'freeman_core/infinite_scroll/selector', $setting );
		if ( is_string( $resolved ) ) {
			$resolved = trim( $resolved );
			return '' === $resolved ? array() : array( $resolved );
		}
		if ( is_array( $resolved ) ) {
			$filtered = array_filter(
				$resolved,
				static function ( $s ) {
					return is_string( $s ) && '' !== trim( $s );
				}
			);
			return array_values( $filtered );
		}
		return array();
	}

	/**
	 * Build the inline CSS that exposes Wave 4.3 skeleton/fade tokens as
	 * `--fm-is-*` custom properties on `:root`. Always emitted from
	 * `enqueue()` (uniform-shape Mechanism A from Wave 3.1b precedent).
	 *
	 * Settings_Hub already validates the underlying options at write-time
	 * (`color` rejects bad hex → `''`; `number` coerces to numeric). Here
	 * we treat empty strings as "use default" and silently clamp numbers
	 * to sensible ranges. No Logger emission — write-time validation has
	 * already filtered, so a per-pageload warning would just spam.
	 *
	 * @return string
	 */
	public function inline_token_css() {
		$base      = $this->get_option( 'shimmer_base_color', '#eceff3' );
		$highlight = $this->get_option( 'shimmer_highlight_color', '#f6f8fb' );
		$shimmer   = $this->get_option( 'shimmer_duration_ms', 1400 );
		$fade      = $this->get_option( 'fade_duration_ms', 550 );
		$transform = $this->get_option( 'fade_transform_px', 18 );

		if ( ! is_string( $base ) || '' === trim( (string) $base ) ) {
			$base = '#eceff3';
		}
		if ( ! is_string( $highlight ) || '' === trim( (string) $highlight ) ) {
			$highlight = '#f6f8fb';
		}
		$shimmer   = max( 0, min( 60000, (int) $shimmer ) );
		$fade      = max( 0, min( 60000, (int) $fade ) );
		$transform = max( 0, min( 200, (int) $transform ) );

		return ':root{'
			. '--fm-is-shimmer-base:' . $base . ';'
			. '--fm-is-shimmer-highlight:' . $highlight . ';'
			. '--fm-is-shimmer-duration:' . $shimmer . 'ms;'
			. '--fm-is-fade-duration:' . $fade . 'ms;'
			. '--fm-is-fade-transform:translateY(' . $transform . 'px);'
			. '}';
	}
}
