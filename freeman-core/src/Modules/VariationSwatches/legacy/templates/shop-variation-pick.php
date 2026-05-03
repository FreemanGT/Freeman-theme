<?php
/**
 * Shop / archive variation picker template (1.6.0).
 *
 * Renders a compact, self-contained variation picker in place of the default
 * WooCommerce "Choose options" loop button. Each attribute gets its own row of
 * swatches; anything beyond `$max_visible` collapses behind a "+N" reveal pill.
 * Once every attribute is chosen, the Add-to-cart button becomes active and
 * submits via AJAX to our wc-ajax=etucart_shop_add_to_cart endpoint.
 *
 * Expected variables:
 * @var WC_Product $product
 * @var array      $prepared    Output of Etucart_VS_Archive::prepare_product_data().
 * @var int        $max_visible How many options to show per attribute before "+N".
 * @var bool       $show_price  Whether to render the "החל מ: ₪X" line.
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pid = absint( $prepared['pid'] ?? 0 );
if ( ! $pid || empty( $prepared['attrs'] ) ) {
	return;
}

// Encode variations + attrs as JSON for the JS matcher. `wc_esc_json` keeps
// the braces / quotes intact when embedded in an HTML attribute.
$variations_json = wp_json_encode( $prepared['variations'] ?? [] );
$variations_attr = function_exists( 'wc_esc_json' )
	? wc_esc_json( $variations_json )
	: _wp_specialchars( wp_check_invalid_utf8( $variations_json ), ENT_QUOTES, 'UTF-8', true );

$nonce     = esc_attr( $prepared['nonce']    ?? '' );
$cart_url  = esc_url(  $prepared['cart_url'] ?? '' );
?>
<div class="etucart-shop-pick<?php
	echo ! empty( $hide_attr_labels ) ? ' etucart-shop-pick--no-labels' : '';
	echo ! empty( $hide_selected )    ? ' etucart-shop-pick--no-selected' : '';
?>"
	 dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"
	 data-product-id="<?php echo esc_attr( $pid ); ?>"
	 data-nonce="<?php echo $nonce; ?>"
	 data-variations="<?php echo $variations_attr; // already escaped above ?>"
	 data-cart-url="<?php echo $cart_url; ?>">

	<?php /* Honeypot — see ajax_add_to_cart() in class-archive.php. */ ?>
	<input type="text" name="_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">

	<?php if ( $show_price ) :
		// Only render the "החל מ:" prefix when the product actually has a
		// variation price range. For flat-priced products (e.g. shoes where
		// every size is the same price) we just show the price number.
		// JS toggles the `.has-range` class when the user picks / clears
		// a full variation so the prefix hides on selection and returns
		// when the selection is cleared — but only if the product had a
		// range to begin with.
		$has_range = ! empty( $prepared['has_price_range'] );
		?>
		<div class="etucart-shop-pick__price<?php echo $has_range ? ' has-range' : ''; ?>"
			 data-has-range="<?php echo $has_range ? '1' : '0'; ?>"
			 aria-live="polite">
			<span class="etucart-shop-pick__price-prefix"<?php if ( ! $has_range ) : ?> hidden<?php endif; ?>><?php esc_html_e( 'החל מ:', 'freeman-core' ); ?></span>
			<span class="etucart-shop-pick__price-value">
				<?php echo wp_kses_post( $prepared['from_price'] ?? '' ); ?>
			</span>
		</div>
	<?php endif; ?>

	<div class="etucart-shop-pick__attrs">
		<?php foreach ( (array) $prepared['attrs'] as $attr ) :
			$name       = (string) ( $attr['name'] ?? '' );
			$label      = (string) ( $attr['label'] ?? '' );
			$is_color   = ! empty( $attr['is_color'] );
			$selected   = (string) ( $attr['selected'] ?? '' );
			$options    = is_array( $attr['options'] ?? null ) ? $attr['options'] : [];
			$total      = count( $options );
			$hide_after = (int) $max_visible;
			?>
			<div class="etucart-shop-pick__attr<?php echo $is_color ? ' etucart-shop-pick__attr--color' : ' etucart-shop-pick__attr--button'; ?>"
				 data-attribute-name="<?php echo esc_attr( $name ); ?>"
				 data-max-visible="<?php echo esc_attr( $hide_after ); ?>">
				<div class="etucart-shop-pick__attr-head">
					<span class="etucart-shop-pick__attr-label"><?php echo esc_html( $label ); ?>:</span>
					<span class="etucart-shop-pick__attr-selected" data-default-text="<?php esc_attr_e( 'בחר/י אפשרות', 'freeman-core' ); ?>">
						<?php
						if ( '' !== $selected ) {
							foreach ( $options as $opt ) {
								if ( (string) $opt['v'] === $selected ) {
									echo esc_html( $opt['n'] );
									break;
								}
							}
						}
						?>
					</span>
				</div>

				<div class="etucart-shop-pick__opts">
					<?php foreach ( $options as $i => $opt ) :
						$value       = (string) ( $opt['v'] ?? '' );
						$display     = (string) ( $opt['n'] ?? $value );
						$hex_raw     = (string) ( $opt['hex'] ?? '' );
						$safe_hex    = Etucart_VS_Plugin::sanitize_hex_color( $hex_raw );
						$is_light    = $safe_hex && Etucart_VS_Plugin::is_light_hex( $safe_hex );
						// Wave 2.2 / 4b (1.11.24) — image wins over color when both
						// are set. The 'img' key is only present in the payload when
						// the image_swatches flag is on, so flag-OFF this branch is
						// dead code and renders identically to pre-1.11.24.
						$img_url    = (string) ( $opt['img'] ?? '' );
						$is_image   = '' !== $img_url;
						$is_selected = ( '' !== $selected && $value === $selected );
						$is_overflow = ( $i >= $hide_after );
						$classes     = [ 'etucart-shop-pick__opt' ];
						if ( $is_image ) {
							$classes[] = 'etucart-shop-pick__opt--image';
						} elseif ( $is_color ) {
							$classes[] = 'etucart-shop-pick__opt--color';
						} else {
							$classes[] = 'etucart-shop-pick__opt--button';
						}
						if ( $is_selected ) $classes[] = 'is-selected';
						if ( $is_overflow ) $classes[] = 'is-overflow';
						if ( $is_light   )  $classes[] = 'is-light';
						?>
						<button type="button"
								class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
								data-value="<?php echo esc_attr( $value ); ?>"
								data-name="<?php echo esc_attr( $display ); ?>"
								<?php if ( $is_color && $safe_hex ) : ?>data-hex="<?php echo esc_attr( $safe_hex ); ?>"<?php endif; ?>
								aria-label="<?php echo esc_attr( $display ); ?>"
								aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
								<?php if ( $is_overflow ) : ?>hidden<?php endif; ?>>
							<?php if ( $is_image ) : ?>
								<span class="etucart-shop-pick__opt-img" aria-hidden="true"
									style="background-image:url('<?php echo esc_url( $img_url ); ?>')"></span>
								<span class="screen-reader-text"><?php echo esc_html( $display ); ?></span>
							<?php elseif ( $is_color ) : ?>
								<span class="etucart-shop-pick__dot" aria-hidden="true"
									<?php if ( $safe_hex ) : ?> style="background-color:<?php echo esc_attr( $safe_hex ); ?>"<?php endif; ?>></span>
								<span class="screen-reader-text"><?php echo esc_html( $display ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $display ); ?>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>

					<?php if ( $total > 1 ) :
						// Render the button hidden by default and let the JS
						// overflow scanner reveal it (and set the correct
						// count) only when there are chips that actually
						// don't fit. Without that, narrow cards that overflow
						// fewer items than `max_visible` would still render
						// "+N" with the PHP-calculated count, and cards where
						// everything fits would briefly flash "+0" before the
						// scanner runs.
						$overflow_count = max( 1, $total - $hide_after );
						?>
						<button type="button"
								class="etucart-shop-pick__more"
								aria-expanded="false"
								data-count="<?php echo esc_attr( $overflow_count ); ?>"
								hidden>
							<span class="etucart-shop-pick__more-label">+<?php echo absint( $overflow_count ); ?></span>
						</button>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<button type="button"
			class="etucart-shop-pick__add"
			disabled
			aria-disabled="true">
		<?php esc_html_e( 'הוספה לעגלה', 'freeman-core' ); ?>
	</button>
	<?php
	// NOTE (1.6.4): the per-card ".etucart-shop-pick__message" element was
	// removed. Long WooCommerce notices (e.g. stock-limit rejections) used
	// to render inside the card, which resized the card and broke the
	// shop grid. All add-to-cart feedback now goes through a singleton
	// fixed-position toast stack rendered into document.body by
	// assets/js/etucart-shop-swatches.js, so card geometry is unaffected
	// regardless of message length.
	?>
</div>
