<?php
/**
 * Typed value object returned from every `Base_Importer::detect()`.
 *
 * Importers used to return a loose `array{installed, active, file}` which
 * meant a missing key made the Tools page silently go blank. This DTO
 * guarantees the shape at construction time; `Legacy_Importer::scan()`
 * normalises any legacy array returns into one of these and logs a
 * warning when a third-party importer returns something unexpected.
 *
 * Implements `ArrayAccess` + `JsonSerializable` for backward compatibility
 * with code that still reads `$result['installed']`, `$result['active']`,
 * `$result['file']`.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Detection result.
 */
final class Detection_Result implements \ArrayAccess, \JsonSerializable {

	/**
	 * Whether the legacy plugin is installed on disk.
	 *
	 * @var bool
	 */
	public $installed;

	/**
	 * Whether the legacy plugin is currently active.
	 *
	 * @var bool
	 */
	public $active;

	/**
	 * Plugin basename (e.g. 'foo/foo.php').
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Construct.
	 *
	 * @param bool   $installed Installed.
	 * @param bool   $active    Active.
	 * @param string $file      Plugin file.
	 */
	public function __construct( $installed, $active, $file ) {
		$this->installed = (bool) $installed;
		$this->active    = (bool) $active;
		$this->file      = (string) $file;
	}

	/**
	 * Coerce a mixed input into a Detection_Result or null if it can't be.
	 * Accepts an existing instance, or an array with the legacy shape.
	 *
	 * @param mixed $input Input.
	 * @return Detection_Result|null
	 */
	public static function from( $input ) {
		if ( $input instanceof self ) {
			return $input;
		}
		if ( is_array( $input ) && isset( $input['installed'], $input['active'], $input['file'] ) ) {
			return new self( $input['installed'], $input['active'], $input['file'] );
		}
		return null;
	}

	/**
	 * Convert to the legacy array shape (for consumers that still read keys).
	 *
	 * @return array{installed:bool,active:bool,file:string}
	 */
	public function to_array() {
		return array(
			'installed' => $this->installed,
			'active'    => $this->active,
			'file'      => $this->file,
		);
	}

	/* --- ArrayAccess --------------------------------------------------- */

	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ) {
		return in_array( $offset, array( 'installed', 'active', 'file' ), true );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		switch ( $offset ) {
			case 'installed':
				return $this->installed;
			case 'active':
				return $this->active;
			case 'file':
				return $this->file;
		}
		return null;
	}

	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) {
		// Immutable — ignore silently to preserve the invariant.
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ) {
		// Immutable.
	}

	/* --- JsonSerializable --------------------------------------------- */

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return $this->to_array();
	}
}
