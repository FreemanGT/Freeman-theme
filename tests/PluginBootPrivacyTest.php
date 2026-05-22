<?php
declare(strict_types=1);

use Freeman\Core\Core\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freeman\Core\Core\Plugin::boot
 */
final class PluginBootPrivacyTest extends TestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_restock_privacy_hooks_register_even_when_restock_module_is_disabled(): void {
		$GLOBALS['fr_hooks'] = array();
		$GLOBALS['fr_opts']  = array(
			'freeman_core_db_version' => FREEMAN_CORE_VERSION,
			'freeman_core_modules'    => array(
				'restock_notify' => false,
			),
		);

		Plugin::instance()->boot();

		$this->assertArrayHasKey( 'wp_privacy_personal_data_exporters', $GLOBALS['fr_hooks'] );
		$this->assertArrayHasKey( 'wp_privacy_personal_data_erasers', $GLOBALS['fr_hooks'] );
		$this->assertNotEmpty( $GLOBALS['fr_hooks']['wp_privacy_personal_data_exporters'] );
		$this->assertNotEmpty( $GLOBALS['fr_hooks']['wp_privacy_personal_data_erasers'] );
	}
}
