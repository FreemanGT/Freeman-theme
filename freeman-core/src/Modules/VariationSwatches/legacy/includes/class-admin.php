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

		// Wave 2.2 / 4b (1.11.24) — image-swatch upload UI is gated behind the
		// image_swatches feature flag. When OFF, we skip the wp.media() enqueue
		// and the inline script entirely, so the term-edit screen looks identical
		// to pre-1.11.24.
		if ( ! \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'image_swatches' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script(
			'wp-color-picker',
			$this->image_uploader_inline_script()
		);
	}

	/**
	 * Inline JS that wires the .etucart-image-field UI to wp.media().
	 *
	 * One frame instance is reused across all open/cancel cycles. The hidden
	 * input stores the attachment ID; the preview <img> mirrors the URL.
	 * Idempotent — safe to run on multiple field instances.
	 */
	private function image_uploader_inline_script(): string {
		return <<<'JS'
jQuery(function($) {
	var frame = null;
	$(document).on('click', '.etucart-image-field-pick', function(e) {
		e.preventDefault();
		var $btn  = $(this);
		var $row  = $btn.closest('.etucart-image-field-wrap');
		var $hid  = $row.find('.etucart-image-field-id');
		var $prev = $row.find('.etucart-image-field-preview');
		if (frame) { frame.off('select'); } else {
			frame = wp.media({ title: $btn.data('frame-title') || 'Choose swatch image', button: { text: $btn.data('frame-button') || 'Use this image' }, multiple: false });
		}
		frame.on('select', function() {
			var att = frame.state().get('selection').first().toJSON();
			$hid.val(att.id);
			var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
			$prev.attr('src', url).show();
			$row.find('.etucart-image-field-clear').show();
		});
		frame.open();
	});
	$(document).on('click', '.etucart-image-field-clear', function(e) {
		e.preventDefault();
		var $row  = $(this).closest('.etucart-image-field-wrap');
		$row.find('.etucart-image-field-id').val('0');
		$row.find('.etucart-image-field-preview').attr('src', '').hide();
		$(this).hide();
	});
});
JS;
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
		<?php $this->render_image_field( 0 ); ?>
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
		<?php $this->render_image_field( $term->term_id ); ?>
		<?php
	}

	/**
	 * Wave 2.2 / 4b (1.11.24) — image-swatch upload field.
	 *
	 * Rendered on both the Add-term and Edit-term screens. Gated behind the
	 * image_swatches feature flag — when off, returns nothing so the term
	 * screen looks identical to pre-1.11.24. The image wins over color in
	 * rendering when both are set; color is the fallback.
	 *
	 * @param int $term_id 0 on the Add-term screen; populated on Edit-term.
	 */
	private function render_image_field( int $term_id ): void {
		if ( ! \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'image_swatches' ) ) {
			return;
		}

		$attachment_id = $term_id > 0 ? Etucart_VS_Plugin::term_image_id( $term_id ) : 0;
		$preview_url   = $term_id > 0 ? Etucart_VS_Plugin::term_image_url( $term_id, 'thumbnail' ) : '';
		$has_image     = $attachment_id > 0 && '' !== $preview_url;

		// On the Add-term screen WP wraps fields in `.form-field`; on Edit-term
		// each field is its own table row. Detect by term_id presence.
		$is_edit_screen = $term_id > 0;

		if ( $is_edit_screen ) :
			?>
			<tr class="form-field term-etucart-image-wrap">
				<th scope="row"><label for="etucart_swatch_image_id"><?php esc_html_e( 'Swatch image', 'freeman-core' ); ?></label></th>
				<td class="etucart-image-field-wrap">
					<?php $this->render_image_field_inner( $attachment_id, $preview_url, $has_image ); ?>
				</td>
			</tr>
			<?php
		else :
			?>
			<div class="form-field term-etucart-image-wrap etucart-image-field-wrap">
				<label for="etucart_swatch_image_id"><?php esc_html_e( 'Swatch image', 'freeman-core' ); ?></label>
				<?php $this->render_image_field_inner( $attachment_id, $preview_url, $has_image ); ?>
			</div>
			<?php
		endif;
	}

	private function render_image_field_inner( int $attachment_id, string $preview_url, bool $has_image ): void {
		?>
		<input
			type="hidden"
			name="etucart_swatch_image_id"
			id="etucart_swatch_image_id"
			class="etucart-image-field-id"
			value="<?php echo esc_attr( (string) $attachment_id ); ?>"
		/>
		<img
			class="etucart-image-field-preview"
			src="<?php echo esc_url( $preview_url ); ?>"
			alt=""
			style="display:<?php echo $has_image ? 'inline-block' : 'none'; ?>;width:48px;height:48px;border-radius:50%;border:1px solid #d0d0d0;vertical-align:middle;object-fit:cover;margin-inline-end:8px;"
		/>
		<button type="button" class="button etucart-image-field-pick"
			data-frame-title="<?php esc_attr_e( 'Choose swatch image', 'freeman-core' ); ?>"
			data-frame-button="<?php esc_attr_e( 'Use this image', 'freeman-core' ); ?>">
			<?php esc_html_e( 'Choose image', 'freeman-core' ); ?>
		</button>
		<a href="#" class="etucart-image-field-clear" style="display:<?php echo $has_image ? 'inline' : 'none'; ?>;margin-inline-start:8px;">
			<?php esc_html_e( 'Remove image', 'freeman-core' ); ?>
		</a>
		<p class="description">
			<?php esc_html_e( 'Upload an image to use this term as a picture-swatch. When set, image takes precedence over color.', 'freeman-core' ); ?>
		</p>
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

		// Wave 2.2 / 4b (1.11.24) — image-swatch attachment ID. Gated: when
		// the flag is off, the field isn't rendered, so $_POST won't carry
		// the key. Defence-in-depth: also bail explicitly if the flag is off
		// so a forged POST can't write image-meta on a flag-OFF site.
		if ( \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'image_swatches' )
			&& isset( $_POST['etucart_swatch_image_id'] )
		) {
			$image_key     = Etucart_VS_Plugin::image_meta_key();
			$attachment_id = absint( wp_unslash( $_POST['etucart_swatch_image_id'] ) );
			if ( $attachment_id > 0 && ( ! function_exists( 'get_post_type' ) || 'attachment' === get_post_type( $attachment_id ) ) ) {
				update_term_meta( $term_id, $image_key, $attachment_id );
			} else {
				delete_term_meta( $term_id, $image_key );
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

		// Wave 2.2 / 4b (1.11.24) — when the image flag is on and the term has
		// an image, prefer the image preview over the color swatch in the
		// term-list column (mirrors the render-time precedence: image wins).
		if ( \Freeman\Core\Core\Feature_Flags::is_enabled( 'variation_swatches', 'image_swatches' ) ) {
			$image_url = Etucart_VS_Plugin::term_image_url( $term_id, 'thumbnail' );
			if ( '' !== $image_url ) {
				return sprintf(
					'<span style="display:inline-block;width:22px;height:22px;border-radius:50%%;border:1px solid #d0d0d0;background-image:url(%1$s);background-size:cover;background-position:center;vertical-align:middle;"></span>',
					esc_url( $image_url )
				);
			}
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
