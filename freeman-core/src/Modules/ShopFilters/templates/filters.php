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
use Freeman\Core\Modules\ShopFilters\Url_State;
use Freeman\Core\Modules\ShopFilters\Module;

/** @var array $facets */
/** @var array $category_tree */
/** @var array $price */
/** @var string $orderby */
/** @var int $count */

// Effective sort selection: the URL's orderby, else the configured default.
$sf_current_sort = '' !== (string) $orderby ? (string) $orderby : (string) get_option( 'freeman_core_shop_filters_default_sort', '' );
?>
<div class="freeman-sf" data-freeman-sf>
	<button type="button" class="freeman-sf__toggle fm-btn fm-btn--ghost fm-btn--block" data-freeman-sf-toggle aria-expanded="false" aria-controls="freeman-sf-panel">
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
				<button type="button" class="freeman-sf__clear fm-btn fm-btn--link" data-freeman-sf-clear><?php echo esc_html( Labels::get( 'clear_all' ) ); ?></button>
			<?php endif; ?>
		</div>

		<p class="freeman-sf__count" data-freeman-sf-count>
			<?php echo esc_html( Labels::count_text( (int) $count ) ); ?>
		</p>

		<div class="freeman-sf__sort">
			<label class="freeman-sf__sort-label" for="freeman-sf-sort"><?php echo esc_html( Labels::get( 'sort' ) ); ?></label>
			<select id="freeman-sf-sort" class="freeman-sf__sort-select fm-select" data-freeman-sf-sort>
				<?php foreach ( Url_State::orderby_whitelist() as $sf_orderby ) : ?>
					<option value="<?php echo esc_attr( $sf_orderby ); ?>" <?php selected( $sf_current_sort, $sf_orderby ); ?>><?php echo esc_html( Module::orderby_label( $sf_orderby ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php include __DIR__ . '/facet-price.php'; ?>

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
			<button type="button" class="freeman-sf__apply fm-btn" data-freeman-sf-apply><?php echo esc_html( Labels::get( 'apply' ) ); ?></button>
			<button type="button" class="freeman-sf__clear-mobile fm-btn fm-btn--ghost" data-freeman-sf-clear-mobile><?php echo esc_html( Labels::get( 'clear' ) ); ?></button>
		</div>
	</div>
</div>
