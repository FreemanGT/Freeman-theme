<?php
/**
 * Plugin bootstrap / service container.
 *
 * @package EtucartVariationSwatches
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Etucart_VS_Plugin' ) ) :

final class Etucart_VS_Plugin {

	/** @var Etucart_VS_Plugin|null */
	private static $instance = null;

	/** @var Etucart_VS_Admin */
	public $admin;

	/** @var Etucart_VS_Frontend */
	public $frontend;

	/** @var Etucart_VS_Ajax */
	public $ajax;

	/** @var Etucart_VS_Settings */
	public $settings;

	/** @var Etucart_VS_Archive */
	public $archive;

	public static function instance(): Etucart_VS_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		// Text-domain (`freeman-core`) is already loaded once by Plugin::load_textdomain();
		// every string in this legacy tree now uses that domain, so a second
		// load here would be redundant AND would point at a non-existent
		// /languages directory inside this module.

		self::maybe_run_migrations();

		$this->admin    = new Etucart_VS_Admin();
		$this->frontend = new Etucart_VS_Frontend();
		$this->ajax     = new Etucart_VS_Ajax();
		$this->settings = new Etucart_VS_Settings();
		$this->archive  = new Etucart_VS_Archive();

		$this->admin->register();
		$this->frontend->register();
		$this->ajax->register();
		$this->settings->register();
		$this->archive->register();
	}

	/**
	 * Run one-shot upgrade migrations. Each migration is gated behind its own
	 * option flag so it runs exactly once per site, regardless of how many
	 * times `boot()` fires.
	 *
	 * 1.6.6 — OOS swap:
	 *   Prior behaviour: PDP hid out-of-stock options by default.
	 *   New behaviour:   PDP shows all options (OOS greyed); shop/archive now
	 *                    hides OOS by default.
	 *   Migration: sites that were running the old default (OPT_PDP_HIDE_OOS
	 *   saved as 'yes') get auto-flipped to 'no' so their PDP experience
	 *   matches the new shipping default. Sites that explicitly chose 'yes'
	 *   and still want that behaviour can toggle it back on in settings.
	 */
	private static function maybe_run_migrations(): void {
		if ( ! class_exists( 'Etucart_VS_Settings' ) ) {
			return;
		}

		$flag_key = 'etucart_vs_migrated_1_6_6';
		if ( (int) get_option( $flag_key, 0 ) >= 1 ) {
			return;
		}

		// Flip only if the option was explicitly saved as 'yes'. Never-saved
		// installs fall through and pick up the new default on first read.
		$existing = \Freeman\Core\Modules\VariationSwatches\Settings_Reader::get( Etucart_VS_Settings::OPT_PDP_HIDE_OOS, null );
		if ( 'yes' === $existing ) {
			update_option( Etucart_VS_Settings::OPT_PDP_HIDE_OOS, 'no' );
		}

		update_option( $flag_key, 1 );
	}

	/**
	 * Filter an (attributes, available_variations) pair down to in-stock,
	 * purchasable variations only. Shared helper used by both the single
	 * product page and the shop / archive picker so the "hide OOS" semantics
	 * are identical across contexts.
	 *
	 * Handles WC's "any value" convention: a variation with an empty string
	 * for an attribute matches every value on that attribute, so we keep all
	 * values of that attribute intact (as long as the "any" variation itself
	 * is in stock).
	 *
	 * If every variation is out of stock, returns the inputs unchanged so
	 * callers can still render the canonical "out of stock" state rather
	 * than a silently empty picker.
	 *
	 * @param array $attributes           From WC_Product_Variable::get_variation_attributes().
	 * @param array $available_variations From WC_Product_Variable::get_available_variations().
	 * @return array{0: array, 1: array} [ filtered_attributes, filtered_variations ]
	 */
	public static function filter_in_stock_only( array $attributes, array $available_variations ): array {
		$in_stock = array_values( array_filter( $available_variations, function ( $v ) {
			return is_array( $v )
				&& ! empty( $v['is_in_stock'] )
				&& ! empty( $v['is_purchasable'] );
		} ) );

		if ( empty( $in_stock ) ) {
			return [ $attributes, $available_variations ];
		}

		$allowed  = []; // input_key => [ value => true ]
		$any_flag = []; // input_key => bool

		foreach ( $in_stock as $v ) {
			if ( empty( $v['attributes'] ) || ! is_array( $v['attributes'] ) ) {
				continue;
			}
			foreach ( $v['attributes'] as $k => $val ) {
				$k   = (string) $k;
				$val = (string) $val;
				if ( '' === $val ) {
					$any_flag[ $k ] = true;
				} else {
					if ( ! isset( $allowed[ $k ] ) ) {
						$allowed[ $k ] = [];
					}
					$allowed[ $k ][ $val ] = true;
				}
			}
		}

		$filtered_attrs = [];
		foreach ( $attributes as $attr_name => $options ) {
			$input_key = 'attribute_' . sanitize_title( $attr_name );

			if ( ! empty( $any_flag[ $input_key ] ) ) {
				$filtered_attrs[ $attr_name ] = $options;
				continue;
			}

			$values = isset( $allowed[ $input_key ] ) ? $allowed[ $input_key ] : [];
			$kept   = [];
			foreach ( (array) $options as $opt ) {
				if ( isset( $values[ (string) $opt ] ) ) {
					$kept[] = $opt;
				}
			}
			if ( ! empty( $kept ) ) {
				$filtered_attrs[ $attr_name ] = $kept;
			}
		}

		if ( empty( $filtered_attrs ) ) {
			return [ $attributes, $available_variations ];
		}

		return [ $filtered_attrs, $in_stock ];
	}

	/**
	 * Get the term-meta key where the hex color is stored for a swatch term.
	 */
	public static function color_meta_key(): string {
		return 'etucart_swatch_color';
	}

	/**
	 * Get the term-meta key where the swatch image's attachment ID is stored.
	 *
	 * Wave 2.2 / 4b (1.11.24). Note this key is namespaced under
	 * `freeman_core_variation_swatches_*` rather than the legacy `etucart_*`
	 * convention used by color_meta_key(). The migration direction established
	 * by 4a is legacy → new (Migrations::migrate_variation_swatches_settings_to_hub
	 * copies etucart_vs_* values into freeman_core_variation_swatches_* keys), so
	 * adding a new term-meta key under the legacy namespace would run against
	 * that grain and create migration debt for any future term-meta sweep.
	 */
	public static function image_meta_key(): string {
		return 'freeman_core_variation_swatches_term_image_id';
	}

	/**
	 * Get the swatch image attachment ID for a term, or 0 if not set.
	 */
	public static function term_image_id( int $term_id ): int {
		$value = get_term_meta( $term_id, self::image_meta_key(), true );
		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Get the swatch image URL for a term at the given size, or empty string
	 * if no image is set or the attachment is missing.
	 *
	 * Filterable via `freeman_core/variation_swatches/term_image_url` so sites
	 * can swap CDN domains, append cache-busting params, etc. Listeners receive
	 * `( string $url, int $term_id, string $size )`.
	 */
	public static function term_image_url( int $term_id, string $size = 'thumbnail' ): string {
		$attachment_id = self::term_image_id( $term_id );
		$url           = '';
		if ( $attachment_id > 0 ) {
			$src = function_exists( 'wp_get_attachment_image_src' )
				? wp_get_attachment_image_src( $attachment_id, $size )
				: false;
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$url = (string) $src[0];
			}
		}

		/**
		 * Filter the resolved swatch image URL.
		 *
		 * @since 1.11.24
		 *
		 * @param string $url      The resolved URL (empty when no image is set).
		 * @param int    $term_id  Attribute term ID.
		 * @param string $size     WordPress image size key (e.g. 'thumbnail').
		 */
		return (string) apply_filters( 'freeman_core/variation_swatches/term_image_url', $url, $term_id, $size );
	}

	/**
	 * Get the term-meta key where the per-term tooltip override is stored.
	 *
	 * Wave 2.2 / 4c (1.11.25). Same canonical-namespace decision as 4b's
	 * image_meta_key() — keeps new term-meta keys in `freeman_core_*` so the
	 * legacy `etucart_*` namespace doesn't grow further.
	 */
	public static function tooltip_meta_key(): string {
		return 'freeman_core_variation_swatches_term_tooltip_text';
	}

	/**
	 * Get the swatch tooltip text for a term, falling back to a caller-provided
	 * default (typically the term name) when no override is stored.
	 *
	 * Returns empty string when both the override and the default are empty.
	 * No filter is exposed at this layer — the tooltip text is plain UI copy
	 * and adding a filter would be premature flexibility we don't need yet.
	 */
	public static function term_tooltip_text( int $term_id, string $default = '' ): string {
		$value = get_term_meta( $term_id, self::tooltip_meta_key(), true );
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
		return $default;
	}

	/**
	 * Decide whether an attribute has any term with a swatch image set.
	 *
	 * An attribute mixed with image and color terms still renders as image
	 * swatches — the per-term precedence (image > color) handles the per-option
	 * fallback. Cached per-request like attribute_is_color().
	 */
	public static function attribute_has_images( string $taxonomy ): bool {
		static $cache = [];
		if ( isset( $cache[ $taxonomy ] ) ) {
			return $cache[ $taxonomy ];
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 1,
			'meta_query' => [
				[
					'key'     => self::image_meta_key(),
					'compare' => 'EXISTS',
				],
			],
		] );

		$cache[ $taxonomy ] = ! is_wp_error( $terms ) && ! empty( $terms );
		return $cache[ $taxonomy ];
	}

	/**
	 * Decide whether an attribute should render as color swatches.
	 *
	 * An attribute renders as color swatches when at least one of its terms
	 * has a hex color stored in term meta. Otherwise it renders as buttons.
	 *
	 * @param string $taxonomy Taxonomy name, e.g. 'pa_color'.
	 */
	public static function attribute_is_color( string $taxonomy ): bool {
		static $cache = [];
		if ( isset( $cache[ $taxonomy ] ) ) {
			return $cache[ $taxonomy ];
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 1,
			'meta_query' => [
				[
					'key'     => self::color_meta_key(),
					'value'   => '',
					'compare' => '!=',
				],
			],
		] );

		$cache[ $taxonomy ] = ! is_wp_error( $terms ) && ! empty( $terms );
		return $cache[ $taxonomy ];
	}

	/**
	 * Get the hex color stored for a given term, or empty string.
	 * Always re-validates before returning so the value is safe to inject
	 * into an inline style attribute.
	 */
	public static function term_color( int $term_id ): string {
		$value = get_term_meta( $term_id, self::color_meta_key(), true );
		if ( ! is_string( $value ) ) {
			return '';
		}
		return self::sanitize_hex_color( $value );
	}

	/**
	 * Validate a hex colour string. Accepts #RGB, #RRGGBB, #RRGGBBAA
	 * (case-insensitive). Returns a normalised `#XXXXXX` (or empty on fail).
	 * Use this before echoing into CSS to avoid injection via the value.
	 */
	public static function sanitize_hex_color( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#?([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value, $m ) ) {
			return '#' . strtoupper( $m[1] );
		}
		return '';
	}

	/**
	 * Relative luminance of a hex colour, 0–255. Used to decide whether
	 * a swatch needs a stronger border so it reads against a white background.
	 * Returns null if the hex is not valid.
	 */
	public static function luminance( string $hex ): ?float {
		$hex = self::sanitize_hex_color( $hex );
		if ( '' === $hex ) {
			return null;
		}
		$digits = substr( $hex, 1 );
		if ( 3 === strlen( $digits ) ) {
			$digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
		} elseif ( 8 === strlen( $digits ) ) {
			$digits = substr( $digits, 0, 6 );
		}
		if ( 6 !== strlen( $digits ) ) {
			return null;
		}
		$r = hexdec( substr( $digits, 0, 2 ) );
		$g = hexdec( substr( $digits, 2, 2 ) );
		$b = hexdec( substr( $digits, 4, 2 ) );
		return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
	}

	public static function is_light_hex( string $hex ): bool {
		$lum = self::luminance( $hex );
		return null !== $lum && $lum > 220.0;
	}

	/**
	 * Resolve the human-readable label for a product attribute. This is a
	 * robust fallback chain because some themes / third-party plugins end up
	 * with attribute taxonomies where `wc_attribute_label()` just returns the
	 * slug (e.g. "pa_color" → "Pa Color" or even "color").
	 *
	 * Chain:
	 *   1. wc_attribute_label() — core WC resolution
	 *   2. The registered taxonomy's labels->singular_name / labels->name
	 *   3. The `woocommerce_attribute_taxonomies` DB row (authoritative "Name")
	 *   4. Prettify the slug (strip pa_ prefix, Title Case, replace _-)
	 *
	 * @param string          $attribute_name Raw attribute key, e.g. `pa_color` or `color`.
	 * @param WC_Product|null $product        Product context (optional).
	 */
	public static function resolve_attribute_label( string $attribute_name, $product = null ): string {
		$label = '';
		if ( function_exists( 'wc_attribute_label' ) ) {
			$label = (string) wc_attribute_label( $attribute_name, $product );
		}

		// If the label looks like the raw slug (case-insensitive), treat it as
		// unresolved and try the richer fallbacks below.
		$looks_like_slug = ( '' === $label )
			|| ( strcasecmp( $label, $attribute_name ) === 0 )
			|| ( 0 === strpos( $attribute_name, 'pa_' ) && strcasecmp( $label, substr( $attribute_name, 3 ) ) === 0 );

		if ( ! $looks_like_slug ) {
			return $label;
		}

		// 2) Registered taxonomy labels.
		$taxonomy = 0 === strpos( $attribute_name, 'pa_' ) ? $attribute_name : '';
		if ( '' !== $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$tax = get_taxonomy( $taxonomy );
			if ( $tax ) {
				if ( ! empty( $tax->labels->singular_name ) ) {
					return (string) $tax->labels->singular_name;
				}
				if ( ! empty( $tax->labels->name ) ) {
					return (string) $tax->labels->name;
				}
				if ( ! empty( $tax->label ) ) {
					return (string) $tax->label;
				}
			}
		}

		// 3) `woocommerce_attribute_taxonomies` row — this holds the "Name"
		// value that the shop owner types in Products → Attributes.
		if ( '' !== $taxonomy && function_exists( 'wc_get_attribute_taxonomy_by_name' ) ) {
			$slug = substr( $taxonomy, 3 );
			$row  = wc_get_attribute_taxonomy_by_name( $slug );
			if ( $row && ! empty( $row->attribute_label ) ) {
				return (string) $row->attribute_label;
			}
		}

		// 4) Prettify the slug.
		$slug = 0 === strpos( $attribute_name, 'pa_' ) ? substr( $attribute_name, 3 ) : $attribute_name;
		$pretty = str_replace( [ '-', '_' ], ' ', $slug );
		$pretty = trim( $pretty );
		if ( '' === $pretty ) {
			return $attribute_name;
		}
		// ucwords is safe for ASCII; for Hebrew we keep the original casing.
		return function_exists( 'mb_convert_case' )
			? mb_convert_case( $pretty, MB_CASE_TITLE, 'UTF-8' )
			: ucwords( $pretty );
	}
}

endif; // class_exists Etucart_VS_Plugin
