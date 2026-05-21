<?php
/**
 * Shop Filters — price band facet template.
 *
 * Renders the numeric price facet as OR-checkbox bands (taxonomy key "price",
 * value "min-max" with an empty max for the open-ended top band) so the existing
 * reload controller picks them up like any other facet. Band counts come from
 * wc_product_meta_lookup (see Query_Builder). Expects in scope:
 *   - array $price : shaped price facet, ['bands' => [ ['min','max','count','selected'], … ]].
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

use Freeman\Core\Modules\ShopFilters\Labels;

/** @var array $price */
$sf_bands = isset( $price['bands'] ) && is_array( $price['bands'] ) ? $price['bands'] : array();
if ( empty( $sf_bands ) ) {
	return;
}

$sf_money = static function ( $amount ) {
	return function_exists( 'wc_price' ) ? wc_price( $amount ) : esc_html( number_format_i18n( (float) $amount ) );
};
?>
<fieldset class="freeman-sf__facet freeman-sf__facet--price" data-freeman-sf-facet="price">
	<legend class="freeman-sf__facet-title"><?php echo esc_html( Labels::get( 'price' ) ); ?></legend>
	<ul class="freeman-sf__terms">
		<?php
		foreach ( $sf_bands as $sf_band ) :
			$sf_min = (float) $sf_band['min'];
			$sf_max = isset( $sf_band['max'] ) && null !== $sf_band['max'] ? (float) $sf_band['max'] : null;
			$sf_val = ( $sf_min + 0 ) . '-' . ( null === $sf_max ? '' : ( $sf_max + 0 ) );
			if ( null === $sf_max ) {
				/* translators: %s: lowest price of an open-ended price band. */
				$sf_text = sprintf( _x( '%s+', 'price band, open-ended top', 'freeman-core' ), $sf_money( $sf_min ) );
			} else {
				/* translators: 1: band lower price, 2: band upper price. */
				$sf_text = sprintf( _x( '%1$s – %2$s', 'price band range', 'freeman-core' ), $sf_money( $sf_min ), $sf_money( $sf_max ) );
			}
			?>
			<li class="freeman-sf__term">
				<label>
					<input
						type="checkbox"
						class="freeman-sf__checkbox"
						data-freeman-sf-taxonomy="price"
						value="<?php echo esc_attr( $sf_val ); ?>"
						<?php checked( ! empty( $sf_band['selected'] ) ); ?>
					/>
					<span class="freeman-sf__term-label"><?php echo wp_kses_post( $sf_text ); ?></span>
					<span class="freeman-sf__term-count">(<?php echo esc_html( (string) (int) $sf_band['count'] ); ?>)</span>
				</label>
			</li>
		<?php endforeach; ?>
	</ul>
</fieldset>
