<?php
/**
 * Simple-product buy-box template (1.6.7).
 *
 * Mirrors the visual shell of variation-buy-box.php for simple products
 * (no variations, no swatch rows, no hidden selects) so the same CSS
 * (`.etucart-buy-box`, `.etucart-actions`, `.etucart-sticky-bar`) and the
 * same Buy Now JS delegate handle it without special casing in the
 * stylesheet. The outer <form> intentionally omits the `variations_form`
 * class so WooCommerce's core wc-add-to-cart-variation.js does NOT attach
 * to it (that script asserts a full variations payload and would throw).
 *
 * @var WC_Product $product
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product_id = $product->get_id();

// A product counts as buyable only if it's both purchasable (published,
// priced, allowed) AND in stock. Either failing collapses the UI down to
// a single disabled "אזל מהמלאי" button — no Buy Now, no quantity, no
// sticky bar — so a priceless or out-of-stock product renders the same
// clear OOS state instead of a confusing half-enabled form.
$buyable = $product->is_purchasable() && $product->is_in_stock();

// Price line, mirroring variation-buy-box.php's `.etucart-pdp-price`. WC's
// own `woocommerce_template_single_price` is removed for simple products in
// Etucart_VS_Frontend::maybe_suppress_pdp_price() so this is the single price
// surface — same markup the variable buy box uses (no range, prefix hidden),
// so the shared `.etucart-pdp-price` CSS styles it and the quick-view
// `.woosq-product .price:not(.etucart-pdp-price)` suppression keeps it. There
// is no JS interaction here: this form has no `variations_form` class, so
// WC's variation script never fires `found_variation` / `reset_data` against
// it, and the price stays as rendered server-side.
$pdp_price_html = $product->get_price_html(); ?>
<p class="price etucart-pdp-price"
   dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"
   data-pdp-price
   data-has-range="0"
   data-from-html="<?php echo esc_attr( $pdp_price_html ); ?>"
   aria-live="polite">
	<span class="etucart-pdp-price__prefix" hidden><?php esc_html_e( 'החל מ:', 'freeman-core' ); ?></span>
	<span class="etucart-pdp-price__value"><?php echo wp_kses_post( $pdp_price_html ); ?></span>
</p>

<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form class="cart etucart-buy-box etucart-buy-box--simple"
	  action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	  method="post"
	  enctype="multipart/form-data"
	  dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"
	  data-product_id="<?php echo absint( $product_id ); ?>">

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<div class="etucart-actions">
		<div class="etucart-actions__row">
			<button type="submit"
					class="etucart-add-to-cart single_add_to_cart_button button alt<?php echo $buyable ? '' : ' disabled'; ?>"
					name="add-to-cart"
					value="<?php echo absint( $product_id ); ?>"
					<?php echo $buyable ? '' : 'disabled aria-disabled="true"'; ?>>
				<?php echo $buyable
					? esc_html__( 'הוספה לעגלה', 'freeman-core' )
					: esc_html__( 'אזל מהמלאי', 'freeman-core' ); ?>
			</button>

			<?php
			if ( $buyable ) {
				woocommerce_quantity_input(
					[
						'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
						'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
						'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( (int) wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(),
						'classes'     => [ 'input-text', 'qty', 'text', 'etucart-qty__input' ],
					]
				);
			}
			?>
		</div>

		<?php // Buy Now is only rendered when the product is actually buyable.
			  // For OOS / priceless products the single ATC "אזל מהמלאי"
			  // button above is the full action surface — showing a second
			  // disabled pill below it just creates visual noise. ?>
		<?php if ( $buyable ) : ?>
			<button type="submit"
					class="etucart-buy-now single_add_to_cart_button button"
					name="add-to-cart"
					value="<?php echo absint( $product_id ); ?>"
					data-etucart-buy-now="1">
				<?php esc_html_e( 'קנה עכשיו', 'freeman-core' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<input type="hidden" name="product_id" value="<?php echo absint( $product_id ); ?>" />
	<input type="hidden" name="<?php echo esc_attr( Etucart_VS_Ajax::BUY_NOW_FIELD ); ?>" class="etucart-buy-now-flag" value="" />

	<?php if ( $buyable ) : ?>
		<?php // Sticky mobile bottom bar — hidden on desktop via CSS, revealed
			  // when the main buy box scrolls out of viewport. The shared
			  // etucart-swatches.js handles the toggle via .etucart-buy-now
			  // as the anchor element (present in both variable + simple). ?>
		<div class="etucart-sticky-bar" aria-hidden="true">
			<div class="etucart-sticky-bar__inner">
				<div class="etucart-sticky-bar__price" aria-live="polite">
					<span class="etucart-sticky-bar__price-value">
						<?php echo wp_kses_post( $product->get_price_html() ); ?>
					</span>
				</div>
				<button type="submit"
						class="etucart-sticky-bar__buy etucart-sticky-bar__buy--atc single_add_to_cart_button"
						name="add-to-cart"
						value="<?php echo absint( $product_id ); ?>">
					<?php esc_html_e( 'הוספה לעגלה', 'freeman-core' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
