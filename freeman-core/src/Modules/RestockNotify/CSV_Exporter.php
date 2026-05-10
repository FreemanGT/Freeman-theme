<?php
/**
 * RestockNotify — CSV export of the rsn_subscribers table.
 *
 * Wave 4.1b. Pairs with `Admin_Tools` (which renders the submit form on a
 * submenu page) to deliver a downloadable subscribers CSV. Gated behind
 * `Feature_Flags::is_enabled( 'restock_notify', 'csv_export' )` — caller
 * (Module::boot) decides whether to register, so flag-OFF means neither
 * the submenu nor the `admin_post_*` listener exists.
 *
 * Output format (all decided 2026-05-11):
 * - UTF-8 BOM for Excel-friendliness on he_IL stacks
 * - Comma delimiter
 * - 9 columns, labels matching the Wave 4.1a Privacy exporter byte-for-byte
 * - Empty `notified_at` rendered as an empty cell
 * - Filename `restock-notify-subscribers-YYYY-MM-DD.csv`
 *
 * Scale assumption (Flag 1 from 4.1b review): the handler reads all rows
 * into memory via `Subscribers::all()` and builds the full CSV as a string
 * before flushing. Fine at expected merchant scale (≤ low tens of
 * thousands of rows). A streaming variant (LIMIT/OFFSET batches written
 * directly to `php://output`) is deferred until a merchant actually
 * hits the wall.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\RestockNotify;

use Freeman\Core\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * CSV export handler.
 */
final class CSV_Exporter {

	/**
	 * admin-post.php action name. Public so Admin_Tools can reference it
	 * for the hidden form input and nonce action without re-stating the
	 * literal string.
	 */
	public const ACTION = 'freeman_restock_notify_csv_export';

	/**
	 * Attach the admin-post handler.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_export' ) );
	}

	/**
	 * Handle the admin-post submission: cap check → nonce verify → headers
	 * → CSV body → exit.
	 *
	 * Cap check runs BEFORE nonce verification (Flag 3 from 4.1b review).
	 * Failing fast on unauthorized users avoids exposing the nonce action
	 * surface to timing/error responses, which is the WP-standard
	 * convention for admin-post handlers.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export subscribers.', 'freeman-core' ), 403 );
		}
		Security::verify_nonce( self::ACTION );

		$filename = 'restock-notify-subscribers-' . gmdate( 'Y-m-d' ) . '.csv';
		$csv      = $this->build_csv( Subscribers::all() );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV content; field-level escaping handled by fputcsv.
		exit;
	}

	/**
	 * Build the full CSV body as a string.
	 *
	 * Pure function: takes row objects, returns a string. Kept separate
	 * from the HTTP shell so tests can drive it without going through
	 * `wp_die` / `exit`. The pure-string return is intentional for current
	 * scale (Flag 1 (a) — full-table read). If a future wave swaps to
	 * LIMIT/OFFSET streaming, this signature must change to write directly
	 * to a stream rather than returning a string. Flagged here so a
	 * future-you doesn't "fix" the architecture without realizing the
	 * scale assumption is what justifies the shape.
	 *
	 * @param object[] $rows Row objects from `Subscribers::all()`.
	 * @return string Full CSV body including UTF-8 BOM, header row, data rows.
	 */
	public function build_csv( array $rows ): string {
		$headers = array(
			__( 'Subscription ID', 'freeman-core' ),
			__( 'Product ID', 'freeman-core' ),
			__( 'Variation ID', 'freeman-core' ),
			__( 'Customer Name', 'freeman-core' ),
			__( 'Customer Email', 'freeman-core' ),
			__( 'Status', 'freeman-core' ),
			__( 'Subscribed at', 'freeman-core' ),
			__( 'Notified at', 'freeman-core' ),
			__( 'Unsubscribe Token', 'freeman-core' ),
		);

		$fh = fopen( 'php://temp', 'r+' );
		fputcsv( $fh, $headers );
		foreach ( $rows as $row ) {
			fputcsv(
				$fh,
				array(
					(string) ( $row->id ?? '' ),
					(string) ( $row->product_id ?? '' ),
					(string) ( $row->variation_id ?? '' ),
					(string) ( $row->customer_name ?? '' ),
					(string) ( $row->customer_email ?? '' ),
					(string) ( $row->status ?? '' ),
					(string) ( $row->created_at ?? '' ),
					(string) ( $row->notified_at ?? '' ),
					(string) ( $row->unsubscribe_token ?? '' ),
				)
			);
		}
		rewind( $fh );
		$body = stream_get_contents( $fh );
		fclose( $fh );

		return "\xEF\xBB\xBF" . $body;
	}
}
