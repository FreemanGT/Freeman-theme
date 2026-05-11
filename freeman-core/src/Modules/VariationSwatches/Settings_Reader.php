<?php
/**
 * Read-shim for VariationSwatches settings.
 *
 * The legacy `Etucart_VS_Settings` static helpers (bool / max_visible /
 * excluded_category_ids) and the one direct get_option() call in
 * class-plugin.php delegate here instead of calling get_option() directly.
 *
 * As of Wave 2.2 / 4g (1.11.45) the settings are owned by the Freeman →
 * Variation Swatches admin page and stored under
 * `freeman_core_variation_swatches_*`. This reader prefers that key and falls
 * back to the legacy `etucart_vs_*` key (which is never deleted — per the
 * §4.5 zero-downtime decision — so a direct DB write or an old backup restore
 * still resolves). It also converts Settings_Hub's storage shapes (checkbox
 * 1/0 ints, comma-separated category-id strings) back to the legacy helper
 * contract ('yes'/'no', int arrays).
 *
 * Prior to 4g this behaviour was gated behind the
 * `freeman_core_variation_swatches_settings_hub_enabled` flag (flag OFF read
 * legacy directly); that flag is retired and the option is now ignored.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariationSwatches;

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
	 * Legacy keys whose new-namespace counterparts store the value as an
	 * integer / boolean (Settings_Hub's `checkbox` field type sanitizer
	 * normalizes truthy → 1, falsy → 0) but whose legacy reader contract
	 * (`Etucart_VS_Settings::bool()`) compares against the strings 'yes'/'no'.
	 *
	 * **Maintenance contract**: when adding a new `'checkbox'`-typed entry to
	 * {@see \Freeman\Core\Modules\VariationSwatches\Module::settings_schema()},
	 * append its legacy key here. The
	 * `test_every_checkbox_in_settings_schema_is_in_checkbox_keys_list()` test
	 * in `tests/VariationSwatchesSettingsReaderTest.php` fails the build if
	 * the lists drift out of sync, so an omission is caught in CI rather than
	 * silently re-shipping the 1.11.21 bug.
	 *
	 * Same shape concern applies to the `'text'`-typed
	 * `etucart_vs_shop_excluded_categories` (string-stored, array-consumed) —
	 * normalized inline in `normalize_new_value_for_legacy_reader()`. Keep
	 * that mapping in sync if a second array-shaped field is added.
	 */
	private const CHECKBOX_KEYS = array(
		'etucart_vs_shop_enabled',
		'etucart_vs_shop_show_price',
		'etucart_vs_shop_apply_shop',
		'etucart_vs_shop_apply_category',
		'etucart_vs_shop_apply_tag',
		'etucart_vs_shop_apply_search',
		'etucart_vs_shop_apply_related',
		'etucart_vs_pdp_hide_oos',
		'etucart_vs_shop_hide_oos',
		'etucart_vs_shop_no_preselect',
		'etucart_vs_shop_hide_attr_labels',
		'etucart_vs_shop_hide_selected',
	);

	/**
	 * Read a setting: new key first, legacy key as fallback, caller default last.
	 *
	 * @param string $legacy_key Full legacy option key (e.g. etucart_vs_shop_enabled).
	 * @param mixed  $default    Caller fallback when neither key is set.
	 * @return mixed
	 */
	public static function get( $legacy_key, $default = null ) {
		$new_key = self::translate( $legacy_key );
		$new_val = get_option( $new_key, self::SENTINEL );
		if ( self::SENTINEL !== $new_val ) {
			return self::normalize_new_value_for_legacy_reader( $legacy_key, $new_val );
		}

		return get_option( $legacy_key, $default );
	}

	/**
	 * Convert Settings_Hub's storage shapes back to the legacy helper contract.
	 *
	 * @param string $legacy_key Legacy option key.
	 * @param mixed  $value      New-key value.
	 * @return mixed
	 */
	private static function normalize_new_value_for_legacy_reader( $legacy_key, $value ) {
		if ( in_array( $legacy_key, self::CHECKBOX_KEYS, true ) ) {
			$parsed = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( null !== $parsed ) {
				return $parsed ? 'yes' : 'no';
			}
		}

		if ( 'etucart_vs_shop_excluded_categories' === $legacy_key && is_string( $value ) ) {
			if ( '' === trim( $value ) ) {
				return array();
			}

			return array_values(
				array_filter(
					array_map( 'absint', explode( ',', $value ) )
				)
			);
		}

		return $value;
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
