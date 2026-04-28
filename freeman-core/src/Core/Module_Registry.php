<?php
/**
 * Discovers and holds module instances.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Module registry.
 */
final class Module_Registry {

	/**
	 * Map of module_id => Module_Interface.
	 *
	 * @var Module_Interface[]
	 */
	private $modules = array();

	/**
	 * Whether discover() already ran.
	 *
	 * @var bool
	 */
	private $discovered = false;

	/**
	 * Scan `src/Modules/{Name}/Module.php`, instantiate each, keep in map.
	 *
	 * @return Module_Interface[]
	 */
	public function discover() {
		if ( $this->discovered ) {
			return $this->modules;
		}

		$base = FREEMAN_CORE_PATH . 'src/Modules';
		if ( ! is_dir( $base ) ) {
			$this->discovered = true;
			return $this->modules;
		}

		$entries = scandir( $base );
		if ( false === $entries ) {
			$this->discovered = true;
			return $this->modules;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$file = $base . '/' . $entry . '/Module.php';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			$class = 'Freeman\\Core\\Modules\\' . $entry . '\\Module';
			if ( ! class_exists( $class ) ) {
				// Autoloader should handle this, but include as a safety net.
				require_once $file;
			}
			if ( ! class_exists( $class ) ) {
				Logger::log( 'Registry: class not found after include: ' . $class );
				continue;
			}
			try {
				$instance = new $class();
				if ( $instance instanceof Module_Interface ) {
					$this->modules[ $instance->id() ] = $instance;
				}
			} catch ( \Throwable $e ) {
				Logger::log( 'Registry: failed to instantiate ' . $class . ' — ' . $e->getMessage() );
			}
		}

		// Let third parties register their own modules.
		$this->modules = apply_filters( 'freeman_core/modules', $this->modules );

		ksort( $this->modules );

		$this->discovered = true;
		return $this->modules;
	}

	/**
	 * Return all discovered modules.
	 *
	 * @return Module_Interface[]
	 */
	public function all() {
		return $this->discover();
	}

	/**
	 * Get a module by id.
	 *
	 * @param string $id Module id.
	 * @return Module_Interface|null
	 */
	public function get( $id ) {
		$this->discover();
		return isset( $this->modules[ $id ] ) ? $this->modules[ $id ] : null;
	}

	/**
	 * Enable or disable a module in settings.
	 *
	 * @param string $id      Module id.
	 * @param bool   $enabled Desired state.
	 */
	public function set_enabled( $id, $enabled ) {
		$modules        = get_option( 'freeman_core_modules', array() );
		$modules        = is_array( $modules ) ? $modules : array();
		$modules[ $id ] = (bool) $enabled;
		update_option( 'freeman_core_modules', $modules );
	}
}
