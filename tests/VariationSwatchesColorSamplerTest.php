<?php
declare(strict_types=1);

use Freeman\Core\Modules\VariationSwatches\Color_Sampler;
use PHPUnit\Framework\TestCase;

/**
 * Wave 2.2 / 4d — Color_Sampler.
 *
 * Fixtures are synthesized programmatically in setUp via imagecreatetruecolor
 * + imagepng to a tmp file, so no PNG bytes are committed to the repo.
 * Eliminates license + repo-size questions and lets each test express its
 * exact pixel pattern inline.
 *
 * @covers \Freeman\Core\Modules\VariationSwatches\Color_Sampler
 */
final class VariationSwatchesColorSamplerTest extends TestCase {

	private string $tmp_dir;

	public static function setUpBeforeClass(): void {
		if ( ! extension_loaded( 'gd' ) ) {
			self::markTestSkippedOnGd();
		}
	}

	private static function markTestSkippedOnGd(): void {
		// PHPUnit's static setUpBeforeClass can't call $this->markTestSkipped;
		// throw the equivalent skip exception manually.
		throw new \PHPUnit\Framework\SkippedTestSuiteError( 'GD extension not available — sampler tests skipped.' );
	}

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']             = array();
		$GLOBALS['fr_post_meta']        = array();
		$GLOBALS['fr_attachment_paths'] = array();
		$GLOBALS['fr_hooks']            = array();

		$this->tmp_dir = sys_get_temp_dir() . '/freeman-sampler-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->tmp_dir, 0755, true );
	}

	protected function tearDown(): void {
		if ( is_dir( $this->tmp_dir ) ) {
			foreach ( glob( $this->tmp_dir . '/*' ) ?: array() as $f ) {
				@unlink( $f );
			}
			@rmdir( $this->tmp_dir );
		}
		parent::tearDown();
	}

	private function write_solid( int $w, int $h, int $r, int $g, int $b, string $name = 'img.png' ): string {
		$im  = imagecreatetruecolor( $w, $h );
		$col = imagecolorallocate( $im, $r, $g, $b );
		imagefill( $im, 0, 0, $col );
		$path = $this->tmp_dir . '/' . $name;
		imagepng( $im, $path );
		imagedestroy( $im );
		return $path;
	}

	private function write_white_bg_with_center(
		int $w,
		int $h,
		int $r,
		int $g,
		int $b,
		string $name = 'img.png'
	): string {
		$im    = imagecreatetruecolor( $w, $h );
		$white = imagecolorallocate( $im, 255, 255, 255 );
		$inner = imagecolorallocate( $im, $r, $g, $b );
		imagefill( $im, 0, 0, $white );
		// Fill a 60% center rectangle with the inner color, leaving a white edge ring.
		$inset_x = (int) round( $w * 0.2 );
		$inset_y = (int) round( $h * 0.2 );
		imagefilledrectangle( $im, $inset_x, $inset_y, $w - $inset_x - 1, $h - $inset_y - 1, $inner );
		$path = $this->tmp_dir . '/' . $name;
		imagepng( $im, $path );
		imagedestroy( $im );
		return $path;
	}

	private function register_attachment( int $attachment_id, string $path ): void {
		$GLOBALS['fr_attachment_paths'][ $attachment_id ] = $path;
	}

	private function attach_to_variation( int $variation_id, int $attachment_id ): void {
		$GLOBALS['fr_post_meta'][ $variation_id ]['_thumbnail_id'] = $attachment_id;
	}

	public function test_sample_solid_blue_image_returns_blue_hex(): void {
		$path = $this->write_solid( 60, 60, 0x33, 0x66, 0xCC );
		$this->register_attachment( 100, $path );
		$this->attach_to_variation( 200, 100 );

		$hex = Color_Sampler::sample( 200 );

		// Bucket-quantization rounds toward 8-pixel steps; assert the hex is
		// close to the input rather than exact.
		$this->assertNotSame( '', $hex );
		$this->assertMatchesRegularExpression( '/^#[0-9A-F]{6}$/', $hex );
		[ $r, $g, $b ] = sscanf( $hex, '#%02X%02X%02X' );
		$this->assertLessThanOrEqual( 12, abs( $r - 0x33 ) );
		$this->assertLessThanOrEqual( 12, abs( $g - 0x66 ) );
		$this->assertLessThanOrEqual( 12, abs( $b - 0xCC ) );
	}

	public function test_sample_white_bg_with_red_center_picks_red_not_white(): void {
		// This is the headline test: modal-with-edge-filter dropping the
		// edge ring is the whole point of the algorithm. A naive mode would
		// pick #FFFFFF because white dominates the pixel count.
		$path = $this->write_white_bg_with_center( 80, 80, 0xCC, 0x22, 0x33 );
		$this->register_attachment( 101, $path );
		$this->attach_to_variation( 201, 101 );

		$hex = Color_Sampler::sample( 201 );

		$this->assertNotSame( '', $hex );
		[ $r, $g, $b ] = sscanf( $hex, '#%02X%02X%02X' );
		// Should be reddish — high R, low G, low B.
		$this->assertGreaterThan( 150, $r, "Expected red dominance, got $hex" );
		$this->assertLessThan( 100, $g, "Expected low green, got $hex" );
		$this->assertLessThan( 100, $b, "Expected low blue, got $hex" );
	}

	public function test_sample_persists_hex_to_variation_post_meta(): void {
		$path = $this->write_solid( 30, 30, 0, 128, 0 );
		$this->register_attachment( 102, $path );
		$this->attach_to_variation( 202, 102 );

		Color_Sampler::sample( 202 );

		$stored = get_post_meta( 202, Color_Sampler::META_KEY, true );
		$this->assertIsString( $stored );
		$this->assertNotSame( '', $stored );
	}

	public function test_sample_with_no_attachment_writes_empty_string(): void {
		// No _thumbnail_id, no parent fallback. Sentinel: empty hex.
		$hex = Color_Sampler::sample( 203 );

		$this->assertSame( '', $hex );
		$this->assertSame( '', get_post_meta( 203, Color_Sampler::META_KEY, true ) );
	}

	public function test_sample_with_broken_image_path_returns_empty(): void {
		$this->register_attachment( 103, $this->tmp_dir . '/does-not-exist.png' );
		$this->attach_to_variation( 204, 103 );

		$hex = Color_Sampler::sample( 204 );

		$this->assertSame( '', $hex );
	}

	public function test_sample_falls_back_to_parent_image_when_variation_has_none(): void {
		// Variation has no _thumbnail_id; parent product has one.
		$path = $this->write_solid( 30, 30, 0, 0, 200 );
		$this->register_attachment( 104, $path );
		// Parent product post id 300, variation 205.
		$GLOBALS['fr_post_parent'][205]                       = 300;
		$GLOBALS['fr_post_meta'][300]['_thumbnail_id']        = 104;

		$hex = Color_Sampler::sample( 205 );

		$this->assertNotSame( '', $hex );
		[ , , $b ] = sscanf( $hex, '#%02X%02X%02X' );
		$this->assertGreaterThan( 150, $b, "Expected blue parent image, got $hex" );
	}

	public function test_sample_if_missing_returns_cached_value_without_resampling(): void {
		$GLOBALS['fr_post_meta'][206][ Color_Sampler::META_KEY ] = '#ABCDEF';

		$hex = Color_Sampler::sample_if_missing( 206 );

		$this->assertSame( '#ABCDEF', $hex );
	}

	public function test_sample_if_missing_samples_when_no_cached_value(): void {
		$path = $this->write_solid( 30, 30, 0, 0, 200 );
		$this->register_attachment( 105, $path );
		$this->attach_to_variation( 207, 105 );

		$hex = Color_Sampler::sample_if_missing( 207 );

		$this->assertNotSame( '', $hex );
		// The freshly-sampled value must now be persisted.
		$this->assertSame( $hex, get_post_meta( 207, Color_Sampler::META_KEY, true ) );
	}

	public function test_clear_removes_cached_meta(): void {
		$GLOBALS['fr_post_meta'][208][ Color_Sampler::META_KEY ] = '#123456';

		Color_Sampler::clear( 208 );

		$this->assertSame( '', get_post_meta( 208, Color_Sampler::META_KEY, true ) );
	}

	public function test_sample_with_zero_or_negative_id_is_no_op(): void {
		$this->assertSame( '', Color_Sampler::sample( 0 ) );
		$this->assertSame( '', Color_Sampler::sample( -1 ) );
		$this->assertSame( '', Color_Sampler::sample_if_missing( 0 ) );
	}
}
