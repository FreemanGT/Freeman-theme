<?php
/**
 * Restock Notify module.
 *
 * Hebrew-first back-in-stock notification system. Customers register for
 * out-of-stock products and get emailed as soon as stock returns. Owns a
 * custom DB table (`{prefix}rsn_subscribers`).
 *
 * Ported from restock-notify v1.2.0. Legacy class bodies are bundled under
 * `legacy/includes/` to preserve behaviour; this Module wires them into the
 * Freeman lifecycle and reuses the same table name so data is preserved when
 * the legacy plugin is deactivated.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\RestockNotify;

use Freeman\Core\Core\Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	/**
	 * The 18 `rsn_*` option keys seeded by `seed_locale_defaults()`.
	 *
	 * Defined as a constant so non-option entries in `locales/<locale>.php`
	 * (e.g. `shell_*` strings consumed by the modern Email class) don't get
	 * accidentally written to the WP options table.
	 *
	 * @since 1.11.4
	 */
	private const OPTION_KEYS = array(
		'auto_inject',
		'form_heading',
		'form_description',
		'form_button_text',
		'form_success_message',
		'form_duplicate_message',
		'enable_confirmation',
		'enable_gdpr',
		'gdpr_text',
		'confirm_subject',
		'confirm_heading',
		'confirm_body',
		'notify_subject',
		'notify_heading',
		'notify_body',
		'notify_button_text',
		'from_name',
		'from_email',
	);

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'restock_notify';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Restock Notify', 'freeman-core' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Customers subscribe to out-of-stock products and are emailed the moment stock returns. Hebrew-first UI, exportable subscriber list.', 'freeman-core' );
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
	 * Settings are rendered by the legacy admin class under its own WP menu
	 * (Restock Notify). Nothing to render via Settings_Hub.
	 *
	 * @return array
	 */
	public function settings_schema() {
		return array();
	}

	/**
	 * The legacy admin class registers its own top-level "Restock Notify" menu
	 * under `admin.php?page=restock-notify`. Wire the dashboard button there.
	 */
	public function legacy_settings_url() {
		return admin_url( 'admin.php?page=restock-notify' );
	}

	/**
	 * Activation — ensure table + seeded defaults.
	 */
	public function on_activate() {
		$this->define_legacy_constants();
		require_once __DIR__ . '/legacy/includes/class-rsn-database.php';
		\RSN_Database::create_tables();
		update_option( 'rsn_db_version', FREEMAN_CORE_VERSION );
		$this->seed_locale_defaults();
	}

	/**
	 * Seed any missing `rsn_*` options from the active locale's defaults.
	 *
	 * Existing values are NOT overwritten (per `/docs/decisions-2026-04-28.md`
	 * §4.2): a Hebrew install that activated under the pre-1.11.2 hardcoded
	 * Hebrew defaults keeps its values untouched even after this method runs
	 * under a different locale.
	 *
	 * Extracted from `on_activate()` so tests can drive the seeding without
	 * spinning up a real `$wpdb` for `RSN_Database::create_tables()`.
	 *
	 * @since 1.11.2
	 */
	public function seed_locale_defaults() {
		$defaults = self::defaults();
		foreach ( self::OPTION_KEYS as $key ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			if ( false === get_option( 'rsn_' . $key, false ) ) {
				update_option( 'rsn_' . $key, $defaults[ $key ] );
			}
		}
	}

	/**
	 * Deactivation — clear cron.
	 */
	public function on_deactivate() {
		wp_clear_scheduled_hook( 'rsn_cleanup_old_entries' );
	}

	/**
	 * Uninstall — remove options but keep the subscriber table by default;
	 * admins can drop it manually if they want to.
	 */
	public function on_uninstall() {
		parent::on_uninstall();
		foreach ( self::OPTION_KEYS as $key ) {
			delete_option( 'rsn_' . $key );
		}
		delete_option( 'rsn_db_version' );
	}

	/**
	 * Boot — load legacy classes and instantiate their components.
	 *
	 * If any of the legacy global classes already exist — because the
	 * original standalone Restock Notify plugin is still active alongside
	 * Freeman Core — skip booting this module and surface an admin notice.
	 * Loading a second class of the same name would fatal the whole site.
	 */
	public function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$conflicts = array_filter(
			array( 'RSN_Frontend', 'RSN_Ajax', 'RSN_Email', 'RSN_Database', 'RSN_Stock_Monitor', 'RSN_Admin' ),
			static function ( $c ) {
				return class_exists( $c, false );
			}
		);
		if ( ! empty( $conflicts ) ) {
			set_transient(
				'freeman_core_restock_conflict',
				array_values( $conflicts ),
				HOUR_IN_SECONDS
			);
			return;
		}

		$this->define_legacy_constants();

		require_once __DIR__ . '/legacy/helpers.php';

		$dir = __DIR__ . '/legacy/includes/';
		require_once $dir . 'class-rsn-database.php';

		$installed = get_option( 'rsn_db_version', '0' );
		if ( version_compare( $installed, FREEMAN_CORE_VERSION, '<' ) ) {
			\RSN_Database::create_tables();
			update_option( 'rsn_db_version', FREEMAN_CORE_VERSION );
		}

		// Modern Email + Stock_Monitor (Wave 2.3b) — alias the legacy global
		// names onto the modern PSR-4 classes so legacy callers
		// (`RSN_Ajax::handle_subscribe()` calling `\RSN_Email::send_confirmation`,
		// `RSN_Admin::handle_actions()` calling `\RSN_Stock_Monitor::manual_notify`)
		// resolve to the modern classes — bilingual-email shell fix and the
		// `freeman_core/restock_notify/email_args` + `before_send` hooks apply
		// universally, not only on the stock-change path.
		//
		// IMPORTANT ordering:
		//  - Aliases happen AFTER the `class_exists(..., false)` conflict check
		//    above, so they don't trip the guard against ourselves.
		//  - The legacy class-rsn-email.php and class-rsn-stock-monitor.php files
		//    are NOT `require_once`'d — loading them would `class RSN_Email {}`
		//    against the alias and fatal.
		class_alias( '\\Freeman\\Core\\Modules\\RestockNotify\\Email',         'RSN_Email' );
		class_alias( '\\Freeman\\Core\\Modules\\RestockNotify\\Stock_Monitor', 'RSN_Stock_Monitor' );

		require_once $dir . 'class-rsn-frontend.php';
		require_once $dir . 'class-rsn-ajax.php';

		new \RSN_Frontend();
		new \RSN_Ajax();
		new \Freeman\Core\Modules\RestockNotify\Stock_Monitor();

		if ( is_admin() ) {
			require_once $dir . 'class-rsn-admin.php';
			new \RSN_Admin();
		}
	}

	/**
	 * Define the legacy constants the bundled classes expect.
	 */
	private function define_legacy_constants() {
		if ( ! defined( 'RSN_VERSION' ) ) {
			define( 'RSN_VERSION', FREEMAN_CORE_VERSION );
		}
		if ( ! defined( 'RSN_PLUGIN_DIR' ) ) {
			define( 'RSN_PLUGIN_DIR', trailingslashit( __DIR__ ) . 'legacy/' );
		}
		if ( ! defined( 'RSN_PLUGIN_URL' ) ) {
			define( 'RSN_PLUGIN_URL', trailingslashit( FREEMAN_CORE_URL . 'src/Modules/RestockNotify' ) );
		}
		if ( ! defined( 'RSN_PLUGIN_BASENAME' ) ) {
			define( 'RSN_PLUGIN_BASENAME', plugin_basename( FREEMAN_CORE_FILE ) );
		}
	}

	/**
	 * Static accessor for the per-locale defaults (used by the legacy
	 * function shim `rsn_option_defaults()`).
	 *
	 * Returns the option-key → default-value map for the requested locale,
	 * loaded from `locales/<locale>.php`. Falls back to `en_US.php` for any
	 * unknown locale. Strings inside the locale files are literals — they
	 * are the per-locale source, not translatable copy.
	 *
	 * Existing callers passing no argument get the active site's locale via
	 * `get_locale()` (or `en_US` if WP isn't loaded).
	 *
	 * @since 1.11.2 Locale-aware. Pre-1.11.2 the method returned hardcoded
	 *               Hebrew defaults regardless of locale.
	 *
	 * @param string|null $locale Locale code, or null to detect via `get_locale()`.
	 * @return array
	 */
	public static function defaults( $locale = null ) {
		if ( null === $locale ) {
			$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		}

		$dir       = __DIR__ . '/locales/';
		$candidate = $dir . $locale . '.php';
		if ( ! is_readable( $candidate ) ) {
			$candidate = $dir . 'en_US.php';
		}

		return (array) require $candidate;
	}

	/**
	 * Instance-level alias.
	 *
	 * @return array
	 */
	private function option_defaults() {
		return self::defaults();
	}
}
