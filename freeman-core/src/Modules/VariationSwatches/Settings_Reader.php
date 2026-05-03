<?php
/**
 * Read-shim for VariationSwatches settings during the 1.11.21 migration to
 * Settings_Hub.
 *
 * The legacy Etucart_VS_Settings static helpers (bool / max_visible /
 * excluded_category_ids) and the one direct get_option() call in
 * class-plugin.php delegate here instead of calling get_option() directly.
 *
 * Behavior depends on the freeman_core_variation_swatches_settings_hub_enabled
 * feature flag (P1 model approved 2026-05-03):
 *
 * - Flag OFF: read the legacy etucart_vs_* key directly. No new-key check.
 *             Avoids stale-new-key shadowing fresh edits made via the still-
 *             active legacy WC settings page.
 * - Flag ON:  prefer the new freeman_core_variation_swatches_* key; fall back
 *             to the legacy key; fall back to caller default.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariationSwatches;

use Freeman\Core\Core\Feature_Flags;

defined( 'ABSPATH' ) || exit;

/**
 * Settings reader.
 */
final class Settings_Reader {

	// Intentionally non-`freeman_*` to stay out of baseline-options-declared.txt;
	// this is an in-memory marker, never persisted.
	const SENTINEL = '__FR_NOT_SET__';

	const LEGACY_PREFIX = 'etucart_vs_';

	const NEW_PREFIX = 'freeman_core_variation_swatches_';

	/**
	 * Read a setting honoring the read-shim contract.
	 *
	 * @param string $legacy_key Full legacy option key (e.g. etucart_vs_shop_enabled).
	 * @param mixed  $default    Caller fallback when neither key is set.
	 * @return mixed
	 */
	public static function get( $legacy_key, $default = null ) {
		if ( ! Feature_Flags::is_enabled( 'variation_swatches', 'settings_hub' ) ) {
			return get_option( $legacy_key, $default );
		}

		$new_key = self::translate( $legacy_key );
		$new_val = get_option( $new_key, self::SENTINEL );
		if ( self::SENTINEL !== $new_val ) {
			return $new_val;
		}

		return get_option( $legacy_key, $default );
	}

	/**
	 * Map a legacy etucart_vs_* key to its freeman_core_variation_swatches_*
	 * counterpart. Public so the migration block in Core\Migrations can reuse
	 * exactly the same translation logic.
	 *
	 * @param string $legacy_key Legacy option key.
	 * @return string
	 */
	public static function translate( $legacy_key ) {
		if ( 0 !== strpos( $legacy_key, self::LEGACY_PREFIX ) ) {
			return $legacy_key;
		}
		return self::NEW_PREFIX . substr( $legacy_key, strlen( self::LEGACY_PREFIX ) );
	}
}
