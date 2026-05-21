<?php
/**
 * Shop Filters URL state — parse / serialize the filter query string.
 *
 * The on-page convention is plain query-string params (no rewrite rules):
 *   ?filter_pa_color=red,blue&filter_pa_size=m&min_price=10&max_price=50&orderby=price&paged=2
 *
 * Pure string handling: no WordPress functions, so it's fully unit-testable and
 * the same logic can be mirrored in JS.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * URL state.
 */
final class Url_State {

	const PREFIX = 'filter_';

	/**
	 * Allowed orderby values (anything else falls back to '').
	 *
	 * @return string[]
	 */
	public static function orderby_whitelist() {
		return array( 'menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc' );
	}

	/**
	 * Parse raw query params into a normalised filter state. Filter values are
	 * CSV slug lists under `filter_<taxonomy>` keys.
	 *
	 * @param array $params Raw query params (e.g. $_GET).
	 * @return array{filters:array<string,string[]>,min_price:?float,max_price:?float,onsale:bool,in_stock:bool,orderby:string,paged:int}
	 */
	public static function parse( array $params ) {
		$filters = array();
		foreach ( $params as $key => $value ) {
			$key = (string) $key;
			if ( 0 !== strpos( $key, self::PREFIX ) ) {
				continue;
			}
			$taxonomy = self::sanitize_taxonomy( substr( $key, strlen( self::PREFIX ) ) );
			if ( '' === $taxonomy ) {
				continue;
			}
			$slugs = array();
			foreach ( explode( ',', (string) $value ) as $raw ) {
				$slug = self::sanitize_slug( $raw );
				if ( '' !== $slug ) {
					$slugs[ $slug ] = true; // dedupe.
				}
			}
			if ( ! empty( $slugs ) ) {
				$filters[ $taxonomy ] = array_keys( $slugs );
			}
		}

		$orderby = isset( $params['orderby'] ) ? strtolower( (string) $params['orderby'] ) : '';
		if ( ! in_array( $orderby, self::orderby_whitelist(), true ) ) {
			$orderby = '';
		}

		return array(
			'filters'   => $filters,
			'min_price' => self::numeric_or_null( $params['min_price'] ?? null ),
			'max_price' => self::numeric_or_null( $params['max_price'] ?? null ),
			'onsale'    => self::truthy( $params['onsale'] ?? null ),
			'in_stock'  => self::truthy( $params['in_stock'] ?? null ),
			'orderby'   => $orderby,
			'paged'     => isset( $params['paged'] ) ? max( 1, (int) $params['paged'] ) : 1,
		);
	}

	/**
	 * Serialize a filter state back into a flat query-param array (omitting
	 * empties / defaults so the URL stays clean).
	 *
	 * @param array $state State as returned by parse().
	 * @return array<string,string>
	 */
	public static function serialize( array $state ) {
		$params = array();

		$filters = isset( $state['filters'] ) && is_array( $state['filters'] ) ? $state['filters'] : array();
		foreach ( $filters as $taxonomy => $slugs ) {
			$taxonomy = self::sanitize_taxonomy( (string) $taxonomy );
			$clean    = array();
			foreach ( (array) $slugs as $slug ) {
				$slug = self::sanitize_slug( $slug );
				if ( '' !== $slug ) {
					$clean[ $slug ] = true;
				}
			}
			if ( '' !== $taxonomy && ! empty( $clean ) ) {
				$params[ self::PREFIX . $taxonomy ] = implode( ',', array_keys( $clean ) );
			}
		}

		if ( isset( $state['min_price'] ) && null !== $state['min_price'] ) {
			$params['min_price'] = (string) ( $state['min_price'] + 0 );
		}
		if ( isset( $state['max_price'] ) && null !== $state['max_price'] ) {
			$params['max_price'] = (string) ( $state['max_price'] + 0 );
		}
		if ( ! empty( $state['onsale'] ) ) {
			$params['onsale'] = '1';
		}
		if ( ! empty( $state['in_stock'] ) ) {
			$params['in_stock'] = '1';
		}
		if ( ! empty( $state['orderby'] ) && in_array( $state['orderby'], self::orderby_whitelist(), true ) ) {
			$params['orderby'] = (string) $state['orderby'];
		}
		if ( isset( $state['paged'] ) && (int) $state['paged'] > 1 ) {
			$params['paged'] = (string) (int) $state['paged'];
		}

		return $params;
	}

	/**
	 * Lowercase, hyphen/underscore + alphanumeric slug.
	 *
	 * @param string $raw Raw value.
	 * @return string
	 */
	private static function sanitize_slug( $raw ) {
		$slug = strtolower( trim( (string) $raw ) );
		return (string) preg_replace( '/[^a-z0-9_-]/', '', $slug );
	}

	/**
	 * Sanitize a taxonomy key (e.g. pa_color, product_cat).
	 *
	 * @param string $raw Raw value.
	 * @return string
	 */
	private static function sanitize_taxonomy( $raw ) {
		$tax = strtolower( trim( (string) $raw ) );
		return (string) preg_replace( '/[^a-z0-9_]/', '', $tax );
	}

	/**
	 * @param mixed $value Value.
	 * @return float|null
	 */
	private static function numeric_or_null( $value ) {
		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function truthy( $value ) {
		return in_array( (string) $value, array( '1', 'true', 'yes', 'on' ), true );
	}
}
