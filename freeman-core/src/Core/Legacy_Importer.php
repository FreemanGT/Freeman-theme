<?php
/**
 * Imports data from the seven legacy plugins into Freeman Core.
 *
 * Each module ships an `Importer` class (in the same namespace as its Module);
 * the importer exposes:
 *   - detect()                : returns array{ installed:bool, active:bool, file:string }
 *   - import()                : returns array{ ok:bool,        message:string }
 *   - delete_legacy_options() : void (optional, used when scrubbing data)
 *
 * Detection is idempotent and non-destructive. Importing writes option values;
 * deletion of legacy plugin files happens only via an explicit admin action.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy importer.
 */
final class Legacy_Importer {

	const IMPORTED_OPTION = 'freeman_core_legacy_imported';
	const NONCE_IMPORT    = 'freeman_legacy_import';
	const NONCE_DELETE    = 'freeman_legacy_delete';

	/**
	 * Registry.
	 *
	 * @var Module_Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Module_Registry $registry Registry.
	 */
	public function __construct( Module_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Register admin-post hooks.
	 */
	public function boot() {
		add_action( 'admin_post_freeman_legacy_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_freeman_legacy_delete', array( $this, 'handle_delete' ) );
	}

	/**
	 * Scan every module for an Importer and collect detection results.
	 *
	 * @return array<string, array{module_label:string,installed:bool,active:bool,file:string,has_importer:bool}>
	 */
	public function scan() {
		$out = array();
		foreach ( $this->registry->all() as $module ) {
			$importer_class = $this->importer_class_for( $module );
			$entry          = array(
				'module_label' => $module->label(),
				'installed'    => false,
				'active'       => false,
				'file'         => '',
				'has_importer' => (bool) $importer_class,
			);
			if ( $importer_class ) {
				try {
					$importer = new $importer_class();
					$raw      = $importer->detect();
					$result   = Detection_Result::from( $raw );
					if ( null === $result ) {
						Logger::log(
							'Legacy_Importer: ' . $importer_class . '::detect() returned an invalid shape — expected Detection_Result or array{installed,active,file}',
							'warning'
						);
					} else {
						$entry['installed'] = $result->installed;
						$entry['active']    = $result->active;
						$entry['file']      = $result->file;
					}
				} catch ( \Throwable $e ) {
					Logger::log( 'Legacy_Importer scan failed for ' . $module->id() . ': ' . $e->getMessage(), 'error' );
				}
			}
			$out[ $module->id() ] = $entry;
		}
		return $out;
	}

	/**
	 * Imported-status lookup keyed by module id.
	 *
	 * @return array<string, array{at:string,message:string}>
	 */
	public function imported() {
		$stored = get_option( self::IMPORTED_OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Handle the "Import legacy plugins" form submit.
	 */
	public function handle_import() {
		Security::verify_nonce( self::NONCE_IMPORT );
		Security::require_cap( 'manage_options' );

		$imported = $this->imported();

		foreach ( $this->registry->all() as $module ) {
			$importer_class = $this->importer_class_for( $module );
			if ( ! $importer_class ) {
				continue;
			}
			try {
				$importer = new $importer_class();
				$result   = $importer->import();
				if ( is_array( $result ) && ! empty( $result['ok'] ) ) {
					$imported[ $module->id() ] = array(
						'at'      => current_time( 'mysql' ),
						'message' => isset( $result['message'] ) ? (string) $result['message'] : '',
					);
				}
			} catch ( \Throwable $e ) {
				Logger::log( 'Legacy_Importer import failed for ' . $module->id() . ': ' . $e->getMessage(), 'error' );
			}
		}

		update_option( self::IMPORTED_OPTION, $imported, false );

		set_transient( 'freeman_core_import_done_' . get_current_user_id(), 1, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=freeman-tools' ) );
		exit;
	}

	/**
	 * Deactivate + delete the legacy plugin files once the user confirms.
	 */
	public function handle_delete() {
		Security::verify_nonce( self::NONCE_DELETE );
		Security::require_cap( 'delete_plugins' );

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Let each importer scrub its own legacy options first.
		foreach ( $this->registry->all() as $module ) {
			$importer_class = $this->importer_class_for( $module );
			if ( ! $importer_class ) {
				continue;
			}
			try {
				$importer = new $importer_class();
				if ( is_callable( array( $importer, 'delete_legacy_options' ) ) ) {
					$importer->delete_legacy_options();
				}
			} catch ( \Throwable $e ) {
				Logger::log( 'Legacy_Importer scrub failed for ' . $module->id() . ': ' . $e->getMessage(), 'error' );
			}
		}

		$installed = get_plugins();
		$to_remove = array();
		foreach ( $this->legacy_plugin_slugs() as $slug ) {
			if ( isset( $installed[ $slug ] ) ) {
				$to_remove[] = $slug;
			}
		}

		if ( ! empty( $to_remove ) ) {
			deactivate_plugins( $to_remove );
			delete_plugins( $to_remove );
		}

		set_transient( 'freeman_core_delete_done_' . get_current_user_id(), 1, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=freeman-tools' ) );
		exit;
	}

	/**
	 * Canonical list of legacy plugin file slugs.
	 *
	 * @return string[]
	 */
	public function legacy_plugin_slugs() {
		$slugs = array();
		foreach ( $this->registry->all() as $module ) {
			$importer_class = $this->importer_class_for( $module );
			if ( $importer_class && defined( $importer_class . '::LEGACY_PLUGIN_FILE' ) ) {
				$slug = constant( $importer_class . '::LEGACY_PLUGIN_FILE' );
				if ( is_string( $slug ) && '' !== $slug ) {
					$slugs[] = $slug;
				}
			}
		}
		// Include the mu-plugin-style cheapest snippet (shipped as a standalone .php).
		$slugs[] = 'auto-default-cheapest-variation/auto-default-cheapest-variation.php';

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Resolve the Importer FQCN for a module, if present.
	 *
	 * @param Module_Interface $module Module.
	 * @return string|null
	 */
	private function importer_class_for( $module ) {
		$ref   = new \ReflectionClass( $module );
		$class = $ref->getNamespaceName() . '\\Importer';
		return class_exists( $class ) ? $class : null;
	}
}
