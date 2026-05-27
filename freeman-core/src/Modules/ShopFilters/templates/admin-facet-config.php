<?php
/**
 * Shop Filters facet-configuration matrix.
 *
 * Rendered by Admin_Config_Page::render(). Expects:
 *
 * @var array  $rows         Facet rows: taxonomy, label, enabled, order, hide[].
 * @var array  $categories   Ordered category choices: id, name, depth.
 * @var string $action_url   admin-post.php URL.
 * @var string $action_name  admin_post action.
 * @var string $nonce_action Nonce action.
 * @var bool   $saved        Whether the page just saved (success notice).
 *
 * @package FreemanCore
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( ! empty( $saved ) ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Filter configuration saved.', 'freeman-core' ); ?></p></div>
<?php endif; ?>

<p class="description">
	<?php esc_html_e( 'Choose which attribute filters appear, their order, and any categories they should be hidden on. Leave everything as-is to keep the automatic behaviour (every attribute shown, categories as a tree).', 'freeman-core' ); ?>
</p>

<form method="post" action="<?php echo esc_url( $action_url ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( $action_name ); ?>" />
	<?php wp_nonce_field( $nonce_action ); ?>

	<table class="widefat striped" style="max-width:840px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Filter', 'freeman-core' ); ?></th>
				<th><?php esc_html_e( 'Show', 'freeman-core' ); ?></th>
				<th><?php esc_html_e( 'Order', 'freeman-core' ); ?></th>
				<th><?php esc_html_e( 'Hide on categories', 'freeman-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<?php $tax = $row['taxonomy']; ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $row['label'] ); ?></strong><br />
						<code><?php echo esc_html( $tax ); ?></code>
					</td>
					<td>
						<input type="checkbox"
							name="facets[<?php echo esc_attr( $tax ); ?>][enabled]"
							value="1"
							<?php checked( $row['enabled'] ); ?> />
					</td>
					<td>
						<input type="number"
							name="facets[<?php echo esc_attr( $tax ); ?>][order]"
							value="<?php echo esc_attr( (string) $row['order'] ); ?>"
							step="1" style="width:5em;" />
					</td>
					<td>
						<?php if ( empty( $categories ) ) : ?>
							<span class="description"><?php esc_html_e( 'No categories found.', 'freeman-core' ); ?></span>
						<?php else : ?>
							<select name="facets[<?php echo esc_attr( $tax ); ?>][hide_on_categories][]"
								multiple size="5" style="min-width:240px;">
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat['id'] ); ?>"
										<?php selected( in_array( $cat['id'], $row['hide'], true ) ); ?>>
										<?php echo esc_html( str_repeat( "\u{2014} ", (int) $cat['depth'] ) . $cat['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php submit_button( __( 'Save filter configuration', 'freeman-core' ) ); ?>
</form>
<?php if ( ! empty( $categories ) ) : ?>
	<p class="description"><?php esc_html_e( 'Hold Ctrl (Cmd on Mac) to select more than one category to hide a filter on.', 'freeman-core' ); ?></p>
<?php endif; ?>
