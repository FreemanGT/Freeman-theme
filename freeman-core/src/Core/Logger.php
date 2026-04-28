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
