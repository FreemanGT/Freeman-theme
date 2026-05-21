<?php
/**
 * Shop Filters — checkbox facet group template.
 *
 * One attribute facet rendered as a fieldset of checkboxes with per-term
 * counts. Expects in scope:
 *   - array $facet : one entry of the shaped facets[] array
 *                    ('taxonomy','label','type','terms'[{slug,label,count,selected}]).
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
<fieldset class="freeman-sf__facet" data-freeman-sf-facet="<?php echo esc_attr( $sf_taxonomy ); ?>">
	<legend class="freeman-sf__facet-title"><?php echo esc_html( (string) ( $facet['label'] ?? $sf_taxonomy ) ); ?></legend>
	<ul class="freeman-sf__terms">
		<?php foreach ( $sf_terms as $sf_term ) : ?>
			<li class="freeman-sf__term">
				<label>
					<input
						type="checkbox"
						class="freeman-sf__checkbox"
						data-freeman-sf-taxonomy="<?php echo esc_attr( $sf_taxonomy ); ?>"
						value="<?php echo esc_attr( (string) $sf_term['slug'] ); ?>"
						<?php checked( ! empty( $sf_term['selected'] ) ); ?>
					/>
					<span class="freeman-sf__term-label"><?php echo esc_html( (string) $sf_term['label'] ); ?></span>
					<span class="freeman-sf__term-count">(<?php echo esc_html( (string) (int) $sf_term['count'] ); ?>)</span>
				</label>
			</li>
		<?php endforeach; ?>
	</ul>
</fieldset>
