<?php
declare(strict_types=1);

require_once __DIR__ . '/snapshots/__fixtures__/wc_product_stub.php';

use PHPUnit\Framework\TestCase;

/**
 * Asserts the flag-OFF / flag-ON contract for the JSON variations payload
 * built by Etucart_VS_Archive::build_variation_entry() — Wave 2.2 / 4f.
 *
 * Flag OFF: entry shape is byte-identical to pre-1.11.23 (5 keys: id, attrs,
 * in_stock, is_purchasable, price_html). Flag ON: entry also carries
 * image_src / image_srcset / image_sizes when the variation has an `image`
 * subarray. The `freeman_core/variation_swatches/card_image_payload` filter
 * runs on the image data only.
 *
 * @covers \Etucart_VS_Archive::build_variation_entry
 */
final class VariationSwatchesCardImagePayloadSnapshotTest extends TestCase {

	private const ARCHIVE_FILE = __DIR__ . '/../freeman-core/src/Modules/VariationSwatches/legacy/includes/class-archive.php';

	public static function setUpBeforeClass(): void {
		// Define the legacy constants the file expects, then load it. Other
		// legacy files (class-plugin etc.) are not loaded — build_variation_entry
		// is self-contained and only depends on apply_filters().
		if ( ! defined( 'ETUCART_VS_VERSION' ) ) {
			define( 'ETUCART_VS_VERSION', '1.11.23' );
		}
		if ( ! defined( 'ETUCART_VS_DIR' ) ) {
			define( 'ETUCART_VS_DIR', dirname( self::ARCHIVE_FILE ) . '/' );
		}
		if ( ! defined( 'ETUCART_VS_URL' ) ) {
			define( 'ETUCART_VS_URL', 'https://example.test/' );
		}
		require_once self::ARCHIVE_FILE;
	}

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	/** Minimal WC variation array as get_available_variations() would return. */
	private function variation_with_image(): array {
		return array(
			'variation_id'   => 42,
			'attributes'     => array( 'attribute_pa_color' => 'blue', 'attribute_pa_size' => 'm' ),
			'is_in_stock'    => true,
			'is_purchasable' => true,
			'price_html'     => '<span class="amount">$20.00</span>',
			'image'          => array(
				'url'    => 'https://example.test/wp-content/uploads/blue-m.jpg',
				'srcset' => 'https://example.test/blue-m-300.jpg 300w, https://example.test/blue-m-600.jpg 600w',
				'sizes'  => '(max-width: 600px) 300px, 600px',
			),
		);
	}

	private function variation_without_image(): array {
		$v = $this->variation_with_image();
		unset( $v['image'] );
		return $v;
	}

	private function build( array $v, bool $with_image ): array {
		return \Etucart_VS_Archive::build_variation_entry( $v, new \WC_Product(), $with_image );
	}

	private function prepared_transient_key(): string {
		$method = new \ReflectionMethod( \Etucart_VS_Archive::class, 'prepared_transient_key' );
		$method->setAccessible( true );

		return (string) $method->invoke( new \Etucart_VS_Archive(), 42 );
	}

	public function test_flag_off_entry_has_only_five_keys(): void {
		$entry = $this->build( $this->variation_with_image(), false );

		$this->assertSame(
			array( 'id', 'attrs', 'in_stock', 'is_purchasable', 'price_html' ),
			array_keys( $entry ),
			'Flag-OFF payload must be byte-identical to pre-1.11.23 (no image fields).'
		);
	}

	public function test_flag_off_with_image_data_still_omits_image_fields(): void {
		// Even when the WC variation carries an `image` subarray, flag-OFF must
		// not leak image fields into the picker JSON. P1 contract.
		$entry = $this->build( $this->variation_with_image(), false );

		$this->assertArrayNotHasKey( 'image_src', $entry );
		$this->assertArrayNotHasKey( 'image_srcset', $entry );
		$this->assertArrayNotHasKey( 'image_sizes', $entry );
	}

	public function test_flag_on_with_image_data_emits_three_image_fields(): void {
		$entry = $this->build( $this->variation_with_image(), true );

		$this->assertArrayHasKey( 'image_src', $entry );
		$this->assertArrayHasKey( 'image_srcset', $entry );
		$this->assertArrayHasKey( 'image_sizes', $entry );
		$this->assertSame( 'https://example.test/wp-content/uploads/blue-m.jpg', $entry['image_src'] );
		$this->assertSame( '(max-width: 600px) 300px, 600px', $entry['image_sizes'] );
	}

	public function test_flag_on_without_image_data_does_not_synthesize_fields(): void {
		// If the WC variation has no `image` subarray (legacy data, custom
		// callback that strips it, etc.), flag-ON must not produce empty
		// image_* fields — better to omit than to emit empty strings that
		// the JS would then try to apply.
		$entry = $this->build( $this->variation_without_image(), true );

		$this->assertArrayNotHasKey( 'image_src', $entry );
		$this->assertArrayNotHasKey( 'image_srcset', $entry );
		$this->assertArrayNotHasKey( 'image_sizes', $entry );
	}

	public function test_card_image_payload_filter_receives_image_array_and_can_mutate(): void {
		$captured = array();
		add_filter(
			'freeman_core/variation_swatches/card_image_payload',
			static function ( $payload, $variation, $product ) use ( &$captured ) {
				$captured                = array(
					'payload'      => $payload,
					'variation_id' => $variation['variation_id'] ?? 0,
					'product_kind' => is_object( $product ) ? get_class( $product ) : '',
				);
				$payload['image_src']    = 'https://example.test/replaced.jpg';
				return $payload;
			},
			10,
			3
		);

		$entry = $this->build( $this->variation_with_image(), true );

		$this->assertSame( 'https://example.test/replaced.jpg', $entry['image_src'], 'Filter return value must be applied.' );
		$this->assertSame( 42, $captured['variation_id'], 'Filter must receive the full variation array.' );
		$this->assertSame( 'WC_Product', $captured['product_kind'], 'Filter must receive the parent product.' );
	}

	public function test_card_image_payload_filter_does_not_fire_when_flag_off(): void {
		$fired = false;
		add_filter(
			'freeman_core/variation_swatches/card_image_payload',
			static function ( $payload ) use ( &$fired ) {
				$fired = true;
				return $payload;
			}
		);

		$this->build( $this->variation_with_image(), false );

		$this->assertFalse( $fired, 'Filter must not fire on the flag-OFF path.' );
	}

	public function test_auto_color_flag_changes_archive_payload_transient_key(): void {
		$off_key = $this->prepared_transient_key();

		update_option( 'freeman_core_variation_swatches_auto_color_enabled', 1 );
		$on_key = $this->prepared_transient_key();

		$this->assertNotSame(
			$off_key,
			$on_key,
			'Auto-color changes cached option hex values, so flag flips must bypass stale archive picker payloads.'
		);
	}

	public function test_attrs_map_normalizes_to_strings(): void {
		// Pre-existing behavior — guard against regression while the loop body
		// is being refactored into build_variation_entry().
		$v          = $this->variation_with_image();
		$v['attributes'] = array( 'attribute_pa_color' => 123, 'attribute_pa_size' => 'm' );

		$entry = $this->build( $v, true );

		$this->assertSame(
			array( 'attribute_pa_color' => '123', 'attribute_pa_size' => 'm' ),
			$entry['attrs']
		);
	}
}
