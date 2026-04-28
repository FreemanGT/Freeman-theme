<?php
/**
 * Centralised security helpers. Using these consistently means we can audit
 * one file instead of chasing nonce/cap/escape calls across the codebase.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Security helper.
 */
final class Security {

	/**
	 * Verify a WP nonce or wp_die().
	 *
	 * @param string $action Nonce action.
	 * @param string $field  Request field name (default _wpnonce).
	 */
	public static function verify_nonce( $action, $field = '_wpnonce' ) {
		if ( ! isset( $_REQUEST[ $field ] ) ) {
			wp_die( esc_html__( 'Security token missing.', 'freeman-core' ), 403 );
		}
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'freeman-core' ), 403 );
		}
	}

	/**
	 * Verify an AJAX nonce or respond with JSON error.
	 *
	 * @param string $action Nonce action.
	 * @param string $field  Request field name.
	 */
	public static function verify_ajax_nonce( $action, $field = '_ajax_nonce' ) {
		if ( ! check_ajax_referer( $action, $field, false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'freeman-core' ) ), 403 );
		}
	}

	/**
	 * Require a capability or wp_die().
	 *
	 * @param string $cap Capability name.
	 */
	public static function require_cap( $cap ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'freeman-core' ), 403 );
		}
	}

	/**
	 * Require a capability for AJAX.
	 *
	 * @param string $cap Capability name.
	 */
	public static function require_cap_ajax( $cap ) {
		if ( ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'freeman-core' ) ), 403 );
		}
	}

	/**
	 * Recursive sanitization for nested request arrays. Uses sanitize_text_field
	 * for leaves; use more specialised sanitizers on known fields.
	 *
	 * @param mixed $value Input value.
	 * @return mixed
	 */
	public static function sanitize_recursive( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_recursive' ), $value );
		}
		if ( is_scalar( $value ) ) {
			return sanitize_text_field( wp_unslash( (string) $value ) );
		}
		return '';
	}

	/**
	 * Simple, option-backed per-IP rate limiter for public AJAX endpoints.
	 *
	 * @param string $bucket Human-readable bucket name.
	 * @param int    $max    Max requests per window.
	 * @param int    $window Seconds.
	 * @return bool True if allowed, false if rate-limited.
	 */
	public static function rate_limit( $bucket, $max = 10, $window = 60 ) {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'anon';
		$key  = 'fmrl_' . md5( $bucket . '|' . $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return false;
		}
		set_transient( $key, $hits + 1, $window );
		return true;
	}
}
