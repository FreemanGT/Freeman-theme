<?php
/**
 * Shop Filters — category-tree facet template.
 *
 * Renders the pruned, count-rolled-up product_cat hierarchy (req #3) as nested
 * lists of links. Per the locked design decision, a category is NAVIGATION: each
 * node links to that category's archive (get_term_link), not a filter param on
 * the current page. Expects in scope:
 *   - array $category_tree : nested nodes from Category_Tree::build()
 *                            (each: term_id, name, slug, count, children[]).
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;

/** @var array $category_tree */
if ( empty( $category_tree ) ) {
	return;
}

// Self-referencing closure: render a level, recursing into children.
$sf_render_cats = static function ( array $nodes ) use ( &$sf_render_cats ) {
	echo '<ul class="freeman-sf__cat-list">';
	foreach ( $nodes as $sf_node ) {
		$sf_link = get_term_link( (int) $sf_node['term_id'], 'product_cat' );
		$sf_href = is_wp_error( $sf_link ) ? '' : (string) $sf_link;
		echo '<li class="freeman-sf__cat-item">';
		if ( '' !== $sf_href ) {
			printf(
				'<a class="freeman-sf__cat-link" href="%s">%s <span class="freeman-sf__cat-count">(%d)</span></a>',
				esc_url( $sf_href ),
				esc_html( (string) $sf_node['name'] ),
				(int) $sf_node['count']
			);
		} else {
			printf(
				'<span class="freeman-sf__cat-link">%s <span class="freeman-sf__cat-count">(%d)</span></span>',
				esc_html( (string) $sf_node['name'] ),
				(int) $sf_node['count']
			);
		}
		if ( ! empty( $sf_node['children'] ) ) {
			$sf_render_cats( $sf_node['children'] );
		}
		echo '</li>';
	}
	echo '</ul>';
};
?>
<nav class="freeman-sf__categories" aria-label="<?php esc_attr_e( 'Product categories', 'freeman-core' ); ?>">
	<h3 class="freeman-sf__categories-title"><?php esc_html_e( 'Categories', 'freeman-core' ); ?></h3>
	<?php $sf_render_cats( $category_tree ); ?>
</nav>
