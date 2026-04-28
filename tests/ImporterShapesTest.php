<?php
declare(strict_types=1);

use Freeman\Core\Core\Base_Importer;
use Freeman\Core\Core\Detection_Result;
use PHPUnit\Framework\TestCase;

/**
 * Every concrete Importer under src/Modules/<Name>/Importer.php must:
 *   - extend Base_Importer
 *   - declare a non-empty LEGACY_PLUGIN_FILE constant
 *   - return a Detection_Result (or an array that coerces to one) from detect()
 */
final class ImporterShapesTest extends TestCase {

	/** @return array<int, array{0:string}> */
	public static function importer_provider(): array {
		$out   = array();
		$files = glob( FREEMAN_CORE_PATH . 'src/Modules/*/Importer.php' );
		foreach ( (array) $files as $f ) {
			$dir   = basename( dirname( $f ) );
			$class = 'Freeman\\Core\\Modules\\' . $dir . '\\Importer';
			$out[] = array( $class );
		}
		return $out;
	}

	/** @dataProvider importer_provider */
	public function test_extends_base_importer( string $class ): void {
		$this->assertTrue( class_exists( $class ), "$class should be autoloadable" );
		$this->assertTrue( is_subclass_of( $class, Base_Importer::class ), "$class should extend Base_Importer" );
	}

	/** @dataProvider importer_provider */
	public function test_has_legacy_plugin_file( string $class ): void {
		$this->assertTrue(
			defined( $class . '::LEGACY_PLUGIN_FILE' ),
			"$class must define LEGACY_PLUGIN_FILE"
		);
		$file = constant( $class . '::LEGACY_PLUGIN_FILE' );
		$this->assertIsString( $file );
		$this->assertNotEmpty( $file );
	}

	/** @dataProvider importer_provider */
	public function test_detect_returns_typed_result( string $class ): void {
		$i      = new $class();
		$result = $i->detect();
		$dto    = Detection_Result::from( $result );
		$this->assertInstanceOf(
			Detection_Result::class,
			$dto,
			"$class::detect() must return Detection_Result (or a coercible legacy array)"
		);
	}

	/** @dataProvider importer_provider */
	public function test_import_returns_valid_shape( string $class ): void {
		$i      = new $class();
		$result = $i->import();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'ok', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertIsBool( $result['ok'] );
	}
}
