<?php
declare(strict_types=1);

use Freeman\Core\Core\Settings_Hub;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freeman\Core\Core\Settings_Hub
 */
final class SettingsHubCheckboxRenderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_checkbox_render_treats_yes_default_as_checked(): void {
		$hub    = new Settings_Hub( new \Freeman\Core\Core\Module_Registry() );
		$method = ( new ReflectionClass( $hub ) )->getMethod( 'render_field' );
		$method->setAccessible( true );
		$module = new \Freeman\Core\Modules\VariationSwatches\Module();

		ob_start();
		$method->invoke(
			$hub,
			$module,
			'shop_enabled',
			array(
				'label'          => 'Shop enabled',
				'type'           => 'checkbox',
				'checkbox_label' => 'Enabled',
				'default'        => 'yes',
			)
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'checked=', $html );
	}
}
