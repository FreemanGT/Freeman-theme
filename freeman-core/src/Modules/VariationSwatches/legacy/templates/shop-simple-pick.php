<?php
/**
 * Shop / archive simple-product picker template (1.6.7).
 *
 * Companion to shop-variation-pick.php — renders a compact add-to-cart
 * card for a simple product inside the shop grid, reusing the same
 * `.etucart-shop-pick` wrapper so existing CSS (card geometry, toast
 * stack, Add-to-cart button styling) applies without duplication. The
 * modifier class `.etucart-shop-pick--simple` is what the JS handler
 * keys off to skip the variation matcher.
 *
 * Expected variables:
 * @var WC_Product $product
 * @var array      $prepared    Output of Etucart_VS_Archive::prepare_simple_product_data().
 * @var bool       $show_price  Whether to render the price line.
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pid = absint( $prepared['pid'] ?? 0 );
if ( ! $pid ) {
	return;
}

$nonce          = esc_attr( $prepared['nonce']    ?? '' );
$cart_url       = esc_url(  $prepared['cart_url'] ?? '' );
$is_purchasable = ! empty( $prepared['is_purchasable'] );
$min_qty        = max( 1, (int) ( $prepared['min_qty'] ?? 1 ) );
$max_qty        = (int) ( $prepared['max_qty'] ?? -1 ); // -1 = no limit
?>
<div class="etucart-shop-pick etucart-shop-pick--simple"
	 dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"
	 data-product-id="<?php echo esc_attr( $pid ); ?>"
	 data-nonce="<?php echo $nonce; ?>"
	 data-cart-url="<?php echo $cart_url; ?>">

	<?php /* Honeypot — see ajax_add_to_cart() in class-archive.php. */ ?>
	<input type="text" name="_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">

	<?php if ( $show_price && ! empty( $prepared['price_html'] ) ) : ?>
		<div class="etucart-shop-pick__price">
			<span class="etucart-shop-pick__price-value">
				<?php echo wp_kses_post( $prepared['price_html'] ); ?>
			</span>
		</div>
	<?php endif; ?>

	<?php if ( $is_purchasable ) : ?>
		<div class="etucart-shop-pick__row">
			<div class="etucart-shop-pick__qty-wrap">
				<button type="button"
						class="etucart-shop-pick__qty-btn etucart-shop-pick__qty-btn--plus"
						aria-label="+"
						tabindex="-1">+</button>
				<input type="number"
					   class="etucart-shop-pick__qty"
					   value="<?php echo esc_attr( $min_qty ); ?>"
					   min="<?php echo esc_attr( $min_qty ); ?>"
					   <?php if ( $max_qty > 0 ) : ?>max="<?php echo esc_attr( $max_qty ); ?>"<?php endif; ?>
					   step="1"
					   inputmode="numeric"
					   aria-label="<?php esc_attr_e( 'כמות', 'freeman-core' ); ?>" />
				<button type="button"
						class="etucart-shop-pick__qty-btn etucart-shop-pick__qty-btn--minus"
						aria-label="−"
						tabindex="-1">−</button>
			</div>

			<button type="button"
					class="etucart-shop-pick__add"
					aria-disabled="false">
				<?php esc_html_e( 'הוספה לעגלה', 'freeman-core' ); ?>
			</button>
		</div>
	<?php else : ?>
		<button type="button"
				class="etucart-shop-pick__add is-oos"
				disabled
				aria-disabled="true">
			<?php esc_html_e( 'אזל מהמלאי', 'freeman-core' ); ?>
		</button>
	<?php endif; ?>
</div>
