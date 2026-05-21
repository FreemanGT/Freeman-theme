<?php
/**
 * Shop Filters — panel template.
 *
 * Server-rendered initial facet tree. The front-end script re-renders this
 * region from the AJAX response on interaction; the markup is kept simple here
 * (styling lands in 6.3b). Expects in scope:
 *   - array $facets : shaped facets[] (see Query_Builder::shape_facets()).
 *   - int   $count  : current filtered product count.
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

/** @var array $facets */
/** @var int $count */
?>
<div class="freeman-sf" data-freeman-sf>
	<?php
	$sf_chips = array();
	foreach ( (array) $facets as $sf_facet ) {
		foreach ( (array) ( $sf_facet['terms'] ?? array() ) as $sf_term ) {
			if ( ! empty( $sf_term['selected'] ) ) {
				$sf_chips[] = array(
					'taxonomy' => (string) $sf_facet['taxonomy'],
					'slug'     => (string) $sf_term['slug'],
					'label'    => (string) $sf_term['label'],
				);
			}
		}
	}
	?>
	<div class="freeman-sf__chips" data-freeman-sf-chips aria-live="polite">
		<?php foreach ( $sf_chips as $sf_chip ) : ?>
			<button
				type="button"
				class="freeman-sf__chip"
				data-freeman-sf-taxonomy="<?php echo esc_attr( $sf_chip['taxonomy'] ); ?>"
				data-freeman-sf-slug="<?php echo esc_attr( $sf_chip['slug'] ); ?>"
			><?php echo esc_html( $sf_chip['label'] ); ?> &times;</button>
		<?php endforeach; ?>
		<?php if ( ! empty( $sf_chips ) ) : ?>
			<button type="button" class="freeman-sf__clear" data-freeman-sf-clear><?php esc_html_e( 'Clear all', 'freeman-core' ); ?></button>
		<?php endif; ?>
	</div>

	<p class="freeman-sf__count" data-freeman-sf-count>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: number of matching products. */
				_n( '%d product', '%d products', (int) $count, 'freeman-core' ),
				(int) $count
			)
		);
		?>
	</p>

	<form class="freeman-sf__facets" data-freeman-sf-facets>
		<?php
		foreach ( (array) $facets as $facet ) {
			include __DIR__ . '/facet-checkbox.php';
		}
		?>
	</form>
</div>
