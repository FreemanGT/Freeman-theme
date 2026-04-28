<?php
/**
 * Settings export / import / auto-backup. The "rollback path" tool from
 * Wave 0.3 of the roadmap.
 *
 * Export and the backup list are read-only; available regardless of flag state.
 * Import writes options and is gated by Feature_Flags::is_enabled('tools',
 * 'settings_import') (default off). Every import auto-backs up current state
 * AFTER validation, BEFORE first write — a failed/rejected import never
 * consumes a backup slot.
 *
 * Import is best-effort, halt-on-error: writes occur in deterministic
 * key-sorted order, and the loop halts on the first update_option() that
 * returns false. The pre-import auto-backup is the documented recovery path.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Settings_Tools.
 */
final class Settings_Tools {

	const OPTION_BACKUPS    = 'freeman_core_settings_backups';
	const MAX_BACKUPS       = 5;
	const ENVELOPE_VERSION  = 1;
	const NONCE_EXPORT      = 'freeman_core_settings_export';
	const NONCE_IMPORT      = 'freeman_core_settings_import';
	const NONCE_RESTORE     = 'freeman_core_settings_restore';
	const EXPORT_PREFIXES   = array( 'freeman_core_', 'freeman_digital_' );

	/**
	 * Option keys excluded from export. These are runtime state, not config.
	 */
	const EXCLUDED_KEYS = array(
		'freeman_core_log',             // Logger ring buffer.
		'freeman_core_boot_failures',   // Transient-shaped boot diagnostics.
		'freeman_core_settings_backups', // The backup store itself (avoid recursion).
	);

	/**
	 * Register admin handlers.
	 */
	public function boot() {
		add_action( 'admin_post_freeman_settings_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_freeman_settings_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_freeman_settings_restore', array( $this, 'handle_restore' ) );
	}

	// ---------------------------------------------------------------------
	// Core (testable) operations
	// ---------------------------------------------------------------------

	/**
	 * Build the export envelope from current options.
	 *
	 * @return array
	 */
	public function export_payload() {
		$keys    = $this->freeman_option_keys();
		$options = array();
		foreach ( $keys as $k ) {
			if ( in_array( $k, self::EXCLUDED_KEYS, true ) ) {
				continue;
			}
			$options[ $k ] = get_option( $k );
		}
		ksort( $options );

		$envelope = array(
			'version'     => self::ENVELOPE_VERSION,
			'exported_at' => gmdate( 'c' ),
			'site_url'    => function_exists( 'home_url' ) ? home_url() : '',
			'options'     => $options,
		);

		/**
		 * Filters the export envelope's options map. Allows redacting keys.
		 *
		 * @since 1.10.15
		 *
		 * @param array $options Map of option_key => value.
		 * @param array $envelope The full envelope (without filter applied).
		 */
		$envelope['options'] = (array) apply_filters( 'freeman_core/tools/export/options', $envelope['options'], $envelope );

		return $envelope;
	}

	/**
	 * Validate an envelope. Returns ['ok' => bool, 'reason' => string].
	 *
	 * @param mixed $envelope Decoded JSON.
	 * @return array
	 */
	public function validate_envelope( $envelope ) {
		if ( ! is_array( $envelope ) ) {
			return array( 'ok' => false, 'reason' => 'not_an_object' );
		}
		foreach ( array( 'version', 'exported_at', 'options' ) as $required ) {
			if ( ! array_key_exists( $required, $envelope ) ) {
				return array( 'ok' => false, 'reason' => 'missing_field:' . $required );
			}
		}
		if ( (int) $envelope['version'] !== self::ENVELOPE_VERSION ) {
			return array( 'ok' => false, 'reason' => 'unsupported_version:' . $envelope['version'] );
		}
		if ( ! is_array( $envelope['options'] ) ) {
			return array( 'ok' => false, 'reason' => 'options_not_an_object' );
		}
		foreach ( array_keys( $envelope['options'] ) as $k ) {
			if ( ! is_string( $k ) || ! $this->is_allowed_key( $k ) ) {
				return array( 'ok' => false, 'reason' => 'disallowed_key:' . $k );
			}
		}
		return array( 'ok' => true, 'reason' => '' );
	}

	/**
	 * Import an envelope. Validates, auto-backs up, then writes in sorted order.
	 * Halts on first write failure.
	 *
	 * @param array $envelope Decoded envelope.
	 * @return array {
	 *   ok:        bool
	 *   reason:    string  Validation reason, empty on success.
	 *   written:   int     Count of options written.
	 *   total:     int     Total options in envelope.
	 *   failed_at: ?string Option key where write returned false, null if no failure.
	 *   backup_at: ?string ISO timestamp of the auto-backup created, null if none.
	 * }
	 */
	public function import( $envelope ) {
		$check = $this->validate_envelope( $envelope );
		if ( ! $check['ok'] ) {
			Logger::log( 'Settings import rejected: ' . $check['reason'], 'error' );
			return array(
				'ok'        => false,
				'reason'    => $check['reason'],
				'written'   => 0,
				'total'     => 0,
				'failed_at' => null,
				'backup_at' => null,
			);
		}

		$total = count( $envelope['options'] );
		Logger::log( sprintf( 'Settings import starting: %d options from %s', $total, isset( $envelope['site_url'] ) ? $envelope['site_url'] : 'unknown' ), 'info' );

		$skip_backup = (bool) apply_filters( 'freeman_core/tools/import/skip_backup', false, $envelope );
		$backup_at   = null;
		if ( ! $skip_backup ) {
			$backup    = $this->backup_current( 'auto' );
			$backup_at = $backup['exported_at'];
		}

		do_action( 'freeman_core/tools/import/before_write', $envelope );

		$options = $envelope['options'];
		ksort( $options );

		$written = 0;
		foreach ( $options as $k => $v ) {
			Logger::log( 'Settings import: writing ' . $k, 'info' );
			$result = update_option( $k, $v );
			if ( false === $result ) {
				Logger::log( 'Settings import halted: update_option failed at ' . $k, 'error' );
				return array(
					'ok'        => false,
					'reason'    => 'write_failed',
					'written'   => $written,
					'total'     => $total,
					'failed_at' => $k,
					'backup_at' => $backup_at,
				);
			}
			$written++;
		}

		Logger::log( sprintf( 'Settings import complete: %d/%d options written', $written, $total ), 'info' );
		do_action( 'freeman_core/tools/import/after_write', $envelope, array( 'written' => $written, 'total' => $total ) );

		return array(
			'ok'        => true,
			'reason'    => '',
			'written'   => $written,
			'total'     => $total,
			'failed_at' => null,
			'backup_at' => $backup_at,
		);
	}

	/**
	 * Capture current state into the rolling-5 backup store.
	 *
	 * @param string $source 'auto' (default) or 'manual'.
	 * @return array The backup envelope just stored.
	 */
	public function backup_current( $source = 'auto' ) {
		$envelope           = $this->export_payload();
		$envelope['source'] = $source;

		$backups = $this->list_backups();
		array_unshift( $backups, $envelope );
		if ( count( $backups ) > self::MAX_BACKUPS ) {
			$backups = array_slice( $backups, 0, self::MAX_BACKUPS );
		}
		update_option( self::OPTION_BACKUPS, $backups, false );

		return $envelope;
	}

	/**
	 * @return array
	 */
	public function list_backups() {
		$backups = get_option( self::OPTION_BACKUPS, array() );
		return is_array( $backups ) ? $backups : array();
	}

	/**
	 * Restore from a backup index (0 = most recent). Itself triggers a backup of
	 * pre-restore state, so a wrong restore is undoable.
	 *
	 * @param int $index Backup position.
	 * @return array Same shape as import().
	 */
	public function restore( $index ) {
		$backups = $this->list_backups();
		$index   = (int) $index;
		if ( ! isset( $backups[ $index ] ) ) {
			Logger::log( 'Settings restore rejected: no backup at index ' . $index, 'error' );
			return array(
				'ok'        => false,
				'reason'    => 'no_such_backup',
				'written'   => 0,
				'total'     => 0,
				'failed_at' => null,
				'backup_at' => null,
			);
		}
		Logger::log( 'Settings restore: applying backup ' . $backups[ $index ]['exported_at'], 'info' );
		return $this->import( $backups[ $index ] );
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * @param string $key
	 * @return bool
	 */
	private function is_allowed_key( $key ) {
		foreach ( self::EXPORT_PREFIXES as $prefix ) {
			if ( strpos( $key, $prefix ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Enumerate every option key matching our export prefixes.
	 *
	 * @return array
	 */
	private function freeman_option_keys() {
		global $wpdb;

		$out = array();
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_col' ) ) {
			$sql = "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'freeman\\_core\\_%' OR option_name LIKE 'freeman\\_digital\\_%'";
			$res = $wpdb->get_col( $sql );
			if ( is_array( $res ) ) {
				$out = $res;
			}
		}
		return array_values( array_unique( $out ) );
	}

	// ---------------------------------------------------------------------
	// admin_post handlers (thin glue)
	// ---------------------------------------------------------------------

	public function handle_export() {
		Security::require_cap( Settings_Hub::CAP );
		Security::verify_nonce( self::NONCE_EXPORT );

		$envelope = $this->export_payload();
		$json     = wp_json_encode( $envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$filename = 'freeman-settings-' . gmdate( 'Y-m-d-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function handle_import() {
		Security::require_cap( Settings_Hub::CAP );
		Security::verify_nonce( self::NONCE_IMPORT );

		if ( ! Feature_Flags::is_enabled( 'tools', 'settings_import' ) ) {
			wp_die( esc_html__( 'Settings import is disabled.', 'freeman-core' ), 403 );
		}

		$payload = '';
		if ( ! empty( $_FILES['envelope']['tmp_name'] ) && is_uploaded_file( $_FILES['envelope']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$payload = file_get_contents( $_FILES['envelope']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} elseif ( isset( $_POST['envelope_text'] ) ) {
			$payload = wp_unslash( $_POST['envelope_text'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		$envelope = json_decode( (string) $payload, true );
		$result   = $this->import( $envelope );

		set_transient( 'freeman_core_settings_import_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS * 5 );
		wp_safe_redirect( admin_url( 'admin.php?page=freeman-tools' ) );
		exit;
	}

	public function handle_restore() {
		Security::require_cap( Settings_Hub::CAP );
		Security::verify_nonce( self::NONCE_RESTORE );

		$index  = isset( $_POST['backup_index'] ) ? (int) $_POST['backup_index'] : -1;
		$result = $this->restore( $index );

		set_transient( 'freeman_core_settings_import_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS * 5 );
		wp_safe_redirect( admin_url( 'admin.php?page=freeman-tools' ) );
		exit;
	}
}
