<?php
/**
 * Admin: adds a hex color-picker field to every product attribute term
 * (taxonomies beginning with "pa_"). The stored hex is read by the frontend
 * to render color swatches.
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Etucart_VS_Admin' ) ) :

class Etucart_VS_Admin {

	public function register(): void {
		add_action( 'admin_init', [ $this, 'hook_attribute_taxonomies' ] );
	}

	/**
	 * Hook add/edit forms for every registered product attribute taxonomy.
	 */
	public function hook_attribute_taxonomies(): void {
		if ( ! function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
			return;
		}

		$taxonomies = wc_get_attribute_taxonomy_names();
		if ( empty( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", [ $this, 'render_add_field' ] );
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'render_edit_field' ], 10, 2 );
			add_action( "created_{$taxonomy}", [ $this, 'save_field' ] );
			add_action( "edited_{$taxonomy}", [ $this, 'save_field' ] );

			add_filter( "manage_edit-{$taxonomy}_columns", [ $this, 'register_column' ] );
			add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'render_column' ], 10, 3 );
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_color_picker' ] );
	}

	public function enqueue_color_picker( string $hook ): void {
		// Only load on term edit screens.
		if ( ! in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery(function($){ $(".etucart-color-field").wpColorPicker(); });'
		);
	}

	public function render_add_field(): void {
		?>
		<div class="form-field term-etucart-color-wrap">
			<label for="etucart_swatch_color"><?php esc_html_e( 'Swatch color', 'freeman-core' ); ?></label>
			<input type="text" name="etucart_swatch_color" id="etucart_swatch_color" value="" class="etucart-color-field" />
			<p class="description">
				<?php esc_html_e( 'Set a color here and this term will render as a color circle on the product page. Leave empty to render as a text button.', 'freeman-core' ); ?>
			</p>
		</div>
		<?php
	}

	public function render_edit_field( WP_Term $term, string $taxonomy ): void {
		$value = Etucart_VS_Plugin::term_color( $term->term_id );
		?>
		<tr class="form-field term-etucart-color-wrap">
			<th scope="row"><label for="etucart_swatch_color"><?php esc_html_e( 'Swatch color', 'freeman-core' ); ?></label></th>
			<td>
				<input type="text" name="etucart_swatch_color" id="etucart_swatch_color" value="<?php echo esc_attr( $value ); ?>" class="etucart-color-field" />
				<p class="description">
					<?php esc_html_e( 'Set a color here and this term will render as a color circle on the product page. Leave empty to render as a text button.', 'freeman-core' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	public function save_field( int $term_id ): void {
		// Cap check — product attribute terms use manage_product_terms in WC.
		if ( ! current_user_can( 'manage_product_terms', $term_id ) ) {
			return;
		}

		// Defence-in-depth: WP already verifies the nonce on the edit-tags /
		// term.php screens before calling the {created,edited}_{$taxonomy}
		// hooks, but we verify again explicitly so direct-hook invocation
		// can't write term meta without a signed request.
		$is_update = isset( $_POST['action'] ) && 'editedtag' === $_POST['action'];
		if ( $is_update ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-tag_' . $term_id ) ) {
				return;
			}
		} else {
			if ( ! isset( $_POST['_wpnonce_add-tag'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_add-tag'] ) ), 'add-tag' ) ) {
				// In some WP versions the add-term nonce is named differently; bail if missing.
				if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'add-tag' ) ) {
					return;
				}
			}
		}

		$key = Etucart_VS_Plugin::color_meta_key();
		if ( isset( $_POST['etucart_swatch_color'] ) ) {
			$raw = wp_unslash( $_POST['etucart_swatch_color'] );
			$hex = $this->sanitize_hex( (string) $raw );
			if ( '' === $hex ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $hex );
			}
		}
	}

	private function sanitize_hex( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#?([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value, $m ) ) {
			return '#' . strtoupper( $m[1] );
		}
		return '';
	}

	public function register_column( array $columns ): array {
		$columns['etucart_color'] = esc_html__( 'Swatch', 'freeman-core' );
		return $columns;
	}

	public function render_column( string $content, string $column_name, int $term_id ): string {
		if ( 'etucart_color' !== $column_name ) {
			return $content;
		}
		$hex = Etucart_VS_Plugin::term_color( $term_id );
		if ( '' === $hex ) {
			return '<span style="color:#bbb;">&mdash;</span>';
		}
		return sprintf(
			'<span title="%1$s" style="display:inline-block;width:22px;height:22px;border-radius:50%%;border:1px solid #d0d0d0;background:%1$s;vertical-align:middle;"></span>',
			esc_attr( $hex )
		);
	}
}

endif; // class_exists Etucart_VS_Admin
