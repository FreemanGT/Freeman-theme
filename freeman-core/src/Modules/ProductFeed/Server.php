<?php
/**
 * Product Feed — HTTP server.
 *
 * Registers the /product-feed rewrite rule and streams the gzipped feed
 * file back with the right headers. Extracted from Module.php in 1.4.0
 * so the HTTP surface can evolve without touching the generator's
 * internals.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ProductFeed;

defined( 'ABSPATH' ) || exit;

/**
 * Feed server.
 */
final class Server {

	const REWRITE_SLUG = 'product-feed';
	const QUERY_VAR    = 'freeman_core_product_feed';

	/**
	 * Deprecated query var name — kept registered for one release cycle so
	 * any rewrite rules still cached in the WP options table from before the
	 * 1.9.0 rename continue to resolve until `flush_rewrite_rules()` runs
	 * (Migrations does this on update). Removed in 2.0.0.
	 */
	const DEPRECATED_QUERY_VAR = 'freeman_productfeed';

	/**
	 * Generator reference (for on-miss regeneration + file path resolution).
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Construct.
	 *
	 * @param Generator $generator Generator.
	 */
	public function __construct( Generator $generator ) {
		$this->generator = $generator;
	}

	/**
	 * Register the /product-feed rewrite rule.
	 */
	public function register_rewrite() {
		add_rewrite_rule( '^' . self::REWRITE_SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register the query var so WP::parse_request keeps it.
	 *
	 * @param array $vars Vars.
	 * @return array
	 */
	public function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::DEPRECATED_QUERY_VAR;
		return $vars;
	}

	/**
	 * Public URL of the feed endpoint.
	 *
	 * @return string
	 */
	public function public_url() {
		return home_url( '/' . self::REWRITE_SLUG );
	}

	/**
	 * Serve the feed — called from template_redirect.
	 */
	public function serve_feed() {
		if ( ! get_query_var( self::QUERY_VAR ) && ! get_query_var( self::DEPRECATED_QUERY_VAR ) ) {
			return;
		}

		/**
		 * Fires when a request is about to be served from the /product-feed
		 * endpoint, before any headers are emitted. Use cases: custom auth
		 * gate, request logging, rate limiting, cache-purge integration.
		 *
		 * Does NOT fire when the rewrite rule misses (the early return above).
		 *
		 * @since 1.11.1
		 */
		do_action( 'freeman_core/product_feed/before_serve' );

		$file = $this->generator->feed_file();
		if ( ! file_exists( $file ) ) {
			$this->generator->generate();
		}
		if ( ! file_exists( $file ) ) {
			status_header( 503 );
			nocache_headers();
			echo 'Feed not available yet. Please try again in a few seconds.'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		$accept_header = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) : '';
		$accepts_gzip  = false !== strpos( $accept_header, 'gzip' );

		header( 'Content-Type: application/xml; charset=UTF-8' );
		if ( $accepts_gzip ) {
			header( 'Content-Encoding: gzip' );
			header( 'Content-Length: ' . filesize( $file ) );
		} else {
			// Decompressed branch — emit Content-Length when the sidecar
			// size file is available (M-02). Cached at generation time so
			// we don't re-read the whole gzip on every request.
			$uncompressed = $this->generator->uncompressed_size();
			if ( null !== $uncompressed ) {
				header( 'Content-Length: ' . $uncompressed );
			}
		}
		header( 'Content-Disposition: inline; filename="products.xml"' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $file ) ) . ' GMT' );
		header( 'Cache-Control: public, max-age=300' );
		header( 'X-Feed-Generated: ' . (string) get_option( Generator::OPT_LAST_GEN, 'unknown' ) );
		header( 'X-Robots-Tag: noindex' );

		if ( $accepts_gzip ) {
			readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		} else {
			$gz = gzopen( $file, 'rb' );
			while ( ! gzeof( $gz ) ) {
				echo gzread( $gz, 65536 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			gzclose( $gz );
		}
		exit;
	}
}
