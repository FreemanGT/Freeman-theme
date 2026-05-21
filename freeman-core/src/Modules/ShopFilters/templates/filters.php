<?php
/**
 * Shop Filters — panel template.
 *
 * Server-rendered filter panel. On desktop it renders inline (sidebar); on
 * mobile the same markup becomes an off-canvas drawer opened by the toggle
 * button — the front-end controller defers navigation until "Apply" on mobile,
 * while desktop navigates on each change (reload transport). Expects in scope:
 *   - array $facets        : shaped facets[] (see Query_Builder::shape_facets()).
 *   - array $category_tree : nested product_cat tree (Category_Tree::build()).
 *   - int   $count         : current filtered product count.
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

use Freeman\Core\Modules\ShopFilters\Labels;

/** @var array $facets */
/** @var array $category_tree */
/** @var int $count */
?>
<div class="freeman-sf" data-freeman-sf>
	<button type="button" class="freeman-sf__toggle" data-freeman-sf-toggle aria-expanded="false" aria-controls="freeman-sf-panel">
		<?php echo esc_html( Labels::get( 'toggle' ) ); ?>
	</button>

	<div class="freeman-sf__overlay" data-freeman-sf-overlay></div>

	<div class="freeman-sf__panel" id="freeman-sf-panel" data-freeman-sf-panel role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( Labels::get( 'panel_aria' ) ); ?>">
		<div class="freeman-sf__panel-head">
			<span class="freeman-sf__panel-title"><?php echo esc_html( Labels::get( 'panel_title' ) ); ?></span>
			<button type="button" class="freeman-sf__close" data-freeman-sf-close aria-label="<?php echo esc_attr( Labels::get( 'close' ) ); ?>">&times;</button>
		</div>

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
				<button type="button" class="freeman-sf__clear" data-freeman-sf-clear><?php echo esc_html( Labels::get( 'clear_all' ) ); ?></button>
			<?php endif; ?>
		</div>

		<p class="freeman-sf__count" data-freeman-sf-count>
			<?php echo esc_html( Labels::count_text( (int) $count ) ); ?>
		</p>

		<?php include __DIR__ . '/facet-category-tree.php'; ?>

		<form class="freeman-sf__facets" data-freeman-sf-facets>
			<?php
			foreach ( (array) $facets as $facet ) {
				if ( 'color' === ( $facet['type'] ?? '' ) ) {
					include __DIR__ . '/facet-color.php';
				} else {
					include __DIR__ . '/facet-checkbox.php';
				}
			}
			?>
		</form>

		<div class="freeman-sf__actions">
			<button type="button" class="freeman-sf__apply" data-freeman-sf-apply><?php echo esc_html( Labels::get( 'apply' ) ); ?></button>
			<button type="button" class="freeman-sf__clear-mobile" data-freeman-sf-clear-mobile><?php echo esc_html( Labels::get( 'clear' ) ); ?></button>
		</div>
	</div>
</div>
