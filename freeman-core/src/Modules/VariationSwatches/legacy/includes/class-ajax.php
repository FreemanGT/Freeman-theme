<?php
/**
 * Handles the "Buy Now" redirect. When the buy-now flag is present in the
 * add-to-cart submission, the user is redirected to the checkout page after
 * the item is added to the cart successfully.
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Etucart_VS_Ajax' ) ) :

class Etucart_VS_Ajax {

	public const BUY_NOW_FIELD = 'etucart_buy_now';

	public function register(): void {
		// PHP_INT_MAX so we run last and defeat any plugin that hooks
		// `woocommerce_add_to_cart_redirect` at a higher priority
		// (FunnelKit / slide-in carts / etc. often do).
		add_filter( 'woocommerce_add_to_cart_redirect', [ $this, 'redirect_to_checkout' ], PHP_INT_MAX, 2 );

		// FunnelKit Cart specifically swaps the single-product add-to-cart
		// flow with an AJAX request that triggers a slide-in cart overlay.
		// When we detect our buy-now flag on that AJAX path, suppress the
		// slide-in cart so the browser follows our redirect straight to
		// checkout instead.
		add_filter( 'fkcart_show_cart_after_add_to_cart', [ $this, 'suppress_fkcart_on_buy_now' ], PHP_INT_MAX );
		add_filter( 'fkcart_open_cart', [ $this, 'suppress_fkcart_on_buy_now' ], PHP_INT_MAX );
	}

	/**
	 * If the submission included our flag, redirect to checkout after a
	 * successful add-to-cart. Otherwise let WooCommerce decide.
	 */
	public function redirect_to_checkout( $url, $adding_to_cart = null ) {
		// Use $_REQUEST because this runs on the POST back from the add-to-cart form.
		if ( ! empty( $_REQUEST[ self::BUY_NOW_FIELD ] ) ) {
			$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
			// Allow custom funnel URLs (same filter exposed to the frontend).
			$checkout = (string) apply_filters( 'etucart_vs_checkout_url', $checkout );
			if ( $checkout ) {
				return $checkout;
			}
		}
		return $url;
	}

	/**
	 * If the current request is a buy-now submission, tell FunnelKit Cart
	 * NOT to open its slide-in cart overlay. We want the user to go
	 * straight to checkout.
	 */
	public function suppress_fkcart_on_buy_now( $should ) {
		if ( ! empty( $_REQUEST[ self::BUY_NOW_FIELD ] ) ) {
			return false;
		}
		return $should;
	}
}

endif; // class_exists Etucart_VS_Ajax
