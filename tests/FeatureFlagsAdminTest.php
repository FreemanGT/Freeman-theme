<?php
declare(strict_types=1);

use Freeman\Core\Core\Feature_Flags;
use Freeman\Core\Core\Settings_Hub;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freeman\Core\Core\Feature_Flags
 * @covers \Freeman\Core\Core\Settings_Hub
 */
final class FeatureFlagsAdminTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	/** Build "module/feature" => true from the registry. */
	private function registry_keys(): array {
		$keys = array();
		foreach ( Feature_Flags::registry() as $flag ) {
			$keys[ $flag['module'] . '/' . $flag['feature'] ] = true;
		}
		return $keys;
	}

	/** Scan freeman-core/src for is_enabled( 'module', 'feature' ) call sites. */
	private function source_referenced_keys(): array {
		$root  = realpath( __DIR__ . '/../freeman-core/src' );
		$this->assertNotFalse( $root, 'freeman-core/src must exist' );

		$found = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
				continue;
			}
			// Migrations may read a *retired* flag's option to decide an
			// upgrade path (e.g. the Wave 4g re-sync checks the retired
			// settings_hub flag); those are intentionally absent from the
			// registry, so don't treat a Migrations.php hit as a missing entry.
			if ( false !== strpos( str_replace( '\\', '/', $file->getPathname() ), 'Core/Migrations.php' ) ) {
				continue;
			}
			$src = (string) file_get_contents( $file->getPathname() );
			if ( preg_match_all( "/is_enabled\\(\\s*'([a-z0-9_]+)'\\s*,\\s*'([a-z0-9_]+)'\\s*\\)/", $src, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $hit ) {
					$found[ $hit[1] . '/' . $hit[2] ] = true;
				}
			}
		}
		return $found;
	}

	public function test_registry_covers_every_flag_referenced_in_source(): void {
		$registry = $this->registry_keys();
		foreach ( array_keys( $this->source_referenced_keys() ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$registry,
				"Feature_Flags::is_enabled( '{$key}' ) is called in src/ but the flag is missing from Feature_Flags::registry() — add it so the admin Feature Flags page lists it."
			);
		}
	}

	public function test_registry_has_no_stale_entries(): void {
		$referenced = $this->source_referenced_keys();
		foreach ( array_keys( $this->registry_keys() ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$referenced,
				"Feature_Flags::registry() lists '{$key}' but nothing in src/ calls is_enabled() for it — remove the stale registry entry."
			);
		}
	}

	public function test_registry_entries_are_well_formed(): void {
		foreach ( Feature_Flags::registry() as $i => $flag ) {
			foreach ( array( 'module', 'feature', 'label', 'description', 'since' ) as $field ) {
				$this->assertArrayHasKey( $field, $flag, "entry #{$i} missing '{$field}'" );
				$this->assertIsString( $flag[ $field ], "entry #{$i} field '{$field}' must be a string" );
				$this->assertNotSame( '', trim( $flag[ $field ] ), "entry #{$i} field '{$field}' must be non-empty" );
			}
			$this->assertArrayHasKey( 'shared', $flag, "entry #{$i} missing 'shared'" );
			$this->assertIsBool( $flag['shared'], "entry #{$i} field 'shared' must be a bool" );
		}
	}

	public function test_registry_has_no_duplicate_flags(): void {
		$seen = array();
		foreach ( Feature_Flags::registry() as $flag ) {
			$key = $flag['module'] . '/' . $flag['feature'];
			$this->assertArrayNotHasKey( $key, $seen, "duplicate registry entry for '{$key}'" );
			$seen[ $key ] = true;
		}
	}

	public function test_option_name_format(): void {
		$this->assertSame(
			'freeman_core_sliders_advanced_controls_enabled',
			Feature_Flags::option_name( 'sliders', 'advanced_controls' )
		);
		// Every registry entry's computed option name follows the convention.
		foreach ( Feature_Flags::registry() as $flag ) {
			$this->assertSame(
				'freeman_core_' . $flag['module'] . '_' . $flag['feature'] . '_enabled',
				Feature_Flags::option_name( $flag['module'], $flag['feature'] )
			);
		}
	}

	public function test_is_forced_by_filter_false_when_no_filter(): void {
		$this->assertFalse( Feature_Flags::is_forced_by_filter( 'sliders', 'advanced_controls' ) );
	}

	public function test_is_forced_by_filter_true_when_filter_registered(): void {
		add_filter( 'freeman_core/feature_flag/sliders/advanced_controls', '__return_true' );
		$this->assertTrue( Feature_Flags::is_forced_by_filter( 'sliders', 'advanced_controls' ) );
		// A filter on a different flag does not bleed over.
		$this->assertFalse( Feature_Flags::is_forced_by_filter( 'infinite_scroll', 'trigger_modes' ) );
	}

	public function test_hub_registers_save_feature_flags_handler(): void {
		$hub = new Settings_Hub( new \Freeman\Core\Core\Module_Registry() );
		$hub->boot();
		$this->assertArrayHasKey( 'admin_post_freeman_save_feature_flags', $GLOBALS['fr_hooks'] );
		$this->assertArrayHasKey( 'admin_post_freeman_toggle_module', $GLOBALS['fr_hooks'] );
	}
}
