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
	 * Option name for a module/feature flag.
	 *
	 * @param string $module  Module slug.
	 * @param string $feature Feature slug.
	 * @return string
	 */
	public static function option_name( $module, $feature ) {
		return 'freeman_core_' . $module . '_' . $feature . '_enabled';
	}

	/**
	 * Whether a module/feature flag is enabled.
	 *
	 * @param string $module  Module slug, e.g. 'sliders'.
	 * @param string $feature Feature slug, e.g. 'advanced_controls'.
	 * @return bool
	 */
	public static function is_enabled( $module, $feature ) {
		$option = self::option_name( $module, $feature );
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

	/**
	 * Whether a flag's effective state is being forced by a code-level filter
	 * on `freeman_core/feature_flag/{module}/{feature}` rather than by the DB
	 * option. Used by the admin Feature Flags page to disable a checkbox the
	 * UI cannot honour.
	 *
	 * @param string $module  Module slug.
	 * @param string $feature Feature slug.
	 * @return bool
	 */
	public static function is_forced_by_filter( $module, $feature ) {
		return has_filter( "freeman_core/feature_flag/{$module}/{$feature}" ) !== false;
	}

	/**
	 * Canonical list of every feature flag the suite ships. Source of truth for
	 * the admin Feature Flags page and the `/docs/feature-flags.md` table.
	 *
	 * Each entry:
	 *   'module'      => string  slug passed to is_enabled()
	 *   'feature'     => string  slug passed to is_enabled()
	 *   'label'       => string  human title
	 *   'description' => string  one-line "what this gates"
	 *   'since'       => string  version the flag was introduced
	 *   'shared'      => bool    true when one flag gates several shipped sub-features
	 *
	 * Adding a flag to the codebase without listing it here is caught by
	 * FeatureFlagsAdminTest::test_registry_covers_every_flag_referenced_in_source().
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function registry() {
		return array(
			array(
				'module'      => 'tools',
				'feature'     => 'settings_import',
				'label'       => __( 'Settings import', 'freeman-core' ),
				'description' => __( 'Adds the Import form under Freeman → Tools. Export, backup listing and restore stay available regardless, so a settings rollback still works after disabling import.', 'freeman-core' ),
				'since'       => '1.10.15',
				'shared'      => false,
			),
			array(
				'module'      => 'variation_swatches',
				'feature'     => 'card_image_swap',
				'label'       => __( 'Variation Swatches — card image swap', 'freeman-core' ),
				'description' => __( 'On shop / archive pages, clicking a swatch swaps the product card image to the matching variation image — no navigation, no Quick View.', 'freeman-core' ),
				'since'       => '1.11.23',
				'shared'      => false,
			),
			array(
				'module'      => 'variation_swatches',
				'feature'     => 'image_swatches',
				'label'       => __( 'Variation Swatches — image swatches', 'freeman-core' ),
				'description' => __( 'Lets each attribute term use an uploaded image instead of a colour; adds the upload UI to the term-edit screen and renders image thumbnails in the shop picker and PDP buy box. Image wins over colour when both are set.', 'freeman-core' ),
				'since'       => '1.11.24',
				'shared'      => false,
			),
			array(
				'module'      => 'variation_swatches',
				'feature'     => 'tooltip',
				'label'       => __( 'Variation Swatches — hover tooltip', 'freeman-core' ),
				'description' => __( 'Shows a CSS hover tooltip on colour and image swatches; the text defaults to the term name and is overridable per term from the term-edit screen.', 'freeman-core' ),
				'since'       => '1.11.25',
				'shared'      => false,
			),
			array(
				'module'      => 'variation_swatches',
				'feature'     => 'auto_color',
				'label'       => __( 'Variation Swatches — auto colour sampler', 'freeman-core' ),
				'description' => __( 'Samples each variation image to a representative hex (cached as post-meta, refreshed on save, pre-warmed via WP-Cron) and uses it as the swatch colour when no manual term colour is set. One toggle drives both the sampler pipeline and the render-path fallback.', 'freeman-core' ),
				'since'       => '1.11.27',
				'shared'      => true,
			),
			array(
				'module'      => 'sliders',
				'feature'     => 'advanced_controls',
				'label'       => __( 'Sliders — advanced controls', 'freeman-core' ),
				'description' => __( 'Adds autoplay, loop and dots / progress-indicator controls to the Category Slider and Product Slider Elementor widgets (on Product Slider, only when display mode is "slider").', 'freeman-core' ),
				'since'       => '1.11.29',
				'shared'      => true,
			),
			array(
				'module'      => 'cheapest_variation',
				'feature'     => 'strategy',
				'label'       => __( 'Cheapest Default Variation — strategy selector', 'freeman-core' ),
				'description' => __( 'Adds a "cheapest / first in stock" choice (global setting, per-product meta override and a filter) for which variation gets pre-selected. Off: the original hardcoded "cheapest" behaviour.', 'freeman-core' ),
				'since'       => '1.11.32',
				'shared'      => false,
			),
			array(
				'module'      => 'infinite_scroll',
				'feature'     => 'trigger_modes',
				'label'       => __( 'Infinite Scroll — trigger modes', 'freeman-core' ),
				'description' => __( 'Adds trigger-mode (observer / load-more button / hybrid), history-mode and container-selector settings, the wrapper render path and its hooks. Off: the original auto-observer behaviour.', 'freeman-core' ),
				'since'       => '1.11.33',
				'shared'      => true,
			),
			array(
				'module'      => 'restock_notify',
				'feature'     => 'csv_export',
				'label'       => __( 'Restock Notify — CSV export', 'freeman-core' ),
				'description' => __( 'Adds an "Export Subscribers" submenu under the Restock Notify menu that streams the full subscriber table as CSV. (The GDPR exporter / eraser is always on — it is a platform contract, not gated by this flag.)', 'freeman-core' ),
				'since'       => '1.11.38',
				'shared'      => false,
			),
			array(
				'module'      => 'variation_swatches',
				'feature'     => 'bundle_compat',
				'label'       => __( 'Variation Swatches — WPC Bundles / FBT compatibility', 'freeman-core' ),
				'description' => __( "Forwards all buy-box form fields to WooCommerce's add-to-cart endpoint so WPC Product Bundles and WPC Frequently-Bought-Together hidden inputs reach the cart; exposes a JS flag for the runtime.", 'freeman-core' ),
				'since'       => '1.11.40',
				'shared'      => false,
			),
			array(
				'module'      => 'shop_filters',
				'feature'     => 'indexer',
				'label'       => __( 'Shop Filters — background indexer', 'freeman-core' ),
				'description' => __( 'Builds and keeps fresh the product attribute / category index that powers Shop Filters (event-driven dirty queue + a 5-minute reconcile sweep) and enables the admin "Reindex now" tool. Off: no index is written and no scheduling occurs. The storefront filter UI is gated separately in a later phase.', 'freeman-core' ),
				'since'       => '1.12.0',
				'shared'      => false,
			),
		);
	}
}
