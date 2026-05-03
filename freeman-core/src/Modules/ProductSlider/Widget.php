<?php
/**
 * Elementor widget for the Product Slider.
 *
 * Mirrors the Category Slider's `.cs-*` slider chrome (drag, momentum,
 * arrows, progress bar, RTL, per-breakpoint cards-per-view) but each
 * slider item is a *standard* WooCommerce shop loop entry rendered via
 * `wc_get_template_part( 'content', 'product' )`. The emitted markup —
 * `<li class="product type-product post-X status-publish ..."> ... </li>`
 * with the full `woocommerce_before_shop_loop_item` /
 * `woocommerce_before_shop_loop_item_title` / `woocommerce_shop_loop_item_title`
 * / `woocommerce_after_shop_loop_item_title` / `woocommerce_after_shop_loop_item`
 * hook stack — is identical to a default product grid, so any plugin
 * that targets the WC archive (sale flash customisers, wishlist buttons,
 * quick-view modals, image-swap on hover, ratings overrides, etc.) lights
 * up the slider with no extra wiring.
 *
 * The "Display as" toggle switches the outer container between a
 * draggable `<ul class="cs-track products">` and a static
 * `<ul class="cs-grid products">`; the `cs-card` class is added to each
 * `<li>` via the `post_class` filter so the shared flex-basis /
 * scroll-snap / drag-suppression rules apply unchanged.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ProductSlider;

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Freeman\Core\Core\Feature_Flags;

/**
 * Widget.
 */
final class Widget extends Widget_Base {

	public function get_name() {
		return 'freeman_product_slider';
	}

	public function get_title() {
		return __( 'Freeman Product Slider', 'freeman-core' );
	}

	public function get_icon() {
		return 'eicon-woocommerce';
	}

	public function get_categories() {
		return array( 'woocommerce-elements', 'general' );
	}

	public function get_keywords() {
		return array( 'product', 'products', 'slider', 'woocommerce', 'carousel', 'grid', 'freeman' );
	}

	public function get_style_depends() {
		return array( 'freeman-core-category-slider', 'freeman-core-product-slider' );
	}

	public function get_script_depends() {
		return array( 'freeman-core-category-slider' );
	}

	protected function register_controls() {
		// ── Content ────────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'freeman-core' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'eyebrow',
			array(
				'label'   => __( 'Eyebrow', 'freeman-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Featured', 'freeman-core' ),
			)
		);

		$this->add_control(
			'headline',
			array(
				'label'   => __( 'Headline', 'freeman-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'New arrivals.', 'freeman-core' ),
			)
		);

		$this->add_control(
			'headline_mute',
			array(
				'label'   => __( 'Headline subtext', 'freeman-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Hand-picked for the season.', 'freeman-core' ),
			)
		);

		$this->end_controls_section();

		// ── Query ──────────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_query',
			array(
				'label' => __( 'Query', 'freeman-core' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'      => __( 'Max products', 'freeman-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '' ),
				'range'      => array( '' => array( 'min' => 1, 'max' => 24, 'step' => 1 ) ),
				'default'    => array( 'unit' => '', 'size' => 12 ),
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order by', 'freeman-core' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'date'       => __( 'Date', 'freeman-core' ),
					'price'      => __( 'Price', 'freeman-core' ),
					'popularity' => __( 'Popularity', 'freeman-core' ),
					'rating'     => __( 'Rating', 'freeman-core' ),
					'menu_order' => __( 'Menu order', 'freeman-core' ),
					'title'      => __( 'Title', 'freeman-core' ),
					'rand'       => __( 'Random', 'freeman-core' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'freeman-core' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'DESC',
				'toggle'  => false,
				'options' => array(
					'ASC'  => array( 'title' => __( 'Ascending', 'freeman-core' ),  'icon' => 'eicon-arrow-up' ),
					'DESC' => array( 'title' => __( 'Descending', 'freeman-core' ), 'icon' => 'eicon-arrow-down' ),
				),
			)
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'Source', 'freeman-core' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'all',
				'options' => array(
					'all'           => __( 'All published products', 'freeman-core' ),
					'featured'      => __( 'Featured only', 'freeman-core' ),
					'on_sale'       => __( 'On-sale only', 'freeman-core' ),
					'category'      => __( 'By category', 'freeman-core' ),
					'tag'           => __( 'By tag', 'freeman-core' ),
					'manual'        => __( 'Manual selection', 'freeman-core' ),
					'current_query' => __( 'Current query (archive)', 'freeman-core' ),
					'related'       => __( 'Related products (single)', 'freeman-core' ),
				),
			)
		);

		$category_options = $this->get_term_options( 'product_cat' );
		$this->add_control(
			'categories',
			array(
				'label'       => __( 'Categories', 'freeman-core' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $category_options,
				'default'     => array(),
				'condition'   => array( 'source' => 'category' ),
			)
		);

		$tag_options = $this->get_term_options( 'product_tag' );
		$this->add_control(
			'tags',
			array(
				'label'       => __( 'Tags', 'freeman-core' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $tag_options,
				'default'     => array(),
				'condition'   => array( 'source' => 'tag' ),
			)
		);

		$this->add_control(
			'include_ids',
			array(
				'label'       => __( 'Product IDs', 'freeman-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Comma-separated product IDs. Order is preserved.', 'freeman-core' ),
				'condition'   => array( 'source' => 'manual' ),
			)
		);

		$this->add_control(
			'exclude_ids',
			array(
				'label'       => __( 'Exclude product IDs', 'freeman-core' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Comma-separated product IDs to exclude.', 'freeman-core' ),
			)
		);

		$this->add_control(
			'hide_free',
			array(
				'label'        => __( 'Hide free products', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'hide_out_of_stock',
			array(
				'label'        => __( 'Hide out-of-stock', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'description'  => __( 'When off, the WooCommerce global "Hide out of stock items from the catalog" setting still applies.', 'freeman-core' ),
			)
		);

		$this->end_controls_section();

		// ── Layout ─────────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'freeman-core' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'per_view',
			array(
				'label'      => __( 'Cards per view', 'freeman-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '' ),
				'range'      => array( '' => array( 'min' => 2, 'max' => 6, 'step' => 1 ) ),
				'default'    => array( 'unit' => '', 'size' => 4 ),
			)
		);

		$this->add_control(
			'per_view_tablet',
			array(
				'label'      => __( 'Cards per view (tablet)', 'freeman-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '' ),
				'range'      => array( '' => array( 'min' => 2, 'max' => 6, 'step' => 1 ) ),
				'default'    => array( 'unit' => '', 'size' => 3 ),
			)
		);

		$this->add_control(
			'per_view_mobile',
			array(
				'label'       => __( 'Cards per view (mobile)', 'freeman-core' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( '' ),
				'range'       => array( '' => array( 'min' => 1, 'max' => 3, 'step' => 0.1 ) ),
				'default'     => array( 'unit' => '', 'size' => 1.4 ),
				'description' => __( 'Fractional values let the next card peek (e.g. 1.4 = one full card with ~30% of the next showing).', 'freeman-core' ),
			)
		);

		$this->add_control(
			'gap',
			array(
				'label'      => __( 'Gap', 'freeman-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 4, 'max' => 48, 'step' => 2 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 20 ),
			)
		);

		$this->add_control(
			'card_height',
			array(
				'label'       => __( 'Image height', 'freeman-core' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array( 'px' => array( 'min' => 180, 'max' => 480, 'step' => 10 ) ),
				'default'     => array( 'unit' => 'px', 'size' => 320 ),
				'description' => __( 'Height of the image area. Meta and cart button stack below at natural height.', 'freeman-core' ),
			)
		);

		$this->end_controls_section();

		// ── Card style (Style tab) ────────────────────────────────────────
		$this->start_controls_section(
			'section_card_style',
			array(
				'label' => __( 'Card style', 'freeman-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'shape',
			array(
				'label'   => __( 'Image shape', 'freeman-core' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'soft',
				'toggle'  => false,
				'options' => array(
					'soft' => array( 'title' => __( 'Soft', 'freeman-core' ), 'icon' => 'eicon-frame-expand' ),
					'rect' => array( 'title' => __( 'Rect', 'freeman-core' ), 'icon' => 'eicon-square' ),
				),
			)
		);

		$this->add_control(
			'show_cart',
			array(
				'label'        => __( 'Show add-to-cart button', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_sale_badge',
			array(
				'label'        => __( 'Show sale badge', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => __( "Toggles WooCommerce's standard sale flash on each card. Plugins that replace the sale flash respect this toggle automatically.", 'freeman-core' ),
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'     => __( 'Accent color', 'freeman-core' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .cs' => '--cs-accent: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'ring_color',
			array(
				'label'       => __( 'Hover ring color', 'freeman-core' ),
				'type'        => Controls_Manager::COLOR,
				'default'     => '',
				'description' => __( 'Outline drawn around a card on hover.', 'freeman-core' ),
				'selectors'   => array(
					'{{WRAPPER}} .cs' => '--cs-ring-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'typo_heading',
			array(
				'label'     => __( 'Typography', 'freeman-core' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'eyebrow_typography',
				'label'    => __( 'Eyebrow', 'freeman-core' ),
				'selector' => '{{WRAPPER}} .cs-eyebrow',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'headline_typography',
				'label'    => __( 'Headline', 'freeman-core' ),
				'selector' => '{{WRAPPER}} .cs-headline',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'name_typography',
				'label'    => __( 'Product name', 'freeman-core' ),
				'selector' => '{{WRAPPER}} .cs.cs-products .woocommerce-loop-product__title',
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Product name color', 'freeman-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .cs.cs-products .woocommerce-loop-product__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'price_color',
			array(
				'label'     => __( 'Price color', 'freeman-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .cs.cs-products .price' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		// ── Behavior ───────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_behavior',
			array(
				'label' => __( 'Behavior', 'freeman-core' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'display_mode',
			array(
				'label'       => __( 'Display as', 'freeman-core' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'slider',
				'toggle'      => false,
				'options'     => array(
					'slider' => array( 'title' => __( 'Slider', 'freeman-core' ), 'icon' => 'eicon-slider-push' ),
					'grid'   => array( 'title' => __( 'Grid', 'freeman-core' ),   'icon' => 'eicon-gallery-grid' ),
				),
				'description' => __( 'Slider = draggable horizontal row with progress bar. Grid = static layout (no drag, no arrows).', 'freeman-core' ),
			)
		);

		$this->add_control(
			'direction',
			array(
				'label'       => __( 'Direction', 'freeman-core' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'auto',
				'toggle'      => false,
				'options'     => array(
					'auto' => array( 'title' => __( 'Auto (follow site)', 'freeman-core' ), 'icon' => 'eicon-cog' ),
					'ltr'  => array( 'title' => __( 'Force LTR', 'freeman-core' ),         'icon' => 'eicon-arrow-right' ),
					'rtl'  => array( 'title' => __( 'Force RTL', 'freeman-core' ),         'icon' => 'eicon-arrow-left' ),
				),
				'description' => __( 'RTL flips arrows, drag direction, and progress bar.', 'freeman-core' ),
				'condition'   => array( 'display_mode' => 'slider' ),
			)
		);

		$this->add_control(
			'snap',
			array(
				'label'     => __( 'Scroll snap', 'freeman-core' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'none',
				'toggle'    => false,
				'options'   => array(
					'none' => array( 'title' => __( 'Free-scroll', 'freeman-core' ), 'icon' => 'eicon-ellipsis-h' ),
					'card' => array( 'title' => __( 'Per card', 'freeman-core' ),    'icon' => 'eicon-frame-minimize' ),
					'page' => array( 'title' => __( 'Per page', 'freeman-core' ),    'icon' => 'eicon-frame-expand' ),
				),
				'condition' => array( 'display_mode' => 'slider' ),
			)
		);

		$this->add_control(
			'mouse_drag',
			array(
				'label'        => __( 'Enable mouse drag', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => __( 'Drag cards left or right to scroll the row. Clicks still navigate normally — drag only engages after a deliberate horizontal motion.', 'freeman-core' ),
				'condition'    => array( 'display_mode' => 'slider' ),
			)
		);

		$this->add_control(
			'show_arrows',
			array(
				'label'        => __( 'Show arrow buttons', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => array( 'display_mode' => 'slider' ),
			)
		);

		// Advanced controls — registered only when the feature flag is on.
		// Saved widget dicts retain whatever values they had if the flag is
		// flipped off; the render path also gates on the flag, so the
		// rendered output reverts byte-identical to the legacy path until
		// the flag is on. show_progress stays in place as a back-compat
		// alias — the indicator control supersedes it when set.
		// Advanced controls additionally inherit display_mode=slider so
		// they're hidden in grid mode (autoplay/indicator have no meaning
		// without slider chrome); the render path also gates on $is_slider.
		if ( Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ) ) {
			$this->add_control(
				'indicator',
				array(
					'label'   => __( 'Indicator', 'freeman-core' ),
					'type'    => Controls_Manager::CHOOSE,
					'default' => 'progress',
					'toggle'  => false,
					'options' => array(
						'progress' => array( 'title' => __( 'Progress bar', 'freeman-core' ), 'icon' => 'eicon-slider-push' ),
						'dots'     => array( 'title' => __( 'Pagination dots', 'freeman-core' ), 'icon' => 'eicon-ellipsis-h' ),
						'none'     => array( 'title' => __( 'Hidden', 'freeman-core' ),         'icon' => 'eicon-ban' ),
					),
					'description' => __( 'Replaces the legacy "Show progress bar" toggle. When unset on pre-existing widgets, the legacy value is honored.', 'freeman-core' ),
					'condition'   => array( 'display_mode' => 'slider' ),
				)
			);
		}

		$this->add_control(
			'show_progress',
			array(
				'label'        => __( 'Show progress bar', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => array( 'display_mode' => 'slider' ),
			)
		);

		if ( Feature_Flags::is_enabled( 'sliders', 'advanced_controls' ) ) {
			$this->add_control(
				'autoplay',
				array(
					'label'        => __( 'Autoplay', 'freeman-core' ),
					'type'         => Controls_Manager::SWITCHER,
					'default'      => '',
					'return_value' => 'yes',
					'separator'    => 'before',
					'condition'    => array( 'display_mode' => 'slider' ),
				)
			);

			$this->add_control(
				'autoplay_delay',
				array(
					'label'      => __( 'Autoplay delay (ms)', 'freeman-core' ),
					'type'       => Controls_Manager::SLIDER,
					'size_units' => array( 'ms' ),
					'range'      => array( 'ms' => array( 'min' => 1000, 'max' => 15000, 'step' => 500 ) ),
					'default'    => array( 'unit' => 'ms', 'size' => 5000 ),
					'condition'  => array(
						'display_mode' => 'slider',
						'autoplay'     => 'yes',
					),
				)
			);

			$this->add_control(
				'loop',
				array(
					'label'        => __( 'Loop', 'freeman-core' ),
					'type'         => Controls_Manager::SWITCHER,
					'default'      => '',
					'return_value' => 'yes',
					'description'  => __( 'When the autoplay reaches the end, smoothly wrap back to the start. Drag-past-end-wraps is intentionally out of scope.', 'freeman-core' ),
					'condition'    => array(
						'display_mode' => 'slider',
						'autoplay'     => 'yes',
					),
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * Build a term-id => name map for SELECT2 controls. Capped at 200 to
	 * keep the editor responsive on stores with very large taxonomies.
	 *
	 * @param string $taxonomy
	 * @return array<int,string>
	 */
	private function get_term_options( $taxonomy ) {
		if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$opts = array();
		foreach ( $terms as $t ) {
			$opts[ (int) $t->term_id ] = $t->name;
		}
		return $opts;
	}

	/**
	 * Coerce a setting into a list of positive integers.
	 *
	 * @param mixed $raw
	 * @return int[]
	 */
	private function ids_array( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,]+/', $raw );
		}
		if ( ! is_array( $raw ) ) {
			$raw = array( $raw );
		}
		$out = array();
		foreach ( $raw as $v ) {
			$id = (int) $v;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Read a SLIDER control as int (handles both array+scalar shapes).
	 *
	 * @param mixed $raw
	 * @param int   $default
	 * @return int
	 */
	private function slider_int( $raw, $default ) {
		if ( is_array( $raw ) && isset( $raw['size'] ) && '' !== $raw['size'] ) {
			return (int) $raw['size'];
		}
		if ( is_scalar( $raw ) && '' !== $raw ) {
			return (int) $raw;
		}
		return $default;
	}

	/**
	 * Float variant for SLIDER controls that allow fractional steps —
	 * notably `per_view_mobile` (1.4 = one card with peek of next).
	 *
	 * @param mixed $raw
	 * @param float $default
	 * @return float
	 */
	private function slider_float( $raw, $default ) {
		if ( is_array( $raw ) && isset( $raw['size'] ) && '' !== $raw['size'] ) {
			return (float) $raw['size'];
		}
		if ( is_scalar( $raw ) && '' !== $raw ) {
			return (float) $raw;
		}
		return $default;
	}

	/**
	 * @return bool
	 */
	private function is_elementor_edit_mode() {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! $plugin || empty( $plugin->editor ) ) {
			return false;
		}
		return (bool) $plugin->editor->is_edit_mode();
	}

	/**
	 * @param string $setting
	 * @return string ltr|rtl
	 */
	private function resolve_direction( $setting ) {
		if ( 'rtl' === $setting ) {
			return 'rtl';
		}
		if ( 'ltr' === $setting ) {
			return 'ltr';
		}
		return ( function_exists( 'is_rtl' ) && is_rtl() ) ? 'rtl' : 'ltr';
	}

	/**
	 * Run the product query.
	 *
	 * @param array $s Settings.
	 * @return \WC_Product[]
	 */
	private function fetch_products( $s ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$limit  = max( 1, $this->slider_int( $s['limit'] ?? null, 12 ) );
		$source = $s['source'] ?? 'all';

		// Current archive query — read $wp_query when on a product archive.
		if ( 'current_query' === $source ) {
			if ( is_post_type_archive( 'product' ) || is_tax( array( 'product_cat', 'product_tag' ) ) ) {
				global $wp_query;
				$out = array();
				if ( $wp_query && ! empty( $wp_query->posts ) ) {
					foreach ( $wp_query->posts as $p ) {
						$prod = wc_get_product( $p );
						if ( $prod instanceof \WC_Product && $prod->is_visible() ) {
							$out[] = $prod;
						}
						if ( count( $out ) >= $limit ) {
							break;
						}
					}
				}
				return $out;
			}
			$source = 'all';
		}

		// Related products — only meaningful on a single product page.
		if ( 'related' === $source ) {
			$current_id = (int) get_the_ID();
			if ( is_singular( 'product' ) && $current_id > 0 && function_exists( 'wc_get_related_products' ) ) {
				$related_ids = wc_get_related_products( $current_id, $limit );
				if ( empty( $related_ids ) ) {
					return array();
				}
				$out = array();
				foreach ( $related_ids as $id ) {
					$prod = wc_get_product( $id );
					if ( $prod instanceof \WC_Product && $prod->is_visible() ) {
						$out[] = $prod;
					}
				}
				return $out;
			}
			$source = 'all';
		}

		$args = array(
			'status'  => 'publish',
			'limit'   => $limit,
			'orderby' => $s['orderby'] ?? 'date',
			'order'   => ( ( $s['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC',
			'return'  => 'objects',
		);

		// Stock visibility — explicit toggle wins; otherwise respect the
		// WC global option so the slider matches the rest of the catalog.
		if ( ( $s['hide_out_of_stock'] ?? '' ) === 'yes' ) {
			$args['stock_status'] = 'instock';
		} elseif ( function_exists( 'get_option' ) && 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$args['stock_status'] = 'instock';
		}

		// Hide free products.
		if ( ( $s['hide_free'] ?? '' ) === 'yes' ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_price',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			);
		}

		if ( 'manual' === $source ) {
			$ids = $this->ids_array( $s['include_ids'] ?? '' );
			if ( empty( $ids ) ) {
				return array();
			}
			$args['include'] = $ids;
			$args['orderby'] = 'post__in';
			$args['limit']   = count( $ids );
		} elseif ( 'featured' === $source ) {
			$args['featured'] = true;
		} elseif ( 'on_sale' === $source ) {
			$args['on_sale'] = true;
		} elseif ( 'category' === $source ) {
			$cat_ids = $this->ids_array( $s['categories'] ?? array() );
			if ( empty( $cat_ids ) ) {
				return array();
			}
			$slugs = array();
			foreach ( $cat_ids as $id ) {
				$term = get_term( $id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$slugs[] = $term->slug;
				}
			}
			if ( empty( $slugs ) ) {
				return array();
			}
			$args['category'] = $slugs;
		} elseif ( 'tag' === $source ) {
			$tag_ids = $this->ids_array( $s['tags'] ?? array() );
			if ( empty( $tag_ids ) ) {
				return array();
			}
			$slugs = array();
			foreach ( $tag_ids as $id ) {
				$term = get_term( $id, 'product_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					$slugs[] = $term->slug;
				}
			}
			if ( empty( $slugs ) ) {
				return array();
			}
			$args['tag'] = $slugs;
		}

		$exclude = $this->ids_array( $s['exclude_ids'] ?? '' );
		if ( ! empty( $exclude ) ) {
			$args['exclude'] = $exclude;
		}

		/**
		 * Filter the `wc_get_products()` args used by the Product Slider widget.
		 *
		 * Only fires on the standard query path. The early-return branches for
		 * `current_query` (read $wp_query directly) and `related` (delegate to
		 * `wc_get_related_products()`) bypass this filter — those code paths
		 * never build `$args`.
		 *
		 * @since 1.11.1
		 *
		 * @param array $args     Args about to be passed to `wc_get_products()`.
		 * @param array $settings Resolved widget settings.
		 */
		$args = (array) apply_filters( 'freeman_core/product_slider/query_args', $args, $s );

		$products = wc_get_products( $args );
		if ( ! is_array( $products ) ) {
			return array();
		}
		// Drop hidden + non-purchasable-and-not-viewable. wc_get_products
		// already filters by status; this catches catalog_visibility="hidden".
		return array_values(
			array_filter(
				$products,
				static function ( $p ) {
					return $p instanceof \WC_Product && $p->is_visible();
				}
			)
		);
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$products = $this->fetch_products( $s );
		if ( empty( $products ) ) {
			if ( $this->is_elementor_edit_mode() ) {
				echo '<div class="cs-empty">' . esc_html__( 'No products match the current Query settings.', 'freeman-core' ) . '</div>';
			}
			return;
		}

		$display_mode    = ( ( $s['display_mode'] ?? 'slider' ) === 'grid' ) ? 'grid' : 'slider';
		$is_slider       = ( 'slider' === $display_mode );
		$per_view        = max( 2, $this->slider_int( $s['per_view'] ?? null, 4 ) );
		$per_view_tablet = max( 2, $this->slider_int( $s['per_view_tablet'] ?? null, 3 ) );
		$per_view_mobile = max( 1.0, $this->slider_float( $s['per_view_mobile'] ?? null, 1.4 ) );
		$gap             = max( 0, $this->slider_int( $s['gap'] ?? null, 20 ) );
		$card_height     = max( 100, $this->slider_int( $s['card_height'] ?? null, 320 ) );
		$shape           = in_array( $s['shape'] ?? 'soft', array( 'soft', 'rect' ), true ) ? $s['shape'] : 'soft';
		$show_cart       = ( $s['show_cart'] ?? 'yes' ) === 'yes';
		$show_sale_badge = ( $s['show_sale_badge'] ?? 'yes' ) === 'yes';

		// Slider-only knobs.
		$snap          = in_array( $s['snap'] ?? 'none', array( 'none', 'card', 'page' ), true ) ? $s['snap'] : 'none';
		$show_arrows   = $is_slider && ( $s['show_arrows'] ?? 'yes' ) === 'yes';
		$show_progress = $is_slider && ( $s['show_progress'] ?? 'yes' ) === 'yes';
		$mouse_drag    = $is_slider && ( $s['mouse_drag'] ?? 'yes' ) === 'yes';
		$dir           = $this->resolve_direction( $s['direction'] ?? 'auto' );

		// Advanced controls (autoplay / loop / indicator) — gated on the
		// flag at both registration AND render, AND on $is_slider so grid
		// mode emits no advanced data attrs and falls through to the
		// legacy show_progress-driven indicator state. Flag-off render
		// paths ignore any saved advanced settings and fall through to
		// the legacy show_progress-driven indicator state, so flipping
		// the flag back to false produces byte-identical output.
		$advanced_enabled = $is_slider && Feature_Flags::is_enabled( 'sliders', 'advanced_controls' );
		if ( $advanced_enabled ) {
			$raw_indicator = $s['indicator'] ?? null;
			if ( in_array( $raw_indicator, array( 'progress', 'dots', 'none' ), true ) ) {
				$indicator = $raw_indicator;
			} else {
				// Back-compat shim: pre-existing widgets don't have
				// `indicator` saved — honor whatever `show_progress` said.
				$indicator = $show_progress ? 'progress' : 'none';
			}
			$autoplay       = ( $s['autoplay'] ?? '' ) === 'yes';
			$autoplay_delay = max( 1000, min( 15000, $this->slider_int( $s['autoplay_delay'] ?? null, 5000 ) ) );
			$loop           = $autoplay && ( $s['loop'] ?? '' ) === 'yes';
		} else {
			$indicator      = $show_progress ? 'progress' : 'none';
			$autoplay       = false;
			$autoplay_delay = 0;
			$loop           = false;
		}

		// `per_view_mobile` is a float (1.0–3.0) so the next card can peek;
		// the others are integer cards-per-view.
		$style_vars = sprintf(
			'--cs-per: %d; --cs-per-tablet: %d; --cs-per-mobile: %s; --cs-gap: %dpx; --cs-card-h: %dpx;',
			$per_view,
			$per_view_tablet,
			rtrim( rtrim( number_format( $per_view_mobile, 2, '.', '' ), '0' ), '.' ),
			$gap,
			$card_height
		);

		// `woocommerce` is the ancestor class many third-party plugins
		// (WPC Quick View, YITH Wishlist, etc.) and theme overrides use to
		// scope selectors like `.woocommerce ul.products li.product .X`.
		// Elementor's default WC Products widget wraps its UL in a
		// `.woocommerce` div for the same reason — adding it here is what
		// puts the slider's cards in the same selector context as the
		// regular product grid, so user-written CSS that targets the
		// regular grid applies to the slider verbatim.
		$root_classes = 'cs cs-products woocommerce cs-shape-' . $shape;
		if ( ! $is_slider ) {
			$root_classes .= ' cs-grid-mode';
		}

		$total = count( $products );

		// Per-widget hook suppression. We restore each callback after the
		// loop so other product loops / widgets on the same page render
		// unchanged. Plugins that hook into the same actions stay wired
		// regardless of these toggles.
		if ( ! $show_sale_badge ) {
			remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
		}
		if ( ! $show_cart ) {
			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		}

		// Inject `cs-card` onto each product `<li>` so the shared `.cs-*`
		// flex-basis, scroll-snap, and drag-suppression rules from the
		// CategorySlider stylesheet apply to slider items unchanged. The
		// rest of the `<li>` body comes verbatim from
		// `wc_get_template_part( 'content', 'product' )` so every plugin
		// that styles the standard shop loop also lights up the slider.
		$cs_card_filter = static function ( $classes ) {
			$classes[] = 'cs-card';
			return $classes;
		};
		add_filter( 'post_class', $cs_card_filter );

		$track_classes = ( $is_slider ? 'cs-track' : 'cs-grid' ) . ' products columns-' . (int) $per_view;
		?>
		<div
			class="<?php echo esc_attr( $root_classes ); ?>"
			dir="<?php echo esc_attr( $dir ); ?>"
			<?php if ( $is_slider ) : ?>
				data-cs-snap="<?php echo esc_attr( $snap ); ?>"
				data-cs-mouse-drag="<?php echo $mouse_drag ? '1' : '0'; ?>"
				data-cs-clamp-children="1"
			<?php endif; ?>
			<?php if ( $advanced_enabled ) : ?>
				data-cs-indicator="<?php echo esc_attr( $indicator ); ?>"
				<?php if ( $autoplay ) : ?>
					data-cs-autoplay="1"
					data-cs-autoplay-delay="<?php echo (int) $autoplay_delay; ?>"
				<?php endif; ?>
				<?php if ( $loop ) : ?>
					data-cs-loop="1"
				<?php endif; ?>
			<?php endif; ?>
			style="<?php echo esc_attr( $style_vars ); ?>"
		>
			<div class="cs-head">
				<?php if ( ! empty( $s['eyebrow'] ) ) : ?>
					<div class="cs-eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $s['headline'] ) || ! empty( $s['headline_mute'] ) ) : ?>
					<div class="cs-headline">
						<?php echo esc_html( $s['headline'] ?? '' ); ?>
						<?php if ( ! empty( $s['headline_mute'] ) ) : ?>
							<span class="cs-headline-mute"> <?php echo esc_html( $s['headline_mute'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( $show_arrows ) : ?>
					<div class="cs-arrows" role="group" aria-label="<?php esc_attr_e( 'Scroll products', 'freeman-core' ); ?>">
						<button type="button" class="cs-arrow" data-cs-dir="-1" aria-label="<?php esc_attr_e( 'Previous', 'freeman-core' ); ?>">
							<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M9 3L5 7L9 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<button type="button" class="cs-arrow" data-cs-dir="1" aria-label="<?php esc_attr_e( 'Next', 'freeman-core' ); ?>">
							<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M5 3L9 7L5 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
					</div>
				<?php endif; ?>
			</div>

			<ul class="<?php echo esc_attr( $track_classes ); ?>"<?php echo $is_slider ? ' data-cs-track' : ''; ?>>
				<?php
				global $post, $product;
				$original_post    = $post;
				$original_product = $product;

				foreach ( $products as $product_obj ) {
					$post_object = get_post( $product_obj->get_id() );
					if ( ! $post_object ) {
						continue;
					}
					$post    = $post_object; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
					$product = $product_obj; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
					setup_postdata( $post );
					wc_get_template_part( 'content', 'product' );
				}

				$post    = $original_post;    // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				$product = $original_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				wp_reset_postdata();
				?>
			</ul>

			<?php if ( 'progress' === $indicator ) : ?>
				<div class="cs-foot">
					<div class="cs-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
						<div class="cs-progress-bar" data-cs-progress-bar></div>
					</div>
					<div class="cs-foot-label" data-cs-foot-label>
						<span data-cs-foot-current><?php echo esc_html( str_pad( (string) min( $total, $per_view ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<span class="cs-foot-sep"> / <?php echo esc_html( str_pad( (string) $total, 2, '0', STR_PAD_LEFT ) ); ?></span>
					</div>
				</div>
			<?php elseif ( 'dots' === $indicator ) : ?>
				<?php $page_count = max( 1, (int) ceil( $total / max( 1, $per_view ) ) ); ?>
				<div class="cs-foot cs-foot-dots">
					<div class="cs-dots" data-cs-dots role="tablist" aria-label="<?php esc_attr_e( 'Slide navigation', 'freeman-core' ); ?>">
						<?php for ( $i = 0; $i < $page_count; $i++ ) : ?>
							<button type="button" class="cs-dot<?php echo 0 === $i ? ' cs-dot-active' : ''; ?>" data-cs-dot="<?php echo (int) $i; ?>" role="tab" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d page index, 1-based */ __( 'Page %d', 'freeman-core' ), $i + 1 ) ); ?>"></button>
						<?php endfor; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php

		remove_filter( 'post_class', $cs_card_filter );

		if ( ! $show_sale_badge ) {
			add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
		}
		if ( ! $show_cart ) {
			add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		}
	}
}
