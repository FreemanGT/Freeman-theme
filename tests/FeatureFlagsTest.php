<?php
declare(strict_types=1);

use Freeman\Core\Core\Feature_Flags;
use PHPUnit\Framework\TestCase;

final class FeatureFlagsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_returns_false_when_option_missing(): void {
		$this->assertFalse( Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ) );
	}

	/**
	 * @dataProvider truthy_values
	 */
	public function test_returns_true_for_truthy_values( $value ): void {
		update_option( 'freeman_core_sliders_advanced_controls_enabled', $value );
		$this->assertTrue(
			Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ),
			'Expected true for value: ' . var_export( $value, true )
		);
	}

	public static function truthy_values(): array {
		return array(
			array( '1' ),
			array( 1 ),
			array( true ),
			array( 'true' ),
			array( 'yes' ),
			array( 'on' ),
		);
	}

	/**
	 * @dataProvider falsy_values
	 */
	public function test_returns_false_for_falsy_values( $value ): void {
		update_option( 'freeman_core_sliders_advanced_controls_enabled', $value );
		$this->assertFalse(
			Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ),
			'Expected false for value: ' . var_export( $value, true )
		);
	}

	public static function falsy_values(): array {
		return array(
			array( '0' ),
			array( '' ),
			array( 0 ),
			array( false ),
			array( 'false' ),
			array( 'no' ),
			array( 'off' ),
		);
	}

	public function test_garbage_string_resolves_to_false(): void {
		update_option( 'freeman_core_sliders_advanced_controls_enabled', 'banana' );
		$this->assertFalse( Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ) );
	}

	public function test_two_flags_are_independent(): void {
		update_option( 'freeman_core_sliders_advanced_controls_enabled', '1' );
		update_option( 'freeman_core_infinite_scroll_trigger_modes_enabled', '0' );

		$this->assertTrue( Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ) );
		$this->assertFalse( Feature_Flags::is_enabled( 'infinite_scroll', 'trigger_modes' ) );
	}

	public function test_option_key_format(): void {
		update_option( 'freeman_core_sliders_advanced_controls_enabled', '1' );

		$this->assertTrue( Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ) );
		$this->assertFalse( Feature_Flags::is_enabled( 'sliders', 'wrong_feature' ) );
		$this->assertFalse( Feature_Flags::is_enabled( 'wrong_module', 'advanced_controls' ) );
	}

	public function test_filter_can_force_enable(): void {
		add_filter(
			'freeman_core/feature_flag/sliders/advanced_controls',
			static function () {
				return true;
			}
		);

		$this->assertTrue( Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ) );
	}

	public function test_filter_receives_module_and_feature(): void {
		$captured = array();
		add_filter(
			'freeman_core/feature_flag/sliders/advanced_controls',
			static function ( $enabled, $module, $feature ) use ( &$captured ) {
				$captured = array( $enabled, $module, $feature );
				return $enabled;
			},
			10,
			3
		);

		Feature_Flags::is_enabled( 'sliders', 'advanced_controls' );

		$this->assertSame( array( false, 'sliders', 'advanced_controls' ), $captured );
	}
}
