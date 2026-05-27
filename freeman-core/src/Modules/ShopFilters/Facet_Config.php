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

use Freeman\Core\Core\Feature_Flags;

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
	 * Full merged facet definitions (defaults + saved overrides), including
	 * disabled ones — the data source for the admin editing matrix. Unlike
	 * resolve(), this does NOT drop disabled/hidden facets, so the editor can
	 * show and re-enable a facet that's currently turned off.
	 *
	 * @param string[] $available_taxonomies Taxonomies present on the catalogue.
	 * @return array
	 */
	public static function all_defs( array $available_taxonomies ) {
		return self::merge( self::saved(), self::defaults( $available_taxonomies ) );
	}

	/**
	 * Normalise a raw admin-matrix submission into the saved-config shape.
	 * Only known taxonomies survive; everything else is coerced to the expected
	 * type. Pure — no I/O, fully unit-testable.
	 *
	 * Expected $raw shape (from the matrix form):
	 *   [ '<taxonomy>' => [ 'enabled' => '1', 'order' => '2',
	 *                       'hide_on_categories' => [ '42', '7' ] ], ... ]
	 *
	 * @param array    $raw              Raw submission (typically $_POST['facets']).
	 * @param string[] $valid_taxonomies Taxonomies allowed to be configured.
	 * @return array Sanitised facet definitions.
	 */
	public static function sanitize( array $raw, array $valid_taxonomies ) {
		$config         = array();
		$order_fallback = 0;
		foreach ( $valid_taxonomies as $taxonomy ) {
			$taxonomy = (string) $taxonomy;
			if ( '' === $taxonomy ) {
				continue;
			}
			$row  = ( isset( $raw[ $taxonomy ] ) && is_array( $raw[ $taxonomy ] ) ) ? $raw[ $taxonomy ] : array();
			$hide = array();
			if ( ! empty( $row['hide_on_categories'] ) && is_array( $row['hide_on_categories'] ) ) {
				$hide = array_values( array_unique( array_filter( array_map( 'intval', $row['hide_on_categories'] ) ) ) );
			}
			$config[] = array(
				'taxonomy'           => $taxonomy,
				'type'               => self::default_type_for( $taxonomy ),
				'enabled'            => ! empty( $row['enabled'] ),
				'order'              => isset( $row['order'] ) ? (int) $row['order'] : $order_fallback,
				'hide_on_categories' => $hide,
			);
			++$order_fallback;
		}
		return $config;
	}

	/**
	 * Saved (admin-configured) facet definitions, or empty.
	 *
	 * Gated by the `admin_config` flag: when the facet-configuration surface is
	 * off, the saved option is ignored so the storefront falls back to the
	 * auto-derived defaults. This makes flipping the flag off a complete,
	 * one-switch rollback of any saved configuration.
	 *
	 * @return array
	 */
	private static function saved() {
		if ( ! Feature_Flags::is_enabled( 'shop_filters', 'admin_config' ) ) {
			return array();
		}
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
