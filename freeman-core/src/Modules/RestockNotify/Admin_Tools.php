<?php
/**
 * RestockNotify — admin submenu for CSV export.
 *
 * Wave 4.1b. Registers an "Export Subscribers" submenu under the existing
 * `restock-notify` parent menu (registered by the legacy `RSN_Admin` class
 * which Hard Rule #3 forbids editing). Renders a simple form that POSTs
 * to `admin-post.php` with the action handled by `CSV_Exporter`.
 *
 * Gated by `Feature_Flags::is_enabled( 'restock_notify', 'csv_export' )` —
 * the caller (Module::boot) decides whether to register, so flag-OFF
 * means no submenu appears in the admin menu tree.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\RestockNotify;

defined( 'ABSPATH' ) || exit;

/**
 * Submenu page + form render for the subscribers CSV export.
 */
final class Admin_Tools {

	/**
	 * Attach the admin_menu listener.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ) );
	}

	/**
	 * Add the "Export Subscribers" submenu under the restock-notify parent.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'restock-notify',
			__( 'Export Subscribers', 'freeman-core' ),
			__( 'Export Subscribers', 'freeman-core' ),
			'manage_woocommerce',
			'restock-notify-export',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the export form.
	 *
	 * @return void
	 */
	public function render_page() {
		$post_url = admin_url( 'admin-post.php' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export Subscribers', 'freeman-core' ); ?></h1>
			<p><?php esc_html_e( 'Download every restock-notify subscription as a CSV. The file includes all 9 columns, including customer name, email, and the unsubscribe token (privileged — treat the file accordingly).', 'freeman-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( CSV_Exporter::ACTION ); ?>" />
				<?php wp_nonce_field( CSV_Exporter::ACTION ); ?>
				<?php submit_button( __( 'Download CSV', 'freeman-core' ) ); ?>
			</form>
		</div>
		<?php
	}
}
