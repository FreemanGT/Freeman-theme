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
	<div class="freeman-sf__chips" data-freeman-sf-chips aria-live="polite"></div>

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
