<?php
/**
 * Feature-flag helper. Reads `freeman_core_<module>_<feature>_enabled` options
 * with explicit boolean parsing so common option-store values
 * ('false', 'no', 'off', 0, '') resolve to false rather than to true via
 * PHP's lax `(bool) 'false'` truthiness.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Feature_Flags.
 */
final class Feature_Flags {

	/**
	 * Whether a module/feature flag is enabled.
	 *
	 * @param string $module  Module slug, e.g. 'sliders'.
	 * @param string $feature Feature slug, e.g. 'advanced_controls'.
	 * @return bool
	 */
	public static function is_enabled( $module, $feature ) {
		$option = 'freeman_core_' . $module . '_' . $feature . '_enabled';
		$raw    = get_option( $option, false );

		// FILTER_VALIDATE_BOOLEAN handles '1'/'0', 'true'/'false', 'yes'/'no',
		// 'on'/'off' explicitly. FILTER_NULL_ON_FAILURE makes garbage strings
		// resolve to null, which we treat as false.
		$parsed  = filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$enabled = ( true === $parsed );

		/**
		 * Filters whether a feature flag is enabled.
		 *
		 * Mirrors the dynamic-name pattern of WordPress's `option_{$option}`:
		 * listeners can target one specific flag without inspecting args.
		 *
		 * @since 1.10.14
		 *
		 * @param bool   $enabled Resolved flag state after option lookup + bool parse.
		 * @param string $module  Module slug passed to is_enabled().
		 * @param string $feature Feature slug passed to is_enabled().
		 */
		return (bool) apply_filters( "freeman_core/feature_flag/{$module}/{$feature}", $enabled, $module, $feature );
	}
}
