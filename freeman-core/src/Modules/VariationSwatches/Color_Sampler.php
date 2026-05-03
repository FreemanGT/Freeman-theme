<?php
/**
 * Wave 2.2 / 4d (1.11.27) — auto-color sampler.
 *
 * Given a WooCommerce variation post id, resolves its primary image,
 * runs modal-with-edge-filter sampling (drop pixels touching the image
 * bounding-box edges, take mode of the remainder after light bucket
 * quantization), and stores the resulting hex as variation post-meta
 * `_freeman_core_vs_sampled_color`. Read by 4e's color-resolution branch.
 *
 * Sampling library: GD by default (universally available); auto-upgrade
 * to Imagick when `extension_loaded('imagick')` is true.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariationSwatches;

defined( 'ABSPATH' ) || exit;

/**
 * Color sampler.
 */
final class Color_Sampler {

	const META_KEY = '_freeman_core_vs_sampled_color';

	/** Bits-per-channel for bucket quantization (5 = 32 buckets per channel). */
	const QUANTIZE_BITS = 5;

	/**
	 * Sample a variation's image and persist the result. Always re-samples
	 * (overwrites any cached value) — used by the `_thumbnail_id` change
	 * listener and explicit re-sample paths.
	 *
	 * @param int $variation_id Variation post id.
	 * @return string Hex value (e.g. `#3366CC`) or empty string on failure.
	 */
	public static function sample( $variation_id ) {
		$variation_id = (int) $variation_id;
		if ( $variation_id <= 0 ) {
			return '';
		}

		$attachment_id = self::resolve_attachment_id( $variation_id );
		if ( $attachment_id <= 0 ) {
			update_post_meta( $variation_id, self::META_KEY, '' );
			return '';
		}

		$hex = self::sample_attachment( $attachment_id );
		update_post_meta( $variation_id, self::META_KEY, $hex );
		return $hex;
	}

	/**
	 * Sample only when no cached value exists. Idempotent: a variation that
	 * already has a hex (even an empty-string sentinel from a prior failed
	 * sample) is left alone. Used by the hot-path lazy fallback (4e's render
	 * path) so the render path doesn't pay the sampling cost twice.
	 *
	 * @param int $variation_id Variation post id.
	 * @return string Hex from cache or fresh sample.
	 */
	public static function sample_if_missing( $variation_id ) {
		$variation_id = (int) $variation_id;
		if ( $variation_id <= 0 ) {
			return '';
		}

		$existing = get_post_meta( $variation_id, self::META_KEY, true );
		if ( '' !== $existing && null !== $existing && false !== $existing ) {
			return is_string( $existing ) ? $existing : '';
		}
		// Empty-string sentinel means "we tried and the image was missing/broken".
		// Don't retry — let it stay '' until something explicitly clears it.
		if ( '' === $existing && self::has_explicit_meta( $variation_id ) ) {
			return '';
		}

		return self::sample( $variation_id );
	}

	/**
	 * Clear the cached hex. Used by the `_thumbnail_id` change listener
	 * (variation got a new image) and the `delete_attachment` listener
	 * (the image was deleted out from under the variation).
	 *
	 * @param int $variation_id Variation post id.
	 */
	public static function clear( $variation_id ) {
		$variation_id = (int) $variation_id;
		if ( $variation_id > 0 ) {
			delete_post_meta( $variation_id, self::META_KEY );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Internals
	 * ------------------------------------------------------------------ */

	/**
	 * Resolve the variation's primary image attachment id, falling back to
	 * the parent variable product's `_thumbnail_id` when the variation
	 * itself has no image.
	 *
	 * @param int $variation_id Variation post id.
	 * @return int Attachment id or 0.
	 */
	private static function resolve_attachment_id( $variation_id ) {
		$thumb_id = (int) get_post_meta( $variation_id, '_thumbnail_id', true );
		if ( $thumb_id > 0 ) {
			return $thumb_id;
		}

		// Fallback: parent variable product's image. WC's get_post_parent
		// is a thin wp_get_post wrapper; using post_parent directly keeps
		// this testable without the full WC stack.
		$parent_id = function_exists( 'wp_get_post_parent_id' )
			? (int) wp_get_post_parent_id( $variation_id )
			: 0;
		if ( $parent_id > 0 ) {
			return (int) get_post_meta( $parent_id, '_thumbnail_id', true );
		}
		return 0;
	}

	/**
	 * Sample the dominant color of an attachment.
	 *
	 * @param int $attachment_id Attachment post id.
	 * @return string Hex (`#RRGGBB`) or empty string on failure.
	 */
	private static function sample_attachment( $attachment_id ) {
		$path = self::resolve_attachment_path( $attachment_id );
		if ( '' === $path || ! @is_readable( $path ) ) {
			return '';
		}

		$rgb = self::pick_sampler( $path );
		if ( null === $rgb ) {
			return '';
		}

		return sprintf( '#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Filesystem path for an attachment. Returns empty string when the
	 * attachment doesn't exist or can't be resolved (deleted out from
	 * under us, etc.).
	 */
	private static function resolve_attachment_path( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return '';
		}
		// Test-friendly: tests register a synthesized path keyed under a global.
		if ( isset( $GLOBALS['fr_attachment_paths'][ $attachment_id ] ) ) {
			return (string) $GLOBALS['fr_attachment_paths'][ $attachment_id ];
		}
		if ( function_exists( 'get_attached_file' ) ) {
			$path = get_attached_file( $attachment_id );
			return is_string( $path ) ? $path : '';
		}
		return '';
	}

	/**
	 * Pick the sampler implementation. Imagick when available, GD as the
	 * universal fallback. Returns `[r, g, b]` ints (0-255) or null on
	 * failure (broken image, unsupported format, both libs unavailable).
	 *
	 * @param string $path Filesystem path.
	 * @return array{0:int,1:int,2:int}|null
	 */
	private static function pick_sampler( $path ) {
		if ( extension_loaded( 'imagick' ) ) {
			$imagick_result = self::sample_imagick( $path );
			if ( null !== $imagick_result ) {
				return $imagick_result;
			}
			// Fall through to GD if Imagick choked on this specific image.
		}
		if ( extension_loaded( 'gd' ) ) {
			return self::sample_gd( $path );
		}
		return null;
	}

	/**
	 * Modal-with-edge-filter sampling via GD. Drops pixels on the bounding-
	 * box edges (typical product-photo background) and takes the mode of
	 * the remainder after bucket quantization.
	 */
	private static function sample_gd( $path ) {
		$im = @imagecreatefromstring( (string) @file_get_contents( $path ) );
		if ( ! $im ) {
			return null;
		}

		$w = imagesx( $im );
		$h = imagesy( $im );
		if ( $w < 3 || $h < 3 ) {
			imagedestroy( $im );
			return null;
		}

		$buckets       = self::tally_buckets_gd( $im, $w, $h );
		$bg_bucket_key = self::detect_background_bucket_gd( $im, $w, $h );
		imagedestroy( $im );

		return self::mode_to_rgb_excluding_background( $buckets, $bg_bucket_key );
	}

	/**
	 * Detect the image's background bucket by sampling its 4 corners.
	 *
	 * Returns the bucket key when at least 3/4 corners agree on a single
	 * bucket (typical product-photo case: white/grey/transparent backdrop).
	 * Returns null when corners disagree (gradient backgrounds, photos with
	 * no clear backdrop) — the caller treats null as "no background to
	 * exclude" and falls back to plain modal sampling.
	 *
	 * Sampling 4 single pixels would be fragile for JPEG-encoded images
	 * (compression noise at corners), so we sample a 3x3 patch at each
	 * corner and take its modal bucket.
	 */
	private static function detect_background_bucket_gd( $im, $w, $h ) {
		$corners = array(
			self::corner_modal_bucket_gd( $im, 0, 0 ),                         // top-left
			self::corner_modal_bucket_gd( $im, $w - 3, 0 ),                    // top-right
			self::corner_modal_bucket_gd( $im, 0, $h - 3 ),                    // bottom-left
			self::corner_modal_bucket_gd( $im, $w - 3, $h - 3 ),               // bottom-right
		);

		// Tally how many corners agree on each bucket key.
		$tally = array();
		foreach ( $corners as $key ) {
			if ( null === $key ) {
				continue;
			}
			$tally[ $key ] = ( $tally[ $key ] ?? 0 ) + 1;
		}
		if ( empty( $tally ) ) {
			return null;
		}

		$max = max( $tally );
		if ( $max < 3 ) {
			return null; // no 3-of-4 consensus → no clear background.
		}
		// Return the first key matching the max count.
		foreach ( $tally as $key => $count ) {
			if ( $count === $max ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Modal bucket key from a 3x3 patch starting at (x, y).
	 */
	private static function corner_modal_bucket_gd( $im, $x, $y ) {
		$tally = array();
		for ( $dy = 0; $dy < 3; $dy++ ) {
			for ( $dx = 0; $dx < 3; $dx++ ) {
				$rgb = imagecolorat( $im, $x + $dx, $y + $dy );
				$key = self::bucket_key( ( $rgb >> 16 ) & 0xFF, ( $rgb >> 8 ) & 0xFF, $rgb & 0xFF );
				$tally[ $key ] = ( $tally[ $key ] ?? 0 ) + 1;
			}
		}
		if ( empty( $tally ) ) {
			return null;
		}
		$max = max( $tally );
		foreach ( $tally as $key => $count ) {
			if ( $count === $max ) {
				return $key;
			}
		}
		return null;
	}

	/**
	 * Walk the image's interior pixels (skipping the edge ring) and tally
	 * bucket-quantized colors. Returns `[bucket_key => count]`.
	 */
	private static function tally_buckets_gd( $im, $w, $h ) {
		$buckets = array();
		// Step every 2px on both axes for >250×250 images so a 1000×1000 photo
		// doesn't tally a million pixels. Smaller images get full resolution.
		$step = ( $w > 250 || $h > 250 ) ? 2 : 1;
		for ( $y = 1; $y < $h - 1; $y += $step ) {
			for ( $x = 1; $x < $w - 1; $x += $step ) {
				$rgb = imagecolorat( $im, $x, $y );
				$r   = ( $rgb >> 16 ) & 0xFF;
				$g   = ( $rgb >> 8 ) & 0xFF;
				$b   = $rgb & 0xFF;

				$key = self::bucket_key( $r, $g, $b );
				if ( isset( $buckets[ $key ] ) ) {
					++$buckets[ $key ];
				} else {
					$buckets[ $key ] = 1;
				}
			}
		}
		return $buckets;
	}

	/**
	 * Imagick variant. Uses Imagick's getQuantumDepth + faster pixel
	 * iteration. Falls back to null on any failure so the GD path can
	 * retry.
	 */
	private static function sample_imagick( $path ) {
		try {
			$img = new \Imagick( $path );
			$w   = (int) $img->getImageWidth();
			$h   = (int) $img->getImageHeight();
			if ( $w < 3 || $h < 3 ) {
				$img->clear();
				return null;
			}

			// Crop to interior (drop 1px edge ring).
			$img->cropImage( $w - 2, $h - 2, 1, 1 );
			$img->setImagePage( 0, 0, 0, 0 );

			// Re-quantize to QUANTIZE_BITS-per-channel granularity by
			// reducing the colorspace and using quantizeImage.
			$bucket_count = ( 1 << ( 3 * self::QUANTIZE_BITS ) );
			$img->quantizeImage(
				min( $bucket_count, 4096 ),
				\Imagick::COLORSPACE_RGB,
				0,
				false,
				false
			);

			// Get the histogram, find max-occurrence color.
			$hist = $img->getImageHistogram();
			$top  = null;
			$best = -1;
			foreach ( $hist as $px ) {
				$count = (int) $px->getColorCount();
				if ( $count > $best ) {
					$c    = $px->getColor();
					$best = $count;
					$top  = array(
						(int) ( $c['r'] ?? 0 ),
						(int) ( $c['g'] ?? 0 ),
						(int) ( $c['b'] ?? 0 ),
					);
				}
				$px->clear();
			}
			$img->clear();
			return $top;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function bucket_key( $r, $g, $b ) {
		$shift = 8 - self::QUANTIZE_BITS;
		return ( ( $r >> $shift ) << ( 2 * self::QUANTIZE_BITS ) )
			| ( ( $g >> $shift ) << self::QUANTIZE_BITS )
			| ( $b >> $shift );
	}

	/**
	 * Pick the representative RGB given a bucket-count tally and an optional
	 * background bucket to exclude.
	 *
	 * Algorithm:
	 *   - Find the modal bucket. If no background was detected, return it.
	 *   - If the modal bucket IS the background AND a runner-up exists with
	 *     at least 5% of the modal's count, return the runner-up — this is
	 *     the case where white-bg dominates the pixel count but the product
	 *     occupies a meaningful chunk of the image.
	 *   - If the modal IS the background and no significant runner-up exists,
	 *     return the modal anyway (solid-color image, or image where the
	 *     background fills almost everything — e.g. a "no image available"
	 *     placeholder).
	 *
	 * @param array<int,int> $buckets       bucket_key → count
	 * @param int|null       $bg_bucket_key The detected background bucket, or null.
	 * @return array{0:int,1:int,2:int}|null
	 */
	private static function mode_to_rgb_excluding_background( array $buckets, $bg_bucket_key ) {
		if ( empty( $buckets ) ) {
			return null;
		}

		$max     = max( $buckets );
		$top_key = array_keys( $buckets, $max, true )[0];

		if ( null !== $bg_bucket_key && $top_key === $bg_bucket_key ) {
			$without_bg = $buckets;
			unset( $without_bg[ $bg_bucket_key ] );
			if ( ! empty( $without_bg ) ) {
				$runner_up_count = max( $without_bg );
				if ( $runner_up_count * 20 >= $max ) { // runner-up ≥ 5% of background.
					$top_key = array_keys( $without_bg, $runner_up_count, true )[0];
				}
			}
		}

		return self::bucket_to_rgb( $top_key );
	}

	/**
	 * Centers the bucket so #FFF doesn't quantize to #F8F8F8.
	 */
	private static function bucket_to_rgb( $bucket_key ) {
		$shift      = 8 - self::QUANTIZE_BITS;
		$mask       = ( 1 << self::QUANTIZE_BITS ) - 1;
		$r_bucket   = ( $bucket_key >> ( 2 * self::QUANTIZE_BITS ) ) & $mask;
		$g_bucket   = ( $bucket_key >> self::QUANTIZE_BITS ) & $mask;
		$b_bucket   = $bucket_key & $mask;
		$bucket_mid = ( 1 << $shift ) >> 1;
		return array(
			min( 255, ( $r_bucket << $shift ) + $bucket_mid ),
			min( 255, ( $g_bucket << $shift ) + $bucket_mid ),
			min( 255, ( $b_bucket << $shift ) + $bucket_mid ),
		);
	}

	/**
	 * Distinguish "we sampled and got '' because the image was missing"
	 * from "this variation has never been sampled". Both conditions look
	 * like an empty `get_post_meta(..., true)` return because the WP API
	 * collapses missing-key and empty-string-value to the same response.
	 */
	private static function has_explicit_meta( $variation_id ) {
		if ( ! function_exists( 'metadata_exists' ) ) {
			return false;
		}
		return metadata_exists( 'post', (int) $variation_id, self::META_KEY );
	}
}
