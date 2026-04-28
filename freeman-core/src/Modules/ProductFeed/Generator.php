<?php
/**
 * Product Feed — generator.
 *
 * Owns the filesystem layout (uploads/freeman-product-feed/) and the actual
 * gzipped XML writer. Extracted from Module.php in 1.4.0 so the feed server
 * (HTTP surface) and the module lifecycle (cron, settings, admin UI) are
 * no longer entangled with the generator's internals.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ProductFeed;

use Freeman\Core\Core\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Feed generator.
 */
final class Generator {

	/** How many parent products to process per query page. */
	const BATCH = 100;

	/** Option that tracks the last successful generation timestamp. */
	const OPT_LAST_GEN = 'freeman_core_productfeed_last_generated';

	/* -----------------------------------------------------------------
	 * Paths
	 * ----------------------------------------------------------------- */

	/**
	 * Feed directory (uploads/freeman-product-feed).
	 *
	 * @return string
	 */
	public function feed_dir() {
		$up = wp_upload_dir();
		return trailingslashit( $up['basedir'] ) . 'freeman-product-feed';
	}

	/**
	 * Feed public URL (uploads/.../products.xml.gz).
	 *
	 * @return string
	 */
	public function feed_url() {
		$up = wp_upload_dir();
		return trailingslashit( $up['baseurl'] ) . 'freeman-product-feed/products.xml.gz';
	}

	/**
	 * Feed file path.
	 *
	 * @return string
	 */
	public function feed_file() {
		return $this->feed_dir() . '/products.xml.gz';
	}

	/**
	 * Sidecar file path that stores the uncompressed XML byte count for the
	 * current feed. Written by `write_feed()` so the HTTP layer can emit a
	 * `Content-Length` header on the decompressed-streaming response without
	 * re-reading the whole gzip.
	 *
	 * @return string
	 */
	public function size_file() {
		return $this->feed_dir() . '/products.xml.size';
	}

	/**
	 * Read the cached uncompressed feed size (in bytes), or null if unknown.
	 *
	 * @return int|null
	 */
	public function uncompressed_size() {
		$path = $this->size_file();
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			return null;
		}
		$size = (int) trim( $raw );
		return $size > 0 ? $size : null;
	}

	/**
	 * Lock file path (used for mutual exclusion between generator runs).
	 *
	 * @return string
	 */
	public function lock_file() {
		return $this->feed_dir() . '/generate.lock';
	}

	/**
	 * Ensure the feed directory exists and is silenced.
	 */
	public function ensure_feed_dir() {
		$dir = $this->feed_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		}
	}

	/* -----------------------------------------------------------------
	 * Generation
	 * ----------------------------------------------------------------- */

	/**
	 * Generate the feed, under an exclusive flock() so concurrent cron ticks
	 * never collide. A failure replays no tmp file into place — the last
	 * good feed stays served until the next successful run.
	 */
	public function generate() {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return;
		}
		$this->ensure_feed_dir();

		$lock = fopen( $this->lock_file(), 'c+' );
		if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			Logger::info( 'ProductFeed: another generation already running, skipping.' );
			return;
		}

		$start = microtime( true );
		$tmp   = $this->feed_file() . '.tmp';

		try {
			$this->write_feed( $tmp );
			rename( $tmp, $this->feed_file() );
			// Sidecar size file written by write_feed() — promote the tmp
			// alongside the .gz so they stay in sync.
			if ( file_exists( $tmp . '.size' ) ) {
				rename( $tmp . '.size', $this->size_file() );
			}
			update_option( self::OPT_LAST_GEN, current_time( 'mysql' ), false );
			$elapsed = round( microtime( true ) - $start, 2 );
			Logger::info( "ProductFeed generated in {$elapsed}s" );

			/**
			 * Fires after a successful feed generation, once the new file is
			 * in place and the last-generated timestamp has been written.
			 * Use cases: ping Merchant Center / Facebook Catalog webhooks,
			 * invalidate a CDN, push the file to S3.
			 *
			 * Does NOT fire on a failed run — the catch block above handles
			 * cleanup but never reaches this point.
			 *
			 * @since 1.11.1
			 *
			 * @param string $feed_file Path to the freshly written feed file.
			 * @param float  $elapsed   Generation time in seconds.
			 */
			do_action( 'freeman_core/product_feed/after_generate', $this->feed_file(), $elapsed );
		} catch ( \Throwable $e ) {
			Logger::error( 'ProductFeed error: ' . $e->getMessage() );
			if ( file_exists( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			if ( file_exists( $tmp . '.size' ) ) {
				@unlink( $tmp . '.size' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/**
	 * Write the gzipped XML to the given tmp path.
	 *
	 * @param string $tmp_file Tmp path.
	 * @throws \RuntimeException On gzopen failure.
	 */
	private function write_feed( $tmp_file ) {
		$gz = gzopen( $tmp_file, 'wb9' );
		if ( ! $gz ) {
			throw new \RuntimeException( 'Cannot open ' . esc_html( $tmp_file ) );
		}

		$now      = gmdate( 'c' );
		$site     = esc_url( get_bloginfo( 'url' ) );
		$currency = get_woocommerce_currency();
		$version  = FREEMAN_CORE_VERSION;

		// `gzwrite()` returns the number of uncompressed bytes written. Sum
		// them so we can persist the total to the sidecar size file used by
		// Server::serve_feed() to emit Content-Length on the decompressed
		// branch (M-02).
		$uncompressed = 0;
		$write        = static function ( $payload ) use ( $gz, &$uncompressed ) {
			$n = gzwrite( $gz, $payload );
			if ( false !== $n ) {
				$uncompressed += $n;
			}
		};

		$write( "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" );
		$write( "<products version=\"{$version}\" generated=\"{$now}\" site=\"{$site}\" currency=\"{$currency}\">\n" );

		$offset = 0;
		while ( true ) {
			/**
			 * Filter the `get_posts()` args used to page through products
			 * during feed generation. Use to scope the feed (e.g. only
			 * a specific category, or exclude a hidden taxonomy).
			 *
			 * Fires once per batch — the `offset` argument changes each tick.
			 *
			 * @since 1.11.1
			 *
			 * @param array $args   Args about to be passed to `get_posts()`.
			 * @param int   $offset Current paging offset.
			 */
			$args = (array) apply_filters(
				'freeman_core/product_feed/query_args',
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => self::BATCH,
					'offset'         => $offset,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				),
				$offset
			);
			$ids  = get_posts( $args );
			if ( empty( $ids ) ) {
				break;
			}
			// Batch-prime post + meta + term caches for the whole page of
			// parent products in a few queries instead of one-per-product.
			_prime_post_caches( $ids, true, true );
			foreach ( $ids as $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product || ! $product->is_visible() ) {
					continue;
				}
				/**
				 * Filter the rendered XML block for a single product before
				 * it is written to the gzipped feed. Receives the XML string
				 * built by `product_xml()` and the source product object.
				 *
				 * Returning an empty string skips the product silently.
				 *
				 * @since 1.11.1
				 *
				 * @param string      $xml     XML block for this product.
				 * @param \WC_Product $product Source product.
				 */
				$xml = (string) apply_filters( 'freeman_core/product_feed/item', $this->product_xml( $product ), $product );
				$write( $xml );
				wp_cache_delete( $pid, 'posts' );
			}
			$offset += self::BATCH;
			if ( class_exists( '\WC_Cache_Helper' ) ) {
				\WC_Cache_Helper::get_transient_version( 'product', true );
			}
			if ( function_exists( 'gc_collect_cycles' ) ) {
				gc_collect_cycles();
			}
		}

		$write( "</products>\n" );
		gzclose( $gz );

		// Sidecar size file. Written next to the tmp.gz so an atomic rename
		// from tmp → final keeps both files in sync.
		file_put_contents( $tmp_file . '.size', (string) $uncompressed ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
	}

	/* -----------------------------------------------------------------
	 * XML field builders
	 * ----------------------------------------------------------------- */

	/**
	 * Build a single product's XML block.
	 *
	 * @param \WC_Product $p Product.
	 * @return string
	 */
	private function product_xml( \WC_Product $p ) {
		$out = '';
		if ( $p->is_type( 'variable' ) ) {
			$out .= "  <product type=\"variable\" id=\"{$p->get_id()}\">\n";
			$out .= $this->common_fields( $p );
			$out .= '    <price_min>' . self::x( $p->get_variation_price( 'min' ) ) . "</price_min>\n";
			$out .= '    <price_max>' . self::x( $p->get_variation_price( 'max' ) ) . "</price_max>\n";
			$out .= '    <regular_price_min>' . self::x( $p->get_variation_regular_price( 'min' ) ) . "</regular_price_min>\n";
			$out .= '    <regular_price_max>' . self::x( $p->get_variation_regular_price( 'max' ) ) . "</regular_price_max>\n";
			$out .= '    <on_sale>' . ( $p->is_on_sale() ? 'yes' : 'no' ) . "</on_sale>\n";

			$total_stock  = 0;
			$any_in_stock = false;
			$out         .= "    <variations>\n";
			$children     = $p->get_children();
			if ( ! empty( $children ) ) {
				_prime_post_caches( $children, true, true );
			}
			foreach ( $children as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v ) {
					continue;
				}
				$qty = $v->get_stock_quantity();
				if ( null !== $qty ) {
					$total_stock += max( 0, (int) $qty );
				}
				if ( $v->is_in_stock() ) {
					$any_in_stock = true;
				}
				$out .= $this->variation_xml( $v );
				wp_cache_delete( $vid, 'posts' );
			}
			$out .= "    </variations>\n";
			$out .= "    <total_stock>{$total_stock}</total_stock>\n";
			$out .= '    <any_in_stock>' . ( $any_in_stock ? 'yes' : 'no' ) . "</any_in_stock>\n";
			$out .= "  </product>\n";
			return $out;
		}

		$out .= '  <product type="' . esc_attr( $p->get_type() ) . '" id="' . (int) $p->get_id() . "\">\n";
		$out .= $this->common_fields( $p );
		$out .= $this->stock_fields( $p );
		$out .= $this->price_fields( $p );
		$out .= "  </product>\n";
		return $out;
	}

	/**
	 * Common XML fields shared by every product type.
	 *
	 * @param \WC_Product $p Product.
	 * @return string
	 */
	private function common_fields( \WC_Product $p ) {
		$dc  = $p->get_date_created();
		$dm  = $p->get_date_modified();
		$out = '';
		$out .= '    <sku>' . self::x( $p->get_sku() ) . "</sku>\n";
		$out .= '    <name>' . self::x( $p->get_name() ) . "</name>\n";
		$out .= '    <slug>' . self::x( $p->get_slug() ) . "</slug>\n";
		$out .= '    <url>' . self::x( get_permalink( $p->get_id() ) ) . "</url>\n";
		$out .= '    <status>' . self::x( $p->get_status() ) . "</status>\n";
		$out .= '    <visibility>' . self::x( $p->get_catalog_visibility() ) . "</visibility>\n";
		$out .= '    <description>' . self::x( wp_strip_all_tags( $p->get_description() ) ) . "</description>\n";
		$out .= '    <short_desc>' . self::x( wp_strip_all_tags( $p->get_short_description() ) ) . "</short_desc>\n";
		$out .= '    <date_created>' . self::x( $dc ? $dc->date( 'c' ) : '' ) . "</date_created>\n";
		$out .= '    <date_modified>' . self::x( $dm ? $dm->date( 'c' ) : '' ) . "</date_modified>\n";
		$out .= '    <weight>' . self::x( $p->get_weight() ) . "</weight>\n";
		$out .= '    <length>' . self::x( $p->get_length() ) . "</length>\n";
		$out .= '    <width>' . self::x( $p->get_width() ) . "</width>\n";
		$out .= '    <height>' . self::x( $p->get_height() ) . "</height>\n";
		$out .= '    <weight_unit>' . self::x( get_option( 'woocommerce_weight_unit' ) ) . "</weight_unit>\n";
		$out .= '    <dim_unit>' . self::x( get_option( 'woocommerce_dimension_unit' ) ) . "</dim_unit>\n";
		$out .= '    <tax_class>' . self::x( $p->get_tax_class() ) . "</tax_class>\n";
		$out .= '    <tax_status>' . self::x( $p->get_tax_status() ) . "</tax_status>\n";

		$cats = get_the_terms( $p->get_id(), 'product_cat' );
		$cats = is_array( $cats ) ? $cats : array();
		$out .= "    <categories>\n";
		foreach ( $cats as $cat ) {
			$out .= '      <category id="' . (int) $cat->term_id . '" slug="' . esc_attr( $cat->slug ) . '">' . self::x( $cat->name ) . "</category>\n";
		}
		$out .= "    </categories>\n";

		$tags = get_the_terms( $p->get_id(), 'product_tag' );
		$tags = is_array( $tags ) ? $tags : array();
		$out .= "    <tags>\n";
		foreach ( $tags as $tag ) {
			$out .= '      <tag slug="' . esc_attr( $tag->slug ) . '">' . self::x( $tag->name ) . "</tag>\n";
		}
		$out .= "    </tags>\n";

		$out .= "    <images>\n";
		$img  = $p->get_image_id();
		if ( $img ) {
			$out .= '      <image role="main">' . self::x( wp_get_attachment_image_url( $img, 'full' ) ) . "</image>\n";
		}
		foreach ( $p->get_gallery_image_ids() as $gid ) {
			$out .= '      <image role="gallery">' . self::x( wp_get_attachment_image_url( $gid, 'full' ) ) . "</image>\n";
		}
		$out .= "    </images>\n";

		$out .= "    <attributes>\n";
		foreach ( $p->get_attributes() as $attr ) {
			$name   = $attr->get_name();
			$label  = wc_attribute_label( $name );
			$values = $attr->is_taxonomy()
				? wc_get_product_terms( $p->get_id(), $name, array( 'fields' => 'names' ) )
				: $attr->get_options();
			$out   .= '      <attribute name="' . esc_attr( $name ) . '" label="' . esc_attr( $label ) . '" variation="' . ( $attr->get_variation() ? 'yes' : 'no' ) . "\">\n";
			foreach ( (array) $values as $v ) {
				$out .= '        <value>' . self::x( $v ) . "</value>\n";
			}
			$out .= "      </attribute>\n";
		}
		$out .= "    </attributes>\n";
		return $out;
	}

	/**
	 * Stock XML fields (simple products).
	 *
	 * @param \WC_Product $p Product.
	 * @return string
	 */
	private function stock_fields( \WC_Product $p ) {
		return '    <in_stock>' . ( $p->is_in_stock() ? 'yes' : 'no' ) . "</in_stock>\n"
			. '    <stock_qty>' . self::x( (string) ( $p->get_stock_quantity() ?? '' ) ) . "</stock_qty>\n"
			. '    <stock_status>' . self::x( $p->get_stock_status() ) . "</stock_status>\n"
			. '    <manage_stock>' . ( $p->get_manage_stock() ? 'yes' : 'no' ) . "</manage_stock>\n"
			. '    <backorders_allowed>' . ( $p->backorders_allowed() ? 'yes' : 'no' ) . "</backorders_allowed>\n"
			. '    <low_stock_amount>' . self::x( (string) ( $p->get_low_stock_amount() ?? '' ) ) . "</low_stock_amount>\n";
	}

	/**
	 * Price XML fields (simple products).
	 *
	 * @param \WC_Product $p Product.
	 * @return string
	 */
	private function price_fields( \WC_Product $p ) {
		$sf = $p->get_date_on_sale_from();
		$st = $p->get_date_on_sale_to();
		return '    <price>' . self::x( $p->get_price() ) . "</price>\n"
			. '    <regular_price>' . self::x( $p->get_regular_price() ) . "</regular_price>\n"
			. '    <sale_price>' . self::x( $p->get_sale_price() ) . "</sale_price>\n"
			. '    <on_sale>' . ( $p->is_on_sale() ? 'yes' : 'no' ) . "</on_sale>\n"
			. '    <sale_from>' . self::x( $sf ? $sf->date( 'c' ) : '' ) . "</sale_from>\n"
			. '    <sale_to>' . self::x( $st ? $st->date( 'c' ) : '' ) . "</sale_to>\n"
			. '    <currency>' . self::x( get_woocommerce_currency() ) . "</currency>\n";
	}

	/**
	 * Variation XML block.
	 *
	 * @param \WC_Product_Variation $v Variation.
	 * @return string
	 */
	private function variation_xml( \WC_Product_Variation $v ) {
		$out  = '      <variation id="' . (int) $v->get_id() . "\">\n";
		$out .= '        <sku>' . self::x( $v->get_sku() ) . "</sku>\n";
		$out .= '        <description>' . self::x( wp_strip_all_tags( $v->get_description() ) ) . "</description>\n";
		$out .= "        <attributes>\n";
		foreach ( $v->get_variation_attributes() as $key => $slug ) {
			$tax   = str_replace( 'attribute_', '', $key );
			$label = wc_attribute_label( $tax );
			if ( taxonomy_exists( $tax ) && $slug ) {
				$term = get_term_by( 'slug', $slug, $tax );
				$slug = $term ? $term->name : $slug;
			}
			$out .= '          <attribute label="' . esc_attr( $label ) . '">' . self::x( $slug ) . "</attribute>\n";
		}
		$out .= "        </attributes>\n";
		$out .= '        <in_stock>' . ( $v->is_in_stock() ? 'yes' : 'no' ) . "</in_stock>\n";
		$out .= '        <stock_qty>' . self::x( (string) ( $v->get_stock_quantity() ?? '' ) ) . "</stock_qty>\n";
		$out .= '        <stock_status>' . self::x( $v->get_stock_status() ) . "</stock_status>\n";
		$out .= '        <manage_stock>' . ( $v->get_manage_stock() ? 'yes' : 'no' ) . "</manage_stock>\n";
		$out .= '        <backorders_allowed>' . ( $v->backorders_allowed() ? 'yes' : 'no' ) . "</backorders_allowed>\n";
		$out .= '        <price>' . self::x( $v->get_price() ) . "</price>\n";
		$out .= '        <regular_price>' . self::x( $v->get_regular_price() ) . "</regular_price>\n";
		$out .= '        <sale_price>' . self::x( $v->get_sale_price() ) . "</sale_price>\n";
		$out .= '        <on_sale>' . ( $v->is_on_sale() ? 'yes' : 'no' ) . "</on_sale>\n";
		$out .= '        <currency>' . self::x( get_woocommerce_currency() ) . "</currency>\n";
		$out .= '        <weight>' . self::x( $v->get_weight() ) . "</weight>\n";
		$out .= '        <length>' . self::x( $v->get_length() ) . "</length>\n";
		$out .= '        <width>' . self::x( $v->get_width() ) . "</width>\n";
		$out .= '        <height>' . self::x( $v->get_height() ) . "</height>\n";
		$img  = $v->get_image_id();
		if ( $img ) {
			$out .= '        <image>' . self::x( wp_get_attachment_image_url( $img, 'full' ) ) . "</image>\n";
		}
		$out .= "      </variation>\n";
		return $out;
	}

	/**
	 * Escape a value for XML text output.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function x( $value ) {
		return htmlspecialchars( (string) ( $value ?? '' ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}
