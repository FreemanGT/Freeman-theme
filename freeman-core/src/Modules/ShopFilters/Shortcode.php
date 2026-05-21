<?php
/**
 * Shop Filters shortcode — [freeman_shop_filters].
 *
 * Drops the filter panel into an Elementor shortcode/HTML element (decision
 * §5.4 — no Elementor widget). Server-renders the initial facet tree for the
 * current context so the first paint is correct and SEO-visible without JS, and
 * enqueues the front-end script that takes over on interaction. Only wired when
 * the frontend feature flag is on (Module::boot()); flag-off renders nothing.
 *
 * The render path touches WooCommerce / the query, so it is exercised by live
 * QA; the registration and the flag-off empty-output contract are unit-tested.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Core\Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode.
 */
final class Shortcode {

	const TAG          = 'freeman_shop_filters';
	const SCRIPT_HANDLE = 'freeman-core-shop-filters';

	/**
	 * Register the shortcode.
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Render the filter panel. Returns an empty string when the frontend flag is
	 * off so the shortcode is inert until explicitly enabled.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts = array() ) {
		if ( ! Feature_Flags::is_enabled( 'shop_filters', 'frontend' ) ) {
			return '';
		}

		$context_id = $this->context_category_id();
		$response   = ( new Query_Builder() )->query( $this->initial_request( $context_id ) );

		$this->enqueue_assets( $context_id );

		ob_start();
		$facets = $response['facets'];
		$count  = $response['count'];
		include $this->template_path( 'filters.php' );
		return (string) ob_get_clean();
	}

	/**
	 * Current product_cat context (0 on the shop page or anywhere else).
	 *
	 * @return int
	 */
	private function context_category_id() {
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return (int) get_queried_object_id();
		}
		return 0;
	}

	/**
	 * Build the initial request from the current URL so a deep-linked filtered
	 * page renders its selection on first paint.
	 *
	 * @param int $context_id Category context.
	 * @return array
	 */
	private function initial_request( $context_id ) {
		$request               = is_array( $_GET ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request['context_id'] = (int) $context_id;
		return $request;
	}

	/**
	 * Enqueue (and localise) the front-end script. CSS lands in 6.3b.
	 *
	 * @param int $context_id Category context.
	 */
	private function enqueue_assets( $context_id ) {
		$fs_base  = FREEMAN_CORE_PATH . 'src/Modules/ShopFilters/assets/';
		$url_base = FREEMAN_CORE_URL . 'src/Modules/ShopFilters/assets/';
		$src      = Module_Base::pick_min_url( $fs_base, $url_base, 'js/shop-filters.js' );

		wp_enqueue_script( self::SCRIPT_HANDLE, $src, array(), FREEMAN_CORE_VERSION, true );
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'FreemanShopFilters',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'action'    => Ajax::ACTION,
				'nonce'     => wp_create_nonce( Ajax::NONCE ),
				'contextId' => (int) $context_id,
			)
		);
	}

	/**
	 * Absolute path to a module template.
	 *
	 * @param string $template Template filename.
	 * @return string
	 */
	private function template_path( $template ) {
		return FREEMAN_CORE_PATH . 'src/Modules/ShopFilters/templates/' . ltrim( $template, '/' );
	}
}
