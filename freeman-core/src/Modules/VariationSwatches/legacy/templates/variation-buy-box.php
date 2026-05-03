<?php
/**
 * Variation buy-box template.
 *
 * Uses WooCommerce's native variations_form structure so that the core
 * add-to-cart-variation.js script handles availability / price / stock in
 * exactly the same way as the default WC template. We simply provide a
 * custom visible UI (swatches + pill buttons) that synchronises with a
 * hidden .variations container of native <select> controls — clicking a
 * swatch dispatches a `change` event on the underlying select, which WC
 * listens to.
 *
 * @var WC_Product_Variable $product
 * @var array $attributes
 * @var array $available_variations
 * @var array $selected_attributes
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var WC_Product_Variable $product */
$product_id = $product->get_id();

// ALWAYS embed the full variations JSON into the form.
//
// WooCommerce core respects the `woocommerce_ajax_variation_threshold` filter
// (default 30) and, when a product has more variations than that, emits
// data-product_variations="false" and switches to per-select AJAX lookups.
// That behaviour is fine for *price/availability updates* but it breaks our
// client-side "out of stock" detection: our JS needs the full variations
// array in memory to compute which attribute-values have matching variations
// that are out of stock (vs. values with no matching variation at all, which
// WC's own script already disables).
//
// Without this data, OOS variations on products with >30 combinations (very
// common for clothing: colors × sizes) render as normal, non-greyed swatches
// because neither WC nor our script flags them. Embedding the JSON adds a
// few KB to the page but restores consistent OOS greying across all sizes
// of variable products and all contexts (single-product page, quick-view
// modals, AJAX-filtered shop pages).
$variations_json = wp_json_encode( $available_variations );
$variations_attr = function_exists( 'wc_esc_json' )
	? wc_esc_json( $variations_json )
	: _wp_specialchars( wp_check_invalid_utf8( $variations_json ), ENT_QUOTES, 'UTF-8', true );

// Normalise attributes once: for each attribute, build a list of option items
// with { value, name, hex } and the current selected value.
$prepared = [];
foreach ( $attributes as $attribute_name => $options ) {
	$taxonomy       = 0 === strpos( $attribute_name, 'pa_' ) ? $attribute_name : '';
	$sanitized_name = sanitize_title( $attribute_name );
	$input_name     = 'attribute_' . $sanitized_name;
	$selected = isset( $_REQUEST[ $input_name ] )
		? wc_clean( wp_unslash( $_REQUEST[ $input_name ] ) )
		: ( isset( $selected_attributes[ $sanitized_name ] ) ? (string) $selected_attributes[ $sanitized_name ] : '' );

	// Reject pre-selections that don't match a valid option (tampered URLs).
	$valid_values = array_map( 'strval', (array) $options );
	if ( '' !== $selected && ! in_array( (string) $selected, $valid_values, true ) ) {
		$selected = '';
	}

	$is_color = $taxonomy && Etucart_VS_Plugin::attribute_is_color( $taxonomy );
	// Wave 2.2 / 4b (1.11.24) — image wins over color when both are set.
	// Flag-OFF: $img stays empty for every option and the render branch is
	// dead code, so output is byte-identical to pre-1.11.24.
	$image_swatches_on = \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'image_swatches' );
	// Wave 2.2 / 4c (1.11.25) — tooltip text per option. Default = term name;
	// admin-overridable via term meta. Flag-OFF: $tt stays empty.
	$tooltip_on        = \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'tooltip' );

	$option_items = [];
	foreach ( $options as $option ) {
		$value = (string) $option;
		$name  = $value;
		$hex   = '';
		$img   = '';
		$tt    = '';
		if ( $taxonomy ) {
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$name = $term->name;
				$hex  = Etucart_VS_Plugin::term_color( (int) $term->term_id );
				if ( $image_swatches_on ) {
					$img = Etucart_VS_Plugin::term_image_url( (int) $term->term_id, 'thumbnail' );
				}
				if ( $tooltip_on ) {
					$tt = Etucart_VS_Plugin::term_tooltip_text( (int) $term->term_id, $name );
				}
			}
		}
		$option_items[] = [
			'value' => $value,
			'name'  => $name,
			'hex'   => $hex,
			'img'   => $img,
			'tt'    => $tt,
		];
	}

	$prepared[ $attribute_name ] = [
		'input_name'   => $input_name,
		'label'        => Etucart_VS_Plugin::resolve_attribute_label( $attribute_name, $product ),
		'is_color'     => $is_color,
		'selected'     => $selected,
		'option_items' => $option_items,
	];
}

// "Starting from" price line (1.7.6). Replaces WC's default price action
// which renders a non-updating range like "₪20 – ₪100" above the form.
// JS swaps `.etucart-pdp-price__value` HTML on `found_variation`, restores
// the min on `reset_data`. The prefix only appears when there's actually a
// range — flat-priced variable products show just the number, no "from".
$pdp_min        = (float) $product->get_variation_price( 'min', true );
$pdp_max        = (float) $product->get_variation_price( 'max', true );
$pdp_has_range  = ( $pdp_max > $pdp_min );
$pdp_min_html   = wc_price( $pdp_min );
?>
<p class="price etucart-pdp-price"
   dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"
   data-pdp-price
   data-has-range="<?php echo $pdp_has_range ? '1' : '0'; ?>"
   data-from-html="<?php echo esc_attr( $pdp_min_html ); ?>"
   aria-live="polite">
	<span class="etucart-pdp-price__prefix"<?php if ( ! $pdp_has_range ) : ?> hidden<?php endif; ?>><?php esc_html_e( 'החל מ:', 'freeman-core' ); ?></span>
	<span class="etucart-pdp-price__value"><?php echo wp_kses_post( $pdp_min_html ); ?></span>
</p>

<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

<form class="variations_form cart etucart-buy-box"
	  action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
	  method="post"
	  enctype="multipart/form-data"
	  dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>"
	  data-product_id="<?php echo absint( $product_id ); ?>"
	  data-product_variations="<?php echo $variations_attr; // already escaped above ?>">

	<?php do_action( 'woocommerce_before_variations_form' ); ?>

	<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
		<p class="stock out-of-stock">
			<?php echo esc_html( apply_filters( 'woocommerce_out_of_stock_message', __( 'This product is currently out of stock and unavailable.', 'woocommerce' ) ) ); ?>
		</p>
	<?php else : ?>

		<div class="etucart-variations">
			<?php foreach ( $prepared as $attribute_name => $p ) : ?>
				<div class="etucart-variation<?php echo $p['is_color'] ? ' etucart-variation--color' : ' etucart-variation--button'; ?>"
					 data-attribute_name="<?php echo esc_attr( $p['input_name'] ); ?>">
					<div class="etucart-variation__head">
						<span class="etucart-variation__label"><?php echo esc_html( $p['label'] ); ?>:</span>
						<span class="etucart-variation__selected" data-default-text="<?php esc_attr_e( 'Choose an option', 'freeman-core' ); ?>">
							<?php
							$selected_label = '';
							foreach ( $p['option_items'] as $opt ) {
								if ( $p['selected'] !== '' && $opt['value'] === $p['selected'] ) {
									$selected_label = $opt['name'];
									break;
								}
							}
							echo esc_html( $selected_label );
							?>
						</span>
					</div>

					<div class="etucart-variation__options">
						<?php foreach ( $p['option_items'] as $opt ) :
							$is_selected = ( $p['selected'] !== '' && $p['selected'] === $opt['value'] );
							// Wave 2.2 / 4b (1.11.24) — image > color > text precedence
							// per option. The 'img' key is always present in the
							// payload (empty string when no image / flag off), so
							// gating on the value is enough.
							$opt_img = (string) ( $opt['img'] ?? '' );
							$is_image_opt = '' !== $opt_img;
							// Wave 2.2 / 4c (1.11.25) — tooltip text per option.
							// Empty string when flag is off, so the data-tooltip
							// attribute is omitted in that case.
							$opt_tt = (string) ( $opt['tt'] ?? '' );
							$emit_tooltip = ( $is_image_opt || $p['is_color'] ) && '' !== $opt_tt;
							?>
							<?php if ( $is_image_opt ) : ?>
								<button type="button"
										class="etucart-swatch etucart-swatch--image<?php echo $is_selected ? ' is-selected' : ''; ?>"
										data-value="<?php echo esc_attr( $opt['value'] ); ?>"
										data-name="<?php echo esc_attr( $opt['name'] ); ?>"
										<?php if ( $emit_tooltip ) : ?>data-tooltip="<?php echo esc_attr( $opt_tt ); ?>"<?php endif; ?>
										aria-label="<?php echo esc_attr( $opt['name'] ); ?>">
									<span class="etucart-swatch__img" aria-hidden="true"
										style="background-image:url('<?php echo esc_url( $opt_img ); ?>')"></span>
									<span class="screen-reader-text"><?php echo esc_html( $opt['name'] ); ?></span>
								</button>
							<?php elseif ( $p['is_color'] ) :
								// Re-validate the hex at render time as defence-in-depth,
								// even though term_color() already sanitises it.
								$safe_hex      = Etucart_VS_Plugin::sanitize_hex_color( (string) $opt['hex'] );
								$is_white_like = Etucart_VS_Plugin::is_light_hex( $safe_hex );
								?>
								<button type="button"
										class="etucart-swatch etucart-swatch--color<?php echo $is_selected ? ' is-selected' : ''; ?><?php echo $is_white_like ? ' is-light' : ''; ?>"
										data-value="<?php echo esc_attr( $opt['value'] ); ?>"
										data-name="<?php echo esc_attr( $opt['name'] ); ?>"
										data-hex="<?php echo esc_attr( $safe_hex ); ?>"
										<?php if ( $emit_tooltip ) : ?>data-tooltip="<?php echo esc_attr( $opt_tt ); ?>"<?php endif; ?>
										aria-label="<?php echo esc_attr( $opt['name'] ); ?>">
									<span class="etucart-swatch__dot" aria-hidden="true"<?php if ( $safe_hex ) : ?> style="background-color:<?php echo esc_attr( $safe_hex ); ?>"<?php endif; ?>></span>
									<span class="screen-reader-text"><?php echo esc_html( $opt['name'] ); ?></span>
								</button>
							<?php else : ?>
								<button type="button"
										class="etucart-swatch etucart-swatch--button<?php echo $is_selected ? ' is-selected' : ''; ?>"
										data-value="<?php echo esc_attr( $opt['value'] ); ?>"
										data-name="<?php echo esc_attr( $opt['name'] ); ?>">
									<?php echo esc_html( $opt['name'] ); ?>
								</button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php // Hidden .variations container — this is where WC's variations.js
			  // looks for attribute <select> elements. We keep it visually
			  // hidden but still present in the DOM so WC can read/write it.
			  //
			  // NOTE: we do NOT push it off-screen with `left: -10000px` — in
			  // an RTL document that creates a huge phantom scroll area to the
			  // left. The "clip + 1x1" pattern hides it without affecting
			  // document flow or creating overflow. ?>
		<div class="variations etucart-hidden-variations" aria-hidden="true">
			<?php foreach ( $prepared as $attribute_name => $p ) : ?>
				<div class="etucart-hidden-row" data-attribute_name="<?php echo esc_attr( $p['input_name'] ); ?>">
					<label for="<?php echo esc_attr( $p['input_name'] ); ?>"><?php echo esc_html( $p['label'] ); ?></label>
					<select id="<?php echo esc_attr( $p['input_name'] ); ?>"
							class="etucart-hidden-select"
							name="<?php echo esc_attr( $p['input_name'] ); ?>"
							data-attribute_name="<?php echo esc_attr( $p['input_name'] ); ?>">
						<option value=""><?php echo esc_html( sprintf( /* translators: %s: attribute label */ __( 'Choose %s', 'woocommerce' ), $p['label'] ) ); ?></option>
						<?php foreach ( $p['option_items'] as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt['value'] ); ?>" <?php selected( $p['selected'], $opt['value'] ); ?>>
								<?php echo esc_html( $opt['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>
			<?php // WC renders a "Clear" link (.reset_variations) when any
				  // selection is made. We keep a hidden one so the core script
				  // has a target — clicking it resets selects and we mirror. ?>
			<a class="reset_variations" href="#" style="display:none;"><?php esc_html_e( 'Clear', 'woocommerce' ); ?></a>
		</div>

		<?php do_action( 'woocommerce_before_single_variation' ); ?>

		<div class="single_variation_wrap">
			<div class="single_variation etucart-single-variation"></div>

			<?php // Intentionally NOT adding `variations_button` class here: some
				  // themes hide that container with display:none until WC fires
				  // `found_variation`, which can leave our buttons invisible if
				  // anything upstream interferes. Our own JS handles disabled
				  // state via the `single_add_to_cart_button` class below, which
				  // WC still toggles, and we reinforce via event listeners. ?>
			<div class="etucart-actions">
				<div class="etucart-actions__row">
					<button type="submit"
							class="etucart-add-to-cart single_add_to_cart_button button alt disabled wc-variation-selection-needed"
							name="add-to-cart"
							value="<?php echo absint( $product_id ); ?>"
							aria-disabled="true">
						<?php esc_html_e( 'הוספה לעגלה', 'freeman-core' ); ?>
					</button>

					<?php
					woocommerce_quantity_input(
						[
							'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
							'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
							'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( (int) wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(),
							'classes'     => [ 'input-text', 'qty', 'text', 'etucart-qty__input' ],
						]
					);
					?>
				</div>

				<button type="submit"
						class="etucart-buy-now single_add_to_cart_button button disabled wc-variation-selection-needed"
						name="add-to-cart"
						value="<?php echo absint( $product_id ); ?>"
						data-etucart-buy-now="1"
						aria-disabled="true">
					<?php esc_html_e( 'קנה עכשיו', 'freeman-core' ); ?>
				</button>
			</div>

			<input type="hidden" name="product_id" value="<?php echo absint( $product_id ); ?>" />
			<input type="hidden" name="variation_id" class="variation_id" value="0" />
			<input type="hidden" name="<?php echo esc_attr( Etucart_VS_Ajax::BUY_NOW_FIELD ); ?>" class="etucart-buy-now-flag" value="" />
		</div>

		<?php // ------------------------------------------------------------------
			  // Sticky mobile bottom bar: price + primary Buy Now CTA.
			  // Lives inside the form so submit carries every hidden input with
			  // it. Hidden on desktop via CSS, shown on mobile once the main
			  // buy box scrolls out of the viewport (see JS). aria-hidden is
			  // toggled from JS so assistive tech isn't duplicated when it's
			  // off-screen.
			  // ------------------------------------------------------------------ ?>
		<div class="etucart-sticky-bar" aria-hidden="true">
			<div class="etucart-sticky-bar__inner">
				<div class="etucart-sticky-bar__price" aria-live="polite">
					<span class="etucart-sticky-bar__price-value"></span>
				</div>
				<button type="submit"
						class="etucart-sticky-bar__buy etucart-sticky-bar__buy--atc single_add_to_cart_button disabled wc-variation-selection-needed"
						name="add-to-cart"
						value="<?php echo absint( $product_id ); ?>"
						aria-disabled="true">
					<?php esc_html_e( 'הוספה לעגלה', 'freeman-core' ); ?>
				</button>
			</div>
		</div>

		<?php do_action( 'woocommerce_after_single_variation' ); ?>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_variations_form' ); ?>
</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
