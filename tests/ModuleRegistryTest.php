<?php
declare(strict_types=1);

use Freeman\Core\Core\Module_Base;
use Freeman\Core\Core\Module_Interface;
use Freeman\Core\Core\Module_Registry;
use PHPUnit\Framework\TestCase;

/**
 * Smoke-tests the module registry itself: it should discover every
 * Module.php that exists under src/Modules, instantiate it, and return
 * them keyed by id.
 */
final class ModuleRegistryTest extends TestCase {

	public function test_discover_finds_every_module_on_disk(): void {
		$dirs = array_filter(
			(array) glob( FREEMAN_CORE_PATH . 'src/Modules/*/Module.php' ),
			static function ( $f ) { return is_readable( $f ); }
		);
		$expected = count( $dirs );
		$this->assertGreaterThan( 0, $expected, 'at least one Module.php must exist on disk' );

		$registry = new Module_Registry();
		$modules  = $registry->discover();
		$this->assertCount(
			$expected,
			$modules,
			'registry->discover() must return one entry per Module.php'
		);
	}

	public function test_every_discovered_module_implements_the_interface(): void {
		$registry = new Module_Registry();
		foreach ( $registry->discover() as $id => $module ) {
			$this->assertInstanceOf(
				Module_Interface::class,
				$module,
				"Module id='$id' must implement Module_Interface"
			);
			$this->assertInstanceOf(
				Module_Base::class,
				$module,
				"Module id='$id' must extend Module_Base"
			);
		}
	}

	public function test_ids_are_stable_and_non_empty(): void {
		$registry = new Module_Registry();
		$ids      = array_keys( $registry->discover() );
		foreach ( $ids as $id ) {
			$this->assertIsString( $id );
			$this->assertNotEmpty( $id );
			$this->assertMatchesRegularExpression( '/^[a-z0-9_]+$/', $id, "Module id '$id' should be snake_case" );
		}
		// Registry sorts alphabetically — make the ordering explicit.
		$sorted = $ids;
		sort( $sorted );
		$this->assertSame( $sorted, $ids, 'registry should return modules in alphabetical order' );
	}

	public function test_discover_is_idempotent(): void {
		$registry = new Module_Registry();
		$first    = $registry->discover();
		$second   = $registry->discover();
		$this->assertSame(
			array_keys( $first ),
			array_keys( $second ),
			'discover() should be idempotent across calls'
		);
	}
}
