<?php
declare(strict_types=1);

use Freeman\Core\Modules\InfiniteScroll\Module;
use PHPUnit\Framework\TestCase;

/**
 * Wave 3.1a — settings registration + flag-state propagation to localized
 * JS payload. Hook firing + render-path tests live in 3.1b.
 *
 * @covers \Freeman\Core\Modules\InfiniteScroll\Module
 */
final class InfiniteScrollSettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_trigger_mode_setting_registers_with_default_auto(): void {
		$schema = ( new Module() )->settings_schema();
		$this->assertSame( 'auto', $schema['trigger_mode']['default'] );
	}

	public function test_history_mode_setting_registers_with_default_pushState(): void {
		$schema = ( new Module() )->settings_schema();
		$this->assertSame( 'pushState', $schema['history_mode']['default'] );
	}

	public function test_hybrid_threshold_setting_registers_with_default_2(): void {
		$schema = ( new Module() )->settings_schema();
		$this->assertSame( 2, $schema['hybrid_threshold']['default'] );
	}

	public function test_feature_flag_reads_correct_option_key_on_and_off(): void {
		update_option( 'freeman_core_infinite_scroll_trigger_modes_enabled', 1 );
		$this->assertTrue( \Freeman\Core\Core\Feature_Flags::is_enabled( 'infinite_scroll', 'trigger_modes' ) );

		delete_option( 'freeman_core_infinite_scroll_trigger_modes_enabled' );
		$this->assertFalse( \Freeman\Core\Core\Feature_Flags::is_enabled( 'infinite_scroll', 'trigger_modes' ) );
	}

	public function test_flag_off_localized_payload_signals_disabled(): void {
		// Flag explicitly absent — Feature_Flags returns false by default.
		$payload = ( new Module() )->localized_payload();
		$this->assertFalse( $payload['triggerModesEnabled'] );
	}

	public function test_flag_on_default_settings_localized_payload_matches_flag_off_modulo_enabled(): void {
		// Flag-OFF payload first.
		$off = ( new Module() )->localized_payload();

		// Flag-ON, all settings at default. The contract: only triggerModesEnabled differs.
		update_option( 'freeman_core_infinite_scroll_trigger_modes_enabled', 1 );
		$on = ( new Module() )->localized_payload();

		$this->assertTrue( $on['triggerModesEnabled'] );

		unset( $off['triggerModesEnabled'], $on['triggerModesEnabled'] );
		$this->assertSame( $off, $on );
	}
}
