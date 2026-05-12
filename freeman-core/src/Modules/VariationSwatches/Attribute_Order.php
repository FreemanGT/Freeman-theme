<?php
/**
 * Re-orders variation-attribute option lists for the PDP swatch picker.
 *
 * WC_Product_Variable::get_variation_attributes() returns each attribute's
 * values in raw database row order — the variable-product data store SELECTs
 * `meta_value` from postmeta with no ORDER BY, then array_unique() keeps
 * first-appearance order. That's why sizes (S/M/L/XL) and numeric values
 * (23/25/27/28/32) come out scrambled on the front end: the order matches
 * the order variations happened to be created in, nothing the merchant set.
 *
 * This helper re-sorts each attribute's option list to a sensible,
 * merchant-controllable order and then demotes out-of-stock values to the
 * end so the in-stock ones read first. Precedence, per attribute:
 *
 *   1. If every value is numeric -> ascending by float value.
 *   2. Else, the order the merchant already controls:
 *        - taxonomy attribute (pa_*): wc_get_product_terms() order, which
 *          honours the attribute's "Default sort order" setting
 *          (custom ordering / name / name (numeric) / term id);
 *        - custom product attribute: the order typed into the product's
 *          Attributes tab (WC_Product_Attribute::get_options()).
 *      Any value not present in that reference order is appended at the end.
 *   3. In-stock values first, out-of-stock values last, preserving the
 *      within-bucket order from steps 1-2. WC's "any value" convention is
 *      honoured: a variation whose attribute value is an empty string
 *      matches every option, so an in-stock "any" variation keeps every
 *      value on that attribute in the in-stock bucket. If every value is
 *      out of stock the list is left untouched (mirrors the existing
 *      Etucart_VS_Plugin::filter_in_stock_only() "all sold out" behaviour).
 *
 * Pure data transform — no hooks, no option reads beyond what WC already
 * exposes on the product. Lives outside legacy/ on purpose; the single call
 * site in Etucart_VS_Frontend::render_variable() is a Hard Rule #3
 * micro-exception (one line), approved with the 1.11.50 plan.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\VariationSwatches;

defined( 'ABSPATH' ) || exit;

/**
 * Variation-attribute option ordering.
 */
final class Attribute_Order {

	/**
	 * Re-order every attribute's option list.
	 *
	 * @param array  $attributes           From WC_Product_Variable::get_variation_attributes()
	 *                                     ( attribute_name => list<string> ).
	 * @param object $product              WC_Product (needs get_id() / get_attributes()).
	 * @param array  $available_variations From WC_Product_Variable::get_available_variations().
	 * @return array Same shape as $attributes, each option list re-ordered.
	 */
	public static function reorder( array $attributes, $product, array $available_variations ): array {
		if ( empty( $attributes ) ) {
			return $attributes;
		}

		list( $in_stock_values, $any_in_stock ) = self::collect_in_stock( $available_variations );

		$out = array();
		foreach ( $attributes as $attribute_name => $options ) {
			$options = array_values( array_map( 'strval', (array) $options ) );
			if ( count( $options ) < 2 ) {
				$out[ $attribute_name ] = $options;
				continue;
			}

			$ordered = self::base_order( (string) $attribute_name, $options, $product );
			$ordered = self::demote_out_of_stock( (string) $attribute_name, $ordered, $in_stock_values, $any_in_stock );

			$out[ $attribute_name ] = $ordered;
		}

		return $out;
	}

	/**
	 * Steps 1-2: numeric ascending if every value parses as a number,
	 * otherwise the merchant-configured order with unknowns appended.
	 *
	 * @param string $attribute_name Raw variation-attribute name (taxonomy slug or label).
	 * @param array  $options        Option values for the attribute, list<string>.
	 * @param object $product        WC_Product.
	 * @return array Re-ordered list<string>.
	 */
	private static function base_order( string $attribute_name, array $options, $product ): array {
		$all_numeric = true;
		foreach ( $options as $value ) {
			$trimmed = trim( $value );
			if ( '' === $trimmed || ! is_numeric( $trimmed ) ) {
				$all_numeric = false;
				break;
			}
		}
		if ( $all_numeric ) {
			usort(
				$options,
				static function ( $a, $b ) {
					return (float) $a <=> (float) $b;
				}
			);
			return $options;
		}

		$reference = self::reference_order( $attribute_name, $product );
		if ( empty( $reference ) ) {
			return $options;
		}

		$rank = array();
		foreach ( $reference as $i => $value ) {
			$value = (string) $value;
			if ( ! isset( $rank[ $value ] ) ) {
				$rank[ $value ] = $i;
			}
		}

		$known   = array();
		$unknown = array();
		foreach ( $options as $value ) {
			if ( isset( $rank[ $value ] ) ) {
				$known[] = $value;
			} else {
				$unknown[] = $value;
			}
		}
		usort(
			$known,
			static function ( $a, $b ) use ( $rank ) {
				return $rank[ $a ] <=> $rank[ $b ];
			}
		);

		return array_merge( $known, $unknown );
	}

	/**
	 * The merchant-controlled value order for an attribute, or [] if it
	 * can't be determined.
	 *
	 * @param string $attribute_name Raw variation-attribute name.
	 * @param object $product        WC_Product.
	 * @return array Reference order as list<string>, or empty if undeterminable.
	 */
	private static function reference_order( string $attribute_name, $product ): array {
		// Global product attributes (pa_*) are always taxonomies.
		// wc_get_product_terms() applies the attribute's configured orderby
		// and itself returns [] if the taxonomy doesn't exist, so the
		// prefix check plus a function_exists guard is enough.
		if ( 0 === strpos( $attribute_name, 'pa_' ) && function_exists( 'wc_get_product_terms' ) && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$slugs = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'slugs' ) );
			return is_array( $slugs ) ? array_map( 'strval', $slugs ) : array();
		}

		// Custom product attribute: the order typed into the product editor.
		if ( is_object( $product ) && method_exists( $product, 'get_attributes' ) && function_exists( 'sanitize_title' ) ) {
			$want = sanitize_title( $attribute_name );
			foreach ( (array) $product->get_attributes() as $key => $attribute ) {
				$matches = ( sanitize_title( (string) $key ) === $want )
					|| ( is_object( $attribute ) && method_exists( $attribute, 'get_name' ) && sanitize_title( (string) $attribute->get_name() ) === $want );
				if ( ! $matches ) {
					continue;
				}
				if ( is_object( $attribute ) && method_exists( $attribute, 'get_options' ) ) {
					return array_map( 'strval', (array) $attribute->get_options() );
				}
				break;
			}
		}

		return array();
	}

	/**
	 * Step 3: in-stock values first, out-of-stock last, stable within
	 * buckets. Returns the input unchanged when there's no usable stock
	 * info or when every value is out of stock.
	 *
	 * @param string $attribute_name Raw variation-attribute name.
	 * @param array  $ordered         Values already in base order, list<string>.
	 * @param array  $in_stock_values Map of input_key => array<string,true>.
	 * @param array  $any_in_stock    Map of input_key => true (has an in-stock "any" variation).
	 * @return array Re-ordered list<string>.
	 */
	private static function demote_out_of_stock( string $attribute_name, array $ordered, array $in_stock_values, array $any_in_stock ): array {
		$key = 'attribute_' . ( function_exists( 'sanitize_title' ) ? sanitize_title( $attribute_name ) : $attribute_name );

		if ( ! empty( $any_in_stock[ $key ] ) ) {
			return $ordered;
		}
		$allowed = isset( $in_stock_values[ $key ] ) ? $in_stock_values[ $key ] : array();
		if ( empty( $allowed ) ) {
			return $ordered;
		}

		$in  = array();
		$oos = array();
		foreach ( $ordered as $value ) {
			if ( isset( $allowed[ $value ] ) ) {
				$in[] = $value;
			} else {
				$oos[] = $value;
			}
		}
		if ( empty( $in ) ) {
			return $ordered;
		}

		return array_merge( $in, $oos );
	}

	/**
	 * Index the in-stock + purchasable variations.
	 *
	 * @param array $available_variations From WC_Product_Variable::get_available_variations().
	 * @return array{0: array<string,array<string,true>>, 1: array<string,true>}
	 *               [ input_key => [ value => true ], input_key => true (has an in-stock "any" variation) ]
	 */
	private static function collect_in_stock( array $available_variations ): array {
		$values = array();
		$any    = array();

		foreach ( $available_variations as $variation ) {
			if ( ! is_array( $variation ) || empty( $variation['is_in_stock'] ) || empty( $variation['is_purchasable'] ) ) {
				continue;
			}
			if ( empty( $variation['attributes'] ) || ! is_array( $variation['attributes'] ) ) {
				continue;
			}
			foreach ( $variation['attributes'] as $input_key => $value ) {
				$input_key = (string) $input_key;
				$value     = (string) $value;
				if ( '' === $value ) {
					$any[ $input_key ] = true;
					continue;
				}
				if ( ! isset( $values[ $input_key ] ) ) {
					$values[ $input_key ] = array();
				}
				$values[ $input_key ][ $value ] = true;
			}
		}

		return array( $values, $any );
	}
}
