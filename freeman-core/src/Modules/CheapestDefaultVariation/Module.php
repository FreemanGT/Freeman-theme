<?php
/**
 * Cheapest default variation module.
 *
 * Picks the lowest-priced in-stock variation of every variable product and
 * sets it as the default so "Add to cart" is active immediately.
 *
 * Ported from auto-default-cheapest-variation.php (mu-plugin snippet).
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\CheapestDefaultVariation;

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Core\Logger;
use Freeman\Core\Core\Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	/**
	 * Per-request cache keyed by product id.
	 *
	 * @var array<int, array>
	 */
	private $cache = array();

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'cheapest_default_variation';
	}

	/**
	 * Module label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Cheapest Default Variation', 'freeman-core' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Auto-selects the cheapest in-stock variation as the default so customers can add to cart without picking options.', 'freeman-core' );
	}

	/**
	 * Settings schema.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array(
			'respect_manual_defaults' => array(
				'label'          => __( 'Respect manual defaults', 'freeman-core' ),
				'type'           => 'checkbox',
				'checkbox_label' => __( 'Leave defaults set in the product editor alone', 'freeman-core' ),
				'description'    => __( 'When on, manually chosen defaults take precedence over this automatic selection.', 'freeman-core' ),
				'default'        => 1,
			),
			'pdp_only'                => array(
				'label'          => __( 'Apply on product pages only', 'freeman-core' ),
				'type'           => 'checkbox',
				'checkbox_label' => __( 'Skip this auto-selection on shop / archive / loop contexts', 'freeman-core' ),
				'description'    => __( 'When on, archive and shop-loop swatches/pickers render with no pre-selected variation — the customer has to actively pick one. The single-product page (PDP) still auto-selects the cheapest.', 'freeman-core' ),
				'default'        => 1,
			),
			'strategy'                => array(
				'label'       => __( 'Default selection strategy', 'freeman-core' ),
				'type'        => 'select',
				'options'     => array(
					'cheapest'       => __( 'Cheapest in-stock variation', 'freeman-core' ),
					'first_in_stock' => __( 'First in-stock variation (in WooCommerce order)', 'freeman-core' ),
				),
				'default'     => 'cheapest',
				'description' => __( 'Which variation gets pre-selected. "Cheapest" scans display_price; "First in-stock" returns the first variation WooCommerce returns that is in stock and purchasable. Only takes effect when the strategy selector feature flag is on.', 'freeman-core' ),
			),
		);
	}

	/**
	 * Boot.
	 */
	public function boot() {
		add_filter( 'woocommerce_product_get_default_attributes', array( $this, 'default_cheapest_variation' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_default_attributes', array( $this, 'default_cheapest_variation' ), 10, 2 );
	}

	/**
	 * Filter callback: return the cheapest variation attribute set.
	 *
	 * @param array       $default_attributes Existing defaults.
	 * @param \WC_Product $product            Product object.
	 * @return array
	 */
	public function default_cheapest_variation( $default_attributes, $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return $default_attributes;
		}
		if ( ! $product->is_type( 'variable' ) ) {
			return $default_attributes;
		}
		if ( (int) $this->get_option( 'respect_manual_defaults', 1 ) && ! empty( $default_attributes ) ) {
			return $default_attributes;
		}

		/**
		 * Filter whether the cheapest-variation auto-selection should run for
		 * this product. Returning false short-circuits the picker and leaves
		 * `$default_attributes` untouched — useful for per-product opt-outs
		 * (e.g. via product meta) without disabling the module globally.
		 *
		 * @since 1.11.0
		 *
		 * @param bool        $should_apply       Whether to apply the picker. Default true.
		 * @param \WC_Product $product            The variable product.
		 * @param array       $default_attributes Existing defaults at the call site.
		 */
		$should_apply = apply_filters( 'freeman_core/cheapest_variation/should_apply', true, $product, $default_attributes );
		if ( ! $should_apply ) {
			return $default_attributes;
		}
		// PDP-only mode (default on): skip pre-selection on shop / archive
		// loops so swatches in those contexts render with nothing chosen
		// and the customer has to actively pick. `is_product()` is true on
		// the single-product page only — false on shop, taxonomy archives,
		// search, home, etc. We allow the filter through during admin/AJAX
		// because variation logic still needs the cheapest pick there.
		if ( (int) $this->get_option( 'pdp_only', 1 ) ) {
			$on_pdp = function_exists( 'is_product' ) && is_product();
			$is_admin_ctx = ( function_exists( 'is_admin' ) && is_admin() )
				|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
				|| ( defined( 'REST_REQUEST' ) && REST_REQUEST );
			if ( ! $on_pdp && ! $is_admin_ctx ) {
				return $default_attributes;
			}
		}

		$product_id = $product->get_id();
		if ( isset( $this->cache[ $product_id ] ) ) {
			return $this->cache[ $product_id ];
		}

		$variations = $product->get_available_variations();
		if ( empty( $variations ) ) {
			$this->cache[ $product_id ] = $default_attributes;
			return $default_attributes;
		}

		// Flag gate: with the strategy selector OFF (default), run the legacy
		// cheapest-only path. WC core's add-to-cart-variation.js reads the
		// resolved default off the DOM after PHP runs and the data shape is
		// unchanged across strategies, so no front-end JS coordination is
		// needed when the dispatcher routes to first_in_stock.
		if ( ! Feature_Flags::is_enabled( 'cheapest_variation', 'strategy' ) ) {
			$cheapest = $this->pick_cheapest( $variations );
		} else {
			$strategy = $this->dispatch_strategy( $product );
			$cheapest = ( 'first_in_stock' === $strategy )
				? $this->pick_first_in_stock( $variations )
				: $this->pick_cheapest( $variations );
		}

		/**
		 * Filter the variation chosen as the default. Receives the variation
		 * array selected by the cheapest-price scan (or null if none qualified)
		 * and the full eligible list, and must return either a variation array
		 * shaped like the entries in `$variations` or null. Returning null or
		 * an array without an `attributes` key leaves `$default_attributes`
		 * unchanged.
		 *
		 * @since 1.11.0
		 *
		 * @param array|null  $cheapest   The picked variation array, or null.
		 * @param \WC_Product $product    The variable product.
		 * @param array[]     $variations Eligible variation arrays from `get_available_variations()`.
		 */
		$cheapest = apply_filters( 'freeman_core/cheapest_variation/chosen', $cheapest, $product, $variations );

		if ( is_array( $cheapest ) && ! empty( $cheapest['attributes'] ) ) {
			foreach ( $cheapest['attributes'] as $key => $value ) {
				$clean_key                        = str_replace( 'attribute_', '', $key );
				$default_attributes[ $clean_key ] = $value;
			}
		}

		$this->cache[ $product_id ] = $default_attributes;
		return $default_attributes;
	}

	/**
	 * Resolve the strategy name for a product.
	 *
	 * Order: setting (baseline) → per-product meta (override) → filter (final word).
	 * Filter values outside the supported enum log a Logger warning and fall
	 * back to the pre-filter resolved value (not blindly to 'cheapest', so an
	 * explicit merchant meta choice is not silently dropped).
	 *
	 * @param \WC_Product $product Variable product.
	 * @return string 'cheapest' | 'first_in_stock'.
	 */
	private function dispatch_strategy( $product ) {
		$strategy = (string) $this->get_option( 'strategy', 'cheapest' );

		$meta = get_post_meta( $product->get_id(), '_freeman_cheapest_variation_strategy', true );
		if ( is_string( $meta ) && '' !== $meta ) {
			$strategy = $meta;
		}

		$resolved_pre_filter = $strategy;

		/**
		 * Filters the strategy name used to pre-select a default variation.
		 *
		 * Resolved value is the per-product meta override if set, otherwise the
		 * module's `strategy` setting. Listeners can override unconditionally.
		 *
		 * Returning a value outside the supported enum logs a warning via Logger
		 * and falls back to the pre-filter resolved value.
		 *
		 * @since 1.11.32
		 *
		 * @param string      $strategy Resolved strategy: 'cheapest' | 'first_in_stock'.
		 * @param \WC_Product $product  The variable product being considered.
		 */
		$strategy = (string) apply_filters( 'freeman_core/cheapest_variation/strategy', $strategy, $product );

		if ( ! in_array( $strategy, array( 'cheapest', 'first_in_stock' ), true ) ) {
			Logger::log(
				sprintf(
					'Invalid cheapest-variation strategy "%s" — falling back to "%s"',
					$strategy,
					$resolved_pre_filter
				),
				'warning'
			);
			$strategy = $resolved_pre_filter;
		}

		return $strategy;
	}

	/**
	 * Pick the cheapest in-stock + purchasable variation.
	 *
	 * `display_price` is the price WC would actually charge — sale price
	 * when on sale, regular price otherwise — so a single comparison over
	 * this field correctly picks the cheapest *effective* price without a
	 * separate sale-vs-regular branch.
	 *
	 * @param array[] $variations Eligible variation arrays from `get_available_variations()`.
	 * @return array|null
	 */
	private function pick_cheapest( $variations ) {
		$cheapest       = null;
		$cheapest_price = null;
		foreach ( $variations as $variation ) {
			if ( empty( $variation['is_in_stock'] ) || empty( $variation['is_purchasable'] ) ) {
				continue;
			}
			$price = isset( $variation['display_price'] ) ? $variation['display_price'] : null;
			if ( null === $price || '' === $price ) {
				continue;
			}
			if ( null === $cheapest_price || (float) $price < (float) $cheapest_price ) {
				$cheapest_price = (float) $price;
				$cheapest       = $variation;
			}
		}
		return $cheapest;
	}

	/**
	 * Pick the first in-stock + purchasable variation.
	 *
	 * "First" = first variation returned by WC's `get_available_variations()`
	 * that passes the in-stock + purchasable + has-numeric-display_price gates.
	 * WC's internal order is not contractually stable across versions or sites
	 * — see test_first_in_stock_pins_wc_order_assumption for the assumption
	 * this method depends on.
	 *
	 * @param array[] $variations Eligible variation arrays from `get_available_variations()`.
	 * @return array|null
	 */
	private function pick_first_in_stock( $variations ) {
		foreach ( $variations as $variation ) {
			if ( empty( $variation['is_in_stock'] ) || empty( $variation['is_purchasable'] ) ) {
				continue;
			}
			$price = isset( $variation['display_price'] ) ? $variation['display_price'] : null;
			if ( null === $price || '' === $price ) {
				continue;
			}
			return $variation;
		}
		return null;
	}
}
