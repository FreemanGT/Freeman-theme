<?php
/**
 * Pure attribute/term helpers for Shop Filters.
 *
 * Deliberately self-contained rather than calling VariationSwatches'
 * Etucart_VS_Plugin: that legacy class only loads when the Variation Swatches
 * module is enabled, so calling it would couple Shop Filters to an unrelated
 * module's enabled-state. Per decisions §4.6 ("duplicate, don't extract") the
 * small pure functions we need are copied here. (Swatch term colours, when the
 * colour facet lands in a later phase, are read directly via get_term_meta()
 * — data, not code — for the same reason.)
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Pure term helpers.
 */
final class Term_Helpers {

	/**
	 * Build a per-attribute map of which option values are carried by at least
	 * one in-stock, purchasable variation. Honours WooCommerce's "any value"
	 * convention (an empty-string value means the variation matches every value
	 * of that attribute). Pure — operates on the array shape returned by
	 * WC_Product_Variable::get_available_variations().
	 *
	 * @param array $available_variations Variation payloads.
	 * @return array{values:array<string,array<string,bool>>,any:array<string,bool>}
	 *               'values': input_key => [ option_value => true ] for in-stock values.
	 *               'any':    input_key => true when an in-stock "any" variation exists.
	 */
	public static function in_stock_values_by_attribute( array $available_variations ) {
		$values = array();
		$any    = array();

		foreach ( $available_variations as $variation ) {
			if ( ! is_array( $variation ) || empty( $variation['is_in_stock'] ) || empty( $variation['is_purchasable'] ) ) {
				continue;
			}
			$attrs = isset( $variation['attributes'] ) && is_array( $variation['attributes'] )
				? $variation['attributes']
				: array();

			foreach ( $attrs as $input_key => $value ) {
				$input_key = (string) $input_key;
				$value     = (string) $value;
				if ( '' === $value ) {
					$any[ $input_key ] = true;
				} else {
					if ( ! isset( $values[ $input_key ] ) ) {
						$values[ $input_key ] = array();
					}
					$values[ $input_key ][ $value ] = true;
				}
			}
		}

		return array(
			'values' => $values,
			'any'    => $any,
		);
	}

	/**
	 * Whether a given attribute option value is carried by an in-stock variation,
	 * given the map produced by in_stock_values_by_attribute(). An "any" match on
	 * the attribute counts for every value. Pure.
	 *
	 * @param array  $map       Map from in_stock_values_by_attribute().
	 * @param string $input_key Variation attribute input key, e.g. 'attribute_pa_color'.
	 * @param string $value     Option value (slug).
	 * @return bool
	 */
	public static function value_in_stock( array $map, $input_key, $value ) {
		$input_key = (string) $input_key;
		$value     = (string) $value;
		if ( ! empty( $map['any'][ $input_key ] ) ) {
			return true;
		}
		return '' !== $value && ! empty( $map['values'][ $input_key ][ $value ] );
	}
}
