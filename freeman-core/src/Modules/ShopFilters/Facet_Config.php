<?php
/**
 * Shop Filters facet configuration.
 *
 * Decides which taxonomies become facets, their display type, order, and
 * per-category hiding. Phase 6.2 auto-derives a sane default (every available
 * global attribute as a checkbox facet, product_cat as a category tree); a real
 * admin editing surface for the `freeman_core_shop_filters_facet_config` option
 * lands in Phase 6.4. Two filters let code override the resolution.
 *
 * Pure except for reading its own option + the two filters — fully testable with
 * those mocked.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Facet config.
 */
final class Facet_Config {

	const OPTION = 'freeman_core_shop_filters_facet_config';

	/**
	 * Default facet type for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string 'category' | 'checkbox'.
	 */
	public static function default_type_for( $taxonomy ) {
		return 'product_cat' === $taxonomy ? 'category' : 'checkbox';
	}

	/**
	 * Auto-derived default facet definitions from the available taxonomies.
	 *
	 * @param string[] $available_taxonomies e.g. ['product_cat','pa_color','pa_size'].
	 * @return array
	 */
	public static function defaults( array $available_taxonomies ) {
		$defs  = array();
		$order = 0;
		foreach ( $available_taxonomies as $taxonomy ) {
			$taxonomy = (string) $taxonomy;
			if ( '' === $taxonomy ) {
				continue;
			}
			$defs[] = array(
				'taxonomy'           => $taxonomy,
				'type'               => self::default_type_for( $taxonomy ),
				'enabled'            => true,
				'order'              => $order++,
				'hide_on_categories' => array(),
			);
		}
		return $defs;
	}

	/**
	 * Resolve the ordered, visible facet definitions for a context.
	 *
	 * @param string[] $available_taxonomies Taxonomies present on the catalogue.
	 * @param int      $context_category_id  Current product_cat term id (0 = shop).
	 * @return array
	 */
	public static function resolve( array $available_taxonomies, $context_category_id = 0 ) {
		$context_category_id = (int) $context_category_id;
		$defs                = self::merge( self::saved(), self::defaults( $available_taxonomies ) );

		/**
		 * Filter the full facet-definition list before visibility resolution.
		 *
		 * @since 1.12.1
		 *
		 * @param array    $defs                 Facet definitions.
		 * @param int      $context_category_id  Current category (0 = shop).
		 * @param string[] $available_taxonomies Available taxonomies.
		 */
		$defs = apply_filters( 'freeman_core/shop_filters/facet_config', $defs, $context_category_id, $available_taxonomies );

		$visible = array();
		foreach ( (array) $defs as $def ) {
			if ( empty( $def['taxonomy'] ) ) {
				continue;
			}
			$is_visible = ! empty( $def['enabled'] );

			if ( $is_visible && ! empty( $def['hide_on_categories'] ) ) {
				$hidden = array_map( 'intval', (array) $def['hide_on_categories'] );
				if ( in_array( $context_category_id, $hidden, true ) ) {
					$is_visible = false;
				}
			}

			/**
			 * Filter whether a single facet is visible in the current context.
			 * Lets code hide a facet that doesn't apply to a category (req #1)
			 * beyond the static hide_on_categories config.
			 *
			 * @since 1.12.1
			 *
			 * @param bool   $is_visible          Resolved visibility.
			 * @param string $taxonomy            Facet taxonomy.
			 * @param int    $context_category_id Current category (0 = shop).
			 */
			$is_visible = (bool) apply_filters( 'freeman_core/shop_filters/is_facet_visible', $is_visible, (string) $def['taxonomy'], $context_category_id );

			if ( $is_visible ) {
				$visible[] = $def;
			}
		}

		usort(
			$visible,
			static function ( $a, $b ) {
				return (int) ( $a['order'] ?? 0 ) <=> (int) ( $b['order'] ?? 0 );
			}
		);

		return $visible;
	}

	/**
	 * Saved (admin-configured) facet definitions, or empty.
	 *
	 * @return array
	 */
	private static function saved() {
		$config = get_option( self::OPTION, array() );
		return is_array( $config ) ? $config : array();
	}

	/**
	 * Merge saved definitions over the auto-derived defaults, keyed by taxonomy.
	 * Saved values win; defaults fill the gaps; saved-only taxonomies (still
	 * present on the catalogue) are appended.
	 *
	 * @param array $saved    Saved definitions.
	 * @param array $defaults Default definitions.
	 * @return array
	 */
	private static function merge( array $saved, array $defaults ) {
		if ( empty( $saved ) ) {
			return $defaults;
		}

		$saved_by_tax = array();
		foreach ( $saved as $def ) {
			if ( ! empty( $def['taxonomy'] ) ) {
				$saved_by_tax[ (string) $def['taxonomy'] ] = $def;
			}
		}

		$merged = array();
		$seen   = array();
		foreach ( $defaults as $def ) {
			$taxonomy = $def['taxonomy'];
			if ( isset( $saved_by_tax[ $taxonomy ] ) ) {
				$merged[]        = array_merge( $def, $saved_by_tax[ $taxonomy ] );
				$seen[ $taxonomy ] = true;
			} else {
				$merged[] = $def;
			}
		}
		foreach ( $saved as $def ) {
			$taxonomy = isset( $def['taxonomy'] ) ? (string) $def['taxonomy'] : '';
			if ( '' !== $taxonomy && empty( $seen[ $taxonomy ] ) ) {
				$merged[] = array_merge(
					array(
						'type'               => self::default_type_for( $taxonomy ),
						'enabled'            => true,
						'order'              => 999,
						'hide_on_categories' => array(),
					),
					$def
				);
			}
		}

		return $merged;
	}
}
