<?php
/**
 * Shop Filters SEO policy for filtered URLs (decisions §5.6).
 *
 * A filtered archive (e.g. ?filter_pa_color=red) is a thin near-duplicate of
 * its clean category/shop page, so for those URLs we emit `noindex,follow` and
 * canonicalise to the clean archive — keeping the clean page indexable and
 * stopping search engines hoarding filter permutations. Query-string only, no
 * rewrite rules (so it stays additive), and gated by a default-off flag so an
 * SEO-plugin-governed site can opt out.
 *
 * We route through whichever SEO plugin is active (RankMath / SEOPress / Yoast)
 * rather than emit a second canonical/robots tag alongside it; with no plugin
 * we fall back to WordPress core. Detection happens once at register().
 *
 * The decision logic (is_filtered_state / clean_url / the robots mutations) is
 * pure and unit-tested; the context guards and tag emission are live-QA.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Filtered-URL SEO policy.
 */
final class Seo {

	const ROBOTS_NOINDEX = 'noindex, follow';

	/**
	 * Attach to the active SEO plugin's canonical/robots filters, or core.
	 */
	public function register() {
		$attached = false;

		if ( class_exists( 'RankMath' ) ) {
			add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_canonical' ) );
			add_filter( 'rank_math/frontend/robots', array( $this, 'filter_robots_index_follow' ) );
			$attached = true;
		}

		if ( defined( 'SEOPRESS_VERSION' ) ) {
			add_filter( 'seopress_titles_canonical', array( $this, 'filter_canonical' ) );
			add_filter( 'seopress_titles_robots', array( $this, 'filter_robots_string' ) );
			$attached = true;
		}

		if ( defined( 'WPSEO_VERSION' ) ) {
			add_filter( 'wpseo_canonical', array( $this, 'filter_canonical' ) );
			add_filter( 'wpseo_robots_array', array( $this, 'filter_robots_index_follow' ) );
			$attached = true;
		}

		if ( ! $attached ) {
			add_filter( 'wp_robots', array( $this, 'filter_robots_core' ) );
			add_action( 'wp_head', array( $this, 'print_canonical' ), 9 );
		}
	}

	/* -----------------------------------------------------------------
	 * Pure decision logic (unit-tested).
	 * ----------------------------------------------------------------- */

	/**
	 * Whether a parsed URL state represents a filtered view. Sort-only
	 * (orderby) and pagination-only (paged) URLs are NOT filtered — only the
	 * facet/price/stock selections count.
	 *
	 * @param array $state State from Url_State::parse().
	 * @return bool
	 */
	public static function is_filtered_state( array $state ) {
		if ( ! empty( $state['filters'] ) ) {
			return true;
		}
		if ( ! empty( $state['price_bands'] ) ) {
			return true;
		}
		if ( isset( $state['min_price'] ) && null !== $state['min_price'] ) {
			return true;
		}
		if ( isset( $state['max_price'] ) && null !== $state['max_price'] ) {
			return true;
		}
		if ( ! empty( $state['onsale'] ) ) {
			return true;
		}
		if ( ! empty( $state['in_stock'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Strip every filter/sort/pagination artefact from a URL, leaving the clean
	 * archive URL (page 1). Unrelated query params and the URL's host/path
	 * formatting (including trailing-slash convention) are preserved, so the
	 * result matches the plugin's own canonical format. Idempotent.
	 *
	 * @param string $url URL to clean.
	 * @return string
	 */
	public static function clean_url( $url ) {
		$url   = (string) $url;
		$parts = parse_url( $url );
		if ( false === $parts ) {
			return $url;
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		// Drop a trailing /page/N/ pagination segment back to page 1.
		$path = (string) preg_replace( '#/page/\d+/?$#', '/', $path );

		$query_pairs = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_pairs );
			foreach ( array_keys( $query_pairs ) as $key ) {
				if ( self::is_filter_param( (string) $key ) ) {
					unset( $query_pairs[ $key ] );
				}
			}
		}

		$clean = '';
		if ( isset( $parts['scheme'], $parts['host'] ) ) {
			$clean = $parts['scheme'] . '://' . $parts['host'];
			if ( isset( $parts['port'] ) ) {
				$clean .= ':' . $parts['port'];
			}
		}
		$clean .= $path;
		if ( ! empty( $query_pairs ) ) {
			$clean .= '?' . http_build_query( $query_pairs );
		}

		return $clean;
	}

	/**
	 * Apply noindex,follow to an index/follow-keyed robots array (RankMath,
	 * Yoast). Other directives are left untouched.
	 *
	 * @param array $robots Robots directives keyed 'index'/'follow'/….
	 * @return array
	 */
	public static function noindex_index_follow( array $robots ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
		return $robots;
	}

	/**
	 * Apply noindex,follow to a core `wp_robots` array (boolean directive keys).
	 *
	 * @param array $robots Core robots directives.
	 * @return array
	 */
	public static function noindex_core( array $robots ) {
		unset( $robots['index'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;
		return $robots;
	}

	/**
	 * Whether a query-param key is a filter/sort/pagination artefact this policy
	 * strips from the canonical URL.
	 *
	 * @param string $key Param key.
	 * @return bool
	 */
	private static function is_filter_param( $key ) {
		if ( 0 === strpos( $key, Url_State::PREFIX ) ) {
			return true;
		}
		return in_array( $key, array( 'min_price', 'max_price', 'onsale', 'in_stock', 'orderby', 'paged' ), true );
	}

	/* -----------------------------------------------------------------
	 * Filter callbacks (context-guarded, live-QA).
	 * ----------------------------------------------------------------- */

	/**
	 * Canonical override: on a filtered shop/category page, point at the clean
	 * archive. Search pages keep their own canonical (decision: search =
	 * noindex only).
	 *
	 * @param string $canonical Canonical URL from the SEO plugin.
	 * @return string
	 */
	public function filter_canonical( $canonical ) {
		if ( ! $this->should_canonical() ) {
			return $canonical;
		}
		return self::clean_url( (string) $canonical );
	}

	/**
	 * Print a canonical link in the core fallback (no SEO plugin emits one on
	 * archives by default).
	 */
	public function print_canonical() {
		if ( ! $this->should_canonical() ) {
			return;
		}
		echo '<link rel="canonical" href="' . esc_url( self::clean_url( $this->current_url() ) ) . '" />' . "\n";
	}

	/**
	 * Robots override for index/follow-keyed arrays (RankMath, Yoast).
	 *
	 * @param mixed $robots Robots directives array.
	 * @return mixed
	 */
	public function filter_robots_index_follow( $robots ) {
		if ( ! is_array( $robots ) || ! $this->should_noindex() ) {
			return $robots;
		}
		return self::noindex_index_follow( $robots );
	}

	/**
	 * Robots override for the core `wp_robots` array.
	 *
	 * @param mixed $robots Core robots directives.
	 * @return mixed
	 */
	public function filter_robots_core( $robots ) {
		if ( ! is_array( $robots ) || ! $this->should_noindex() ) {
			return $robots;
		}
		return self::noindex_core( $robots );
	}

	/**
	 * Robots override for SEOPress's string filter.
	 *
	 * @param mixed $robots Robots meta content string.
	 * @return mixed
	 */
	public function filter_robots_string( $robots ) {
		if ( ! $this->should_noindex() ) {
			return $robots;
		}
		return self::ROBOTS_NOINDEX;
	}

	/* -----------------------------------------------------------------
	 * Context guards.
	 * ----------------------------------------------------------------- */

	/**
	 * Apply noindex on a filtered shop / category / product-search page.
	 *
	 * @return bool
	 */
	private function should_noindex() {
		if ( ! self::is_filtered_state( $this->current_state() ) ) {
			return false;
		}
		return $this->on_filterable_archive() || $this->on_product_search();
	}

	/**
	 * Override the canonical only on a filtered shop / category archive.
	 *
	 * @return bool
	 */
	private function should_canonical() {
		if ( ! self::is_filtered_state( $this->current_state() ) ) {
			return false;
		}
		return $this->on_filterable_archive();
	}

	/**
	 * @return bool
	 */
	private function on_filterable_archive() {
		if ( is_admin() ) {
			return false;
		}
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
	}

	/**
	 * @return bool
	 */
	private function on_product_search() {
		if ( is_admin() ) {
			return false;
		}
		return function_exists( 'is_search' ) && is_search();
	}

	/**
	 * Parsed filter state of the current request.
	 *
	 * @return array
	 */
	private function current_state() {
		$params = ( isset( $_GET ) && is_array( $_GET ) ) ? wp_unslash( $_GET ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only canonical/robots decision; Url_State::parse() sanitises.
		return Url_State::parse( $params );
	}

	/**
	 * Full URL of the current request (core fallback canonical source).
	 *
	 * @return string
	 */
	private function current_url() {
		$scheme = ( function_exists( 'is_ssl' ) && is_ssl() ) ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return $scheme . '://' . $host . $uri;
	}
}
