<?php
declare(strict_types=1);

use Freeman\Core\Modules\VariationSwatches\Module;
use PHPUnit\Framework\TestCase;

/**
 * Wave 4.5 (1.11.40) — VariationSwatches WPC Bundles + FBT compat. PHP-side
 * plumbing only: `Module::inject_feature_flags()` emits a `window.FreemanCoreVSFlags`
 * inline script before the `freeman-core` handle, gated by the
 * `freeman_core_variation_swatches_bundle_compat_enabled` flag. The
 * behavioral JS changes are guarded with source-level checks because the
 * browser add-to-cart handler is not directly exercisable from PHPUnit.
 *
 * @covers \Freeman\Core\Modules\VariationSwatches\Module
 */

final class VariationSwatchesBundleCompatTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']                    = array();
		$GLOBALS['fr_hooks']                   = array();
		$GLOBALS['fr_scripts_inline']          = array();
		$GLOBALS['fr_wp_script_is_registered'] = array();
	}

	public function test_inject_feature_flags_bails_when_handle_not_registered(): void {
		$GLOBALS['fr_wp_script_is_registered']['freeman-core'] = false;

		( new Module() )->inject_feature_flags();

		$this->assertSame(
			array(),
			$GLOBALS['fr_scripts_inline'],
			'wp_add_inline_script must not be called when the freeman-core handle is unregistered'
		);
	}

	public function test_inject_feature_flags_emits_bundle_compat_false_when_flag_off(): void {
		$GLOBALS['fr_wp_script_is_registered']['freeman-core'] = true;
		// Flag default is false; no option set.

		( new Module() )->inject_feature_flags();

		$this->assertArrayHasKey( 'freeman-core', $GLOBALS['fr_scripts_inline'] );
		$this->assertArrayHasKey( 'before', $GLOBALS['fr_scripts_inline']['freeman-core'] );
		$payload = $GLOBALS['fr_scripts_inline']['freeman-core']['before'][0] ?? '';
		$this->assertStringContainsString( 'window.FreemanCoreVSFlags', $payload );
		$this->assertStringContainsString( '"bundleCompat":false', $payload );
	}

	public function test_inject_feature_flags_emits_bundle_compat_true_when_flag_on(): void {
		$GLOBALS['fr_wp_script_is_registered']['freeman-core'] = true;
		$GLOBALS['fr_opts']['freeman_core_variation_swatches_bundle_compat_enabled'] = '1';

		( new Module() )->inject_feature_flags();

		$payload = $GLOBALS['fr_scripts_inline']['freeman-core']['before'][0] ?? '';
		$this->assertStringContainsString( '"bundleCompat":true', $payload );
		$this->assertStringNotContainsString( '"bundleCompat":false', $payload );
	}

	public function test_bundle_compat_ajax_payload_preserves_repeated_form_fields(): void {
		$src = file_get_contents( FREEMAN_CORE_PATH . 'src/Modules/VariationSwatches/assets/js/etucart-swatches.js' );

		$this->assertIsString( $src );
		$this->assertStringContainsString( 'data = [];', $src );
		$this->assertStringContainsString( 'data.push(field);', $src );
		$this->assertStringContainsString( "if (field.name === 'product_id' || field.name === 'quantity') return;", $src );
		$this->assertStringContainsString( "data.push({ name: 'product_id', value: (isSimple ? productId : variationId) });", $src );
		$this->assertStringContainsString( "data.push({ name: 'quantity', value: qty });", $src );
		$this->assertStringNotContainsString( 'data[field.name] = field.value;', $src );
	}

	/**
	 * Other VariationSwatches tests in the suite pre-load `Etucart_VS_Plugin`,
	 * which triggers Module::boot()'s legacy-conflict bail-out and prevents
	 * the add_action() registration we want to observe. Running this test in
	 * an isolated process gives us a clean class symbol table.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_boot_registers_inject_feature_flags_on_wp_enqueue_scripts_priority_10001(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			eval( 'class WooCommerce {}' );
		}
		( new Module() )->boot();

		$hooks = $GLOBALS['fr_hooks']['wp_enqueue_scripts'] ?? array();
		$found = false;
		foreach ( $hooks as $h ) {
			if ( is_array( $h['cb'] ) && $h['cb'][1] === 'inject_feature_flags' && (int) $h['priority'] === 10001 ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'inject_feature_flags must be wired to wp_enqueue_scripts at priority 10001' );
	}
}
