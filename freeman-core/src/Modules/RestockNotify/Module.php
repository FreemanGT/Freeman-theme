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
		foreach ( $this->option_defaults() as $key => $value ) {
			if ( false === get_option( 'rsn_' . $key, false ) ) {
				update_option( 'rsn_' . $key, $value );
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
		foreach ( array_keys( $this->option_defaults() ) as $key ) {
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

		require_once $dir . 'class-rsn-frontend.php';
		require_once $dir . 'class-rsn-ajax.php';
		require_once $dir . 'class-rsn-email.php';
		require_once $dir . 'class-rsn-stock-monitor.php';

		new \RSN_Frontend();
		new \RSN_Ajax();
		new \RSN_Stock_Monitor();

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
	 * Static accessor for the defaults (used by the legacy function shim).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'auto_inject'            => 'yes',
			'form_heading'           => __( 'עדכנו אותי כשיחזור למלאי', 'freeman-core' ),
			'form_description'       => __( 'השאירו את הפרטים שלכם ונעדכן אתכם ברגע שהמוצר יחזור למלאי.', 'freeman-core' ),
			'form_button_text'       => __( 'הרשמה לעדכון', 'freeman-core' ),
			'form_success_message'   => __( 'נרשמת בהצלחה! נשלח לך מייל כשהמוצר יחזור למלאי.', 'freeman-core' ),
			'form_duplicate_message' => __( 'כבר נרשמת לקבלת עדכון על מוצר זה.', 'freeman-core' ),
			'enable_confirmation'    => 'yes',
			'enable_gdpr'            => 'no',
			'gdpr_text'              => __( 'אני מסכים/ה לקבל התראות במייל על מוצר זה.', 'freeman-core' ),
			'confirm_subject'        => __( 'נרשמת לרשימת ההמתנה!', 'freeman-core' ),
			'confirm_heading'        => __( 'נעדכן אותך', 'freeman-core' ),
			/* translators: {product_name} placeholder is replaced at send time. */
			'confirm_body'           => __( 'נרשמת לרשימת ההמתנה עבור <strong>{product_name}</strong>. נשלח לך מייל ברגע שהמוצר יחזור למלאי.', 'freeman-core' ),
			/* translators: {product_name} placeholder is replaced at send time. */
			'notify_subject'         => __( 'חדשות טובות — {product_name} חזר למלאי!', 'freeman-core' ),
			'notify_heading'         => __( 'המוצר חזר!', 'freeman-core' ),
			/* translators: {product_name} placeholder is replaced at send time. */
			'notify_body'            => __( '<strong>{product_name}</strong> חזר למלאי ומחכה לך. כדאי לתפוס לפני שייגמר שוב!', 'freeman-core' ),
			'notify_button_text'     => __( 'לרכישה', 'freeman-core' ),
			'from_name'              => '',
			'from_email'             => '',
		);
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
