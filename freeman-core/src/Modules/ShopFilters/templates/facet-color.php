<?php
/**
 * Shop Filters — colour / image swatch facet template.
 *
 * Renders an attribute facet as a row of swatches instead of a plain checkbox
 * list. Each swatch is a visually-hidden checkbox (so the front-end controller's
 * readSelection() and the reload transport are unchanged) behind a styled label
 * showing the term's image (preferred) or hex colour, falling back to the label
 * text. Swatch data is read from term meta by Query_Builder (decoupled from the
 * VariationSwatches module — decisions §5.7). Expects in scope:
 *   - array $facet : one shaped facets[] entry
 *                    ('taxonomy','label','type','terms'[{slug,label,count,selected,color?,image?}]).
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

/** @var array $facet */
$sf_taxonomy = isset( $facet['taxonomy'] ) ? (string) $facet['taxonomy'] : '';
$sf_terms    = isset( $facet['terms'] ) && is_array( $facet['terms'] ) ? $facet['terms'] : array();
if ( '' === $sf_taxonomy || empty( $sf_terms ) ) {
	return;
}
?>
<fieldset class="freeman-sf__facet freeman-sf__facet--color" data-freeman-sf-facet="<?php echo esc_attr( $sf_taxonomy ); ?>">
	<legend class="freeman-sf__facet-title"><?php echo esc_html( (string) ( $facet['label'] ?? $sf_taxonomy ) ); ?></legend>
	<ul class="freeman-sf__swatches">
		<?php
		foreach ( $sf_terms as $sf_term ) :
			$sf_label = (string) $sf_term['label'];
			$sf_text  = sprintf(
				/* translators: 1: attribute value label, 2: matching product count. */
				_x( '%1$s (%2$d)', 'swatch accessible label', 'freeman-core' ),
				$sf_label,
				(int) $sf_term['count']
			);
			$sf_image = (string) ( $sf_term['image'] ?? '' );
			$sf_color = (string) ( $sf_term['color'] ?? '' );
			?>
			<li class="freeman-sf__swatch">
				<label class="freeman-sf__swatch-label<?php echo ! empty( $sf_term['selected'] ) ? ' is-selected' : ''; ?>" title="<?php echo esc_attr( $sf_text ); ?>">
					<input
						type="checkbox"
						class="freeman-sf__checkbox freeman-sf__swatch-input"
						data-freeman-sf-taxonomy="<?php echo esc_attr( $sf_taxonomy ); ?>"
						value="<?php echo esc_attr( (string) $sf_term['slug'] ); ?>"
						<?php checked( ! empty( $sf_term['selected'] ) ); ?>
					/>
					<?php if ( '' !== $sf_image ) : ?>
						<span class="freeman-sf__swatch-chip" style="background-image:url('<?php echo esc_url( $sf_image ); ?>')"></span>
					<?php elseif ( '' !== $sf_color ) : ?>
						<span class="freeman-sf__swatch-chip" style="background-color:<?php echo esc_attr( $sf_color ); ?>"></span>
					<?php else : ?>
						<span class="freeman-sf__swatch-chip freeman-sf__swatch-chip--text"><?php echo esc_html( $sf_label ); ?></span>
					<?php endif; ?>
					<span class="screen-reader-text"><?php echo esc_html( $sf_text ); ?></span>
				</label>
			</li>
		<?php endforeach; ?>
	</ul>
</fieldset>
