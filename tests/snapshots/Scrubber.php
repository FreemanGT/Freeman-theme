<?php
/**
 * Scrubber — replaces volatile content with stable sentinels so snapshots
 * are byte-identical across runs and machines.
 *
 * Use these on the actual output before passing it to assertSnapshotMatches().
 * Each function targets one source of nondeterminism in the codebase:
 * timestamps, nonces, plugin versions, the site URL, and JSON object fields.
 *
 * @package FreemanCore
 */

declare(strict_types=1);

namespace Freeman\Tests\Snapshots;

final class Scrubber {

	const SENTINEL_TIMESTAMP = '<scrubbed:timestamp>';
	const SENTINEL_NONCE     = '<scrubbed:nonce>';
	const SENTINEL_VERSION   = '<scrubbed:version>';
	const SENTINEL_SITE_URL  = '<scrubbed:site_url>';

	/**
	 * Replace ISO-8601 / RFC 3339 timestamps (e.g. 2026-04-29T12:34:56+00:00,
	 * 2026-04-29T12:34:56Z) and `Y-m-d H:i:s` MySQL timestamps.
	 */
	public static function timestamps( string $s ): string {
		// ISO-8601 with timezone or Z.
		$s = preg_replace(
			'/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:?\d{2})/',
			self::SENTINEL_TIMESTAMP,
			$s
		);
		// MySQL "Y-m-d H:i:s" (no T, no zone).
		$s = preg_replace(
			'/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
			self::SENTINEL_TIMESTAMP,
			$s
		);
		return (string) $s;
	}

	/**
	 * Replace WP nonce values inside hidden inputs and URL params.
	 * WP nonces are 10-char hex hashes by default.
	 */
	public static function nonces( string $s ): string {
		// <input type="hidden" name="..._nonce" value="abc1234567"/>
		$s = preg_replace(
			'/(name="[^"]*nonce[^"]*"\s+value=")[a-f0-9]{10,}(")/i',
			'$1' . self::SENTINEL_NONCE . '$2',
			$s
		);
		// _wpnonce=abc1234567 in query strings.
		$s = preg_replace(
			'/(_wpnonce=)[a-f0-9]{10,}/i',
			'$1' . self::SENTINEL_NONCE,
			$s
		);
		return (string) $s;
	}

	/**
	 * Replace semver-ish plugin version strings inside `version="x.y.z"`
	 * attributes (used by ProductFeed XML and similar).
	 */
	public static function versions( string $s ): string {
		$s = preg_replace(
			'/(version=")\d+\.\d+\.\d+(?:[-+][\w.]+)?(")/',
			'$1' . self::SENTINEL_VERSION . '$2',
			$s
		);
		return (string) $s;
	}

	/**
	 * Replace any occurrence of a known site URL with the sentinel.
	 * Pass the URL the bootstrap stubs return (typically https://example.test)
	 * — not a regex.
	 */
	public static function site_url( string $s, string $url ): string {
		if ( '' === $url ) {
			return $s;
		}
		return str_replace( $url, self::SENTINEL_SITE_URL, $s );
	}

	/**
	 * Replace specific keys in a (possibly nested) JSON-decoded array with
	 * a fixed sentinel string. Keys not present are silently skipped.
	 *
	 * @param array<string, mixed> $json     Decoded JSON.
	 * @param string[]             $keys     Keys to scrub at the top level.
	 * @param string               $sentinel Replacement value.
	 * @return array<string, mixed>
	 */
	public static function json_keys( array $json, array $keys, string $sentinel ): array {
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $json ) ) {
				$json[ $k ] = $sentinel;
			}
		}
		return $json;
	}
}
