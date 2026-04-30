<?php
/**
 * Variation Swatches module.
 *
 * Replaces the default WooCommerce add-to-cart form on variable products with
 * a modern, RTL/Hebrew-first buy box featuring color swatches, size buttons
 * and quantity stepper. Also adds a compact inline variation picker on shop /
 * archive pages.
 *
 * Ported from etucart-variation-swatches v1.6.6. The legacy class bodies are
 * kept verbatim under `legacy/includes/` to preserve the plugin's mature
 * matching + AJAX logic; this Module is a thin bootstrap that wires them into
 * the Freeman lifecycle.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariationSwatches;

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Core\Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'variation_swatches';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Variation Swatches', 'freeman-core' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Color swatches, size pills, quick-add buy box and shop-grid variation picker for variable products.', 'freeman-core' );
	}

	/**
	 * Dependencies.
	 *
	 * @return array
	 */
	public function dependencies() {
		return array( 'woocommerce' );
	}

	/**
	 * No user-configurable fields — settings live under WC → Settings → Products
	 * (preserved from the legacy plugin so admins recognise them).
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array();
	}

	/**
	 * Swatches settings live in the WC Products settings tab. Point the
	 * Dashboard "Settings" button at it so admins don't have to hunt.
	 */
	public function legacy_settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=products&section=etucart_vs' );
	}

	/**
	 * Deactivation — scrub the per-product transients this module generates
	 * (prepare_product_data() populates `_transient_freeman_vs_pd_*`), so a
	 * disabled module doesn't leave stale picker JSON lying around for the
	 * next re-enable.
	 */
	public function on_deactivate() {
		global $wpdb;
		if ( isset( $wpdb ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$wpdb->esc_like( '_transient_freeman_vs_pd_' ) . '%',
					$wpdb->esc_like( '_transient_timeout_freeman_vs_pd_' ) . '%'
				)
			);
		}
	}

	/**
	 * Boot — define constants the bundled classes expect, require them, and
	 * boot the legacy singleton.
	 *
	 * If the original etucart-variation-swatches plugin is still active, any
	 * of its global classes will already be loaded. Requiring our copy on top
	 * would fatal the whole site, so we bail and record the conflict for the
	 * admin notice instead.
	 */
	public function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$conflicts = array_filter(
			array(
				'Etucart_VS_Plugin',
				'Etucart_VS_Frontend',
				'Etucart_VS_Admin',
				'Etucart_VS_Ajax',
				'Etucart_VS_Settings',
				'Etucart_VS_Archive',
			),
			static function ( $c ) {
				return class_exists( $c, false );
			}
		);
		if ( ! empty( $conflicts ) ) {
			set_transient(
				'freeman_core_swatches_conflict',
				array_values( $conflicts ),
				HOUR_IN_SECONDS
			);
			return;
		}

		$this->define_legacy_constants();
		$this->require_legacy_classes();

		if ( ! defined( 'ETUCART_VS_BOOTED' ) ) {
			define( 'ETUCART_VS_BOOTED', true );
			\Etucart_VS_Plugin::instance()->boot();
		}

		// Feature-flag bridge: expose flag values to the frontend JS bundle
		// before it executes. Runs after legacy register_assets (priority
		// 9999) so the `freeman-core` script handle is already registered.
		add_action( 'wp_enqueue_scripts', array( $this, 'inject_feature_flags' ), 10001 );
	}

	/**
	 * Inject `window.FreemanCoreVSFlags` ahead of the swatches script so the
	 * JS can branch on per-feature flags without editing the legacy bundle.
	 *
	 * @since 1.11.13
	 */
	public function inject_feature_flags() {
		$handle = 'freeman-core';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		/**
		 * Filter the bundle-plugin marker prefixes. When a form contains a
		 * hidden field whose name starts with any of these, our capture-phase
		 * click handler steps aside (no `stopImmediatePropagation`) so the
		 * bundle plugin's own bubble-phase click handler can run.
		 *
		 * Default markers — verified against actual plugin source 2026-04-30:
		 *
		 * - `woobt_` — WPC Frequently Bought Together (wp.org slug
		 *   `woo-bought-together`). The plugin POSTs to its own AJAX endpoint
		 *   `woobt_add_all_to_cart` and packs all bundle items into a single
		 *   `woobt_ids` field. If we intercept, WC's standard add_to_cart
		 *   endpoint can't parse it; the AJAX fails and we fall back to
		 *   native form submit, which navigates to the form `action` URL
		 *   (the product permalink) — that's the "QV opens product page"
		 *   symptom we hit in 1.11.13's first try.
		 *
		 * NOT included:
		 *
		 * - WPC Product Bundles (`woosb-ids-*` with hyphen). That plugin
		 *   relies on WC's standard add_to_cart endpoint server-side; the
		 *   flag-ON `serializeArray()` payload already forwards every
		 *   `woosb-ids-*` field, and the bundle plugin's PHP action hook
		 *   processes them. Stepping aside would force a slow page reload
		 *   via native submit instead. Don't add `woosb-` here.
		 *
		 * @since 1.11.13
		 *
		 * @param string[] $markers List of name-prefix strings.
		 */
		$markers = apply_filters(
			'freeman_core/variation_swatches/bundle_markers',
			array( 'woobt_' )
		);
		$markers = array_values( array_filter( array_map( 'strval', (array) $markers ) ) );

		$flags = array(
			'bundleCompat'  => Feature_Flags::is_enabled( 'variation_swatches', 'bundle_compat' ),
			'bundleMarkers' => $markers,
		);
		wp_add_inline_script(
			$handle,
			'window.FreemanCoreVSFlags = ' . wp_json_encode( $flags ) . ';',
			'before'
		);
	}

	/**
	 * Define legacy constants so the bundled classes resolve their paths
	 * correctly from inside the module.
	 */
	private function define_legacy_constants() {
		if ( ! defined( 'ETUCART_VS_VERSION' ) ) {
			define( 'ETUCART_VS_VERSION', FREEMAN_CORE_VERSION );
		}
		if ( ! defined( 'ETUCART_VS_FILE' ) ) {
			define( 'ETUCART_VS_FILE', __FILE__ );
		}
		if ( ! defined( 'ETUCART_VS_DIR' ) ) {
			// Templates live in legacy/templates/, used via ETUCART_VS_DIR . 'templates/'.
			define( 'ETUCART_VS_DIR', trailingslashit( __DIR__ ) . 'legacy/' );
		}
		if ( ! defined( 'ETUCART_VS_URL' ) ) {
			// Assets live at module root, used via ETUCART_VS_URL . 'assets/…'.
			define( 'ETUCART_VS_URL', trailingslashit( FREEMAN_CORE_URL . 'src/Modules/VariationSwatches' ) );
		}
	}

	/**
	 * Require the legacy class files.
	 */
	private function require_legacy_classes() {
		$dir = __DIR__ . '/legacy/includes/';
		require_once $dir . 'class-plugin.php';
		require_once $dir . 'class-admin.php';
		require_once $dir . 'class-frontend.php';
		require_once $dir . 'class-ajax.php';
		require_once $dir . 'class-settings.php';
		require_once $dir . 'class-archive.php';
	}
}
