<?php
/**
 * Thin logger. Writes to the WP debug log (if WP_DEBUG_LOG is on) and also
 * keeps the last 100 entries in an option so the Dashboard can show them.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Logger.
 */
final class Logger {

	const OPTION   = 'freeman_core_log';
	const MAX_KEEP = 100;

	/**
	 * Write a log line.
	 *
	 * @param string $message Message.
	 * @param string $level   'info' | 'warning' | 'error'.
	 */
	public static function log( $message, $level = 'info' ) {
		$entry = array(
			'time'    => current_time( 'mysql' ),
			'level'   => $level,
			'message' => (string) $message,
		);

		/**
		 * Filters a log entry before it is written.
		 *
		 * Listeners may add or modify fields on the entry array. If a listener
		 * returns a non-array value, the original entry is used (the filter is
		 * for mutation only, not cancellation).
		 *
		 * @since 1.10.13
		 *
		 * @param array  $entry   { 'time' => string, 'level' => string, 'message' => string }.
		 * @param string $message Original message passed to log().
		 * @param string $level   Log level: 'info' | 'warning' | 'error'.
		 */
		$filtered = apply_filters( 'freeman_core/logger/entry', $entry, (string) $message, $level );
		if ( is_array( $filtered ) ) {
			$entry = $filtered;
		}

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[FreemanCore][' . $level . '] ' . $message );
		}

		$log   = get_option( self::OPTION, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = $entry;
		if ( count( $log ) > self::MAX_KEEP ) {
			$log = array_slice( $log, - self::MAX_KEEP );
		}
		update_option( self::OPTION, $log, false );

		/**
		 * Fires after a log entry has been persisted.
		 *
		 * @since 1.10.13
		 *
		 * @param array $entry The entry just written (post-filter).
		 * @param array $log   The full stored log array (post-trim, newest last).
		 */
		do_action( 'freeman_core/logger/written', $entry, $log );
	}

	/**
	 * Retrieve stored log entries (newest last).
	 *
	 * @return array
	 */
	public static function entries() {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Wipe stored log entries.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}
