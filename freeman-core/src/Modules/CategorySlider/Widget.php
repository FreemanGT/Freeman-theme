<?php
/**
 * Elementor widget for the Category Slider.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\CategorySlider;

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Widget.
 *
 * Defaults mirror the TWEAK_DEFAULTS block from the Claude Design handoff:
 *   perView 5 · gap 20 · shape "soft" · cardHeight 280 ·
 *   showCount "hover" · showArrows true · snap "none".
 */
final class Widget extends Widget_Base {

	public function get_name() {
		return 'freeman_category_slider';
	}

	public function get_title() {
		return __( 'Freeman Category Slider', 'freeman-core' );
	}

	public function get_icon() {
		return 'eicon-products-archive';
	}

	public function get_categories() {
		return array( 'woocommerce-elements', 'general' );
	}

	public function get_keywords() {
		return array( 'category', 'slider', 'woocommerce', 'carousel', 'freeman' );
	}

	public function get_style_depends() {
		return array( 'freeman-core-category-slider' );
	}

	public function get_script_depends() {
		return array( 'freeman-core-category-slider' );
	}

	protected function register_controls() {
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
				'default' => __( 'Shop by category', 'freeman-core' ),
			)
		);

		$this->add_control(
			'headline',
			array(
				'label'   => __( 'Headline', 'freeman-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'The Spring Edit.', 'freeman-core' ),
			)
		);

		$this->add_control(
			'headline_mute',
			array(
				'label'   => __( 'Headline subtext', 'freeman-core' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Curated essentials, season-led.', 'freeman-core' ),
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'      => __( 'Max categories', 'freeman-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '' ),
				'range'      => array( '' => array( 'min' => 1, 'max' => 50, 'step' => 1 ) ),
				'default'    => array( 'unit' => '', 'size' => 12 ),
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order by', 'freeman-core' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'name',
				'options' => array(
					'name'       => __( 'Name', 'freeman-core' ),
					'count'      => __( 'Product count', 'freeman-core' ),
					'slug'       => __( 'Slug', 'freeman-core' ),
					'menu_order' => __( 'Menu order', 'freeman-core' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'freeman-core' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'ASC',
				'toggle'  => false,
				'options' => array(
					'ASC'  => array( 'title' => __( 'Ascending', 'freeman-core' ),  'icon' => 'eicon-arrow-up' ),
					'DESC' => array( 'title' => __( 'Descending', 'freeman-core' ), 'icon' => 'eicon-arrow-down' ),
				),
			)
		);

		$this->add_control(
			'hide_empty',
			array(
				'label'        => __( 'Hide empty categories', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'parent_only',
			array(
				'label'        => __( 'Top-level only', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => __( 'Ignored if "Child of" or "Include only" is set.', 'freeman-core' ),
			)
		);

		$term_options = $this->get_term_options();

		$this->add_control(
			'child_of',
			array(
				'label'       => __( 'Child of', 'freeman-core' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array( '' => __( '— Any —', 'freeman-core' ) ) + $term_options,
				'description' => __( 'Show only sub-categories of this parent term.', 'freeman-core' ),
				'condition'   => array( 'parent_only!' => 'yes' ),
			)
		);

		$this->add_control(
			'include',
			array(
				'label'       => __( 'Include only these', 'freeman-core' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $term_options,
				'default'     => array(),
				'description' => __( 'When set, only these categories appear (overrides Top-level / Child of).', 'freeman-core' ),
			)
		);

		$this->add_control(
			'exclude',
			array(
				'label'       => __( 'Exclude these', 'freeman-core' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => $term_options,
				'default'     => array(),
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
				'range'      => array( '' => array( 'min' => 2, 'max' => 8, 'step' => 1 ) ),
				'default'    => array( 'unit' => '', 'size' => 5 ),
			)
		);

		$this->add_control(
			'per_view_tablet',
			array(
				'label'      => __( 'Cards per view (tablet)', 'freeman-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '' ),
				'range'      => array( '' => array( 'min' => 2, 'max' => 8, 'step' => 1 ) ),
				'default'    => array( 'unit' => '', 'size' => 4 ),
			)
		);

		$this->add_control(
			'per_view_mobile',
			array(
				'label'      => __( 'Cards per view (mobile)', 'freeman-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '' ),
				'range'      => array( '' => array( 'min' => 1, 'max' => 4, 'step' => 1 ) ),
				'default'    => array( 'unit' => '', 'size' => 2 ),
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
				'label'      => __( 'Card height', 'freeman-core' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 180, 'max' => 420, 'step' => 10 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 280 ),
			)
		);

		$this->end_controls_section();

		// ── Card style ─────────────────────────────────────────────────────
		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Card style', 'freeman-core' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'shape',
			array(
				'label'   => __( 'Shape', 'freeman-core' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'soft',
				'toggle'  => false,
				'options' => array(
					'circle' => array( 'title' => __( 'Circle', 'freeman-core' ), 'icon' => 'eicon-circle-o' ),
					'soft'   => array( 'title' => __( 'Soft', 'freeman-core' ),   'icon' => 'eicon-frame-expand' ),
					'rect'   => array( 'title' => __( 'Rect', 'freeman-core' ),   'icon' => 'eicon-square' ),
					'pill'   => array( 'title' => __( 'Pill', 'freeman-core' ),   'icon' => 'eicon-tags' ),
				),
			)
		);

		$this->add_control(
			'show_count',
			array(
				'label'   => __( 'Show product count', 'freeman-core' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'hover',
				'toggle'  => false,
				'options' => array(
					'hover'  => array( 'title' => __( 'On hover', 'freeman-core' ), 'icon' => 'eicon-mouse' ),
					'always' => array( 'title' => __( 'Always', 'freeman-core' ),   'icon' => 'eicon-eye' ),
					'none'   => array( 'title' => __( 'Hidden', 'freeman-core' ),   'icon' => 'eicon-ban' ),
				),
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'   => __( 'Accent color', 'freeman-core' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '',
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
			'heading_typography',
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
				'label'    => __( 'Card name', 'freeman-core' ),
				'selector' => '{{WRAPPER}} .cs-name',
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Card name color', 'freeman-core' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .cs-name' => 'color: {{VALUE}};',
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
			)
		);

		$this->add_control(
			'snap',
			array(
				'label'   => __( 'Scroll snap', 'freeman-core' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'none',
				'toggle'  => false,
				'options' => array(
					'none' => array( 'title' => __( 'Free-scroll', 'freeman-core' ), 'icon' => 'eicon-ellipsis-h' ),
					'card' => array( 'title' => __( 'Per card', 'freeman-core' ),    'icon' => 'eicon-frame-minimize' ),
					'page' => array( 'title' => __( 'Per page', 'freeman-core' ),    'icon' => 'eicon-frame-expand' ),
				),
			)
		);

		$this->add_control(
			'mouse_drag',
			array(
				'label'        => __( 'Enable mouse drag', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'description'  => __( 'Drag cards left or right to scroll the row. Clicks still navigate normally — drag only engages after a deliberate horizontal motion. Turn off for pure click behavior.', 'freeman-core' ),
			)
		);

		$this->add_control(
			'show_arrows',
			array(
				'label'        => __( 'Show arrow buttons', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_progress',
			array(
				'label'        => __( 'Show progress bar', 'freeman-core' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Build the term options list for include/exclude/child_of selects.
	 * Returns id => name. Capped at 200 terms — anything bigger should
	 * really be using the taxonomy archive, not a slider widget.
	 *
	 * @return array<int,string>
	 */
	private function get_term_options() {
		if ( ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
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
	 * Coerce a setting value into an array of positive integer IDs.
	 *
	 * @param mixed $raw
	 * @return int[]
	 */
	private function ids_array( $raw ) {
		if ( empty( $raw ) ) {
			return array();
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
	 * Query product_cat terms with the widget's settings.
	 *
	 * @param array $s Settings.
	 * @return \WP_Term[]
	 */
	private function fetch_terms( $s ) {
		$include = $this->ids_array( $s['include'] ?? array() );
		$exclude = $this->ids_array( $s['exclude'] ?? array() );

		$args = array(
			'taxonomy'   => 'product_cat',
			'orderby'    => $s['orderby'] ?? 'name',
			'order'      => ( ( $s['order'] ?? 'ASC' ) === 'DESC' ) ? 'DESC' : 'ASC',
			'hide_empty' => ( ( $s['hide_empty'] ?? 'yes' ) === 'yes' ),
			'number'     => max( 1, $this->slider_int( $s['limit'] ?? null, 12 ) ),
		);

		if ( ! empty( $include ) ) {
			// `include` overrides hierarchical filters — user explicitly chose these.
			$args['include'] = $include;
		} else {
			$child_of = (int) ( $s['child_of'] ?? 0 );
			if ( $child_of > 0 ) {
				$args['parent'] = $child_of;
			} elseif ( ( $s['parent_only'] ?? 'yes' ) === 'yes' ) {
				$args['parent'] = 0;
			}
		}

		if ( ! empty( $exclude ) ) {
			$args['exclude'] = $exclude;
		}

		/**
		 * Filter the `get_terms()` args used by the Category Slider widget.
		 *
		 * @since 1.11.1
		 *
		 * @param array $args     Args about to be passed to `get_terms()`.
		 * @param array $settings Resolved widget settings.
		 */
		$args = (array) apply_filters( 'freeman_core/category_slider/query_args', $args, $s );

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		return $terms;
	}

	/**
	 * Read a SLIDER control value as int. Elementor stores sliders as
	 * ['size' => N, 'unit' => '...']; older saved values may still be plain
	 * scalars, so we handle both shapes.
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
	 * Are we rendering inside the Elementor editor preview?
	 *
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
	 * Resolve the Direction control to the actual direction string.
	 *
	 * @param string $setting auto|ltr|rtl
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
	 * Resolve the term archive URL with a hard fallback. `get_term_link()`
	 * can return WP_Error (taxonomy missing), false, or empty string when
	 * permalinks are mis-set up — anchoring an empty href reloads the
	 * current page, which is what made the URLs "look wrong" in the QA.
	 *
	 * @param \WP_Term $term
	 * @return string
	 */
	private function safe_term_url( $term ) {
		if ( ! $term || empty( $term->term_id ) ) {
			return '#';
		}
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) || ! is_string( $url ) || '' === $url ) {
			// Last-resort: send users to a search for the term name. Better
			// than a "#" or self-reload — preserves intent even when permalinks
			// are misconfigured.
			if ( function_exists( 'home_url' ) ) {
				return add_query_arg(
					array( 'product_cat' => $term->slug ),
					home_url( '/' )
				);
			}
			return '#';
		}
		return $url;
	}

	/**
	 * WC stores the category thumbnail attachment id in the term meta key
	 * `thumbnail_id`. Returns '' when none is set.
	 *
	 * @param int $term_id
	 * @return string
	 */
	private function get_thumbnail_url( $term_id ) {
		$thumb_id = get_term_meta( $term_id, 'thumbnail_id', true );
		if ( ! $thumb_id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( (int) $thumb_id, 'woocommerce_thumbnail' );
		return $src ? $src[0] : '';
	}

	/**
	 * Stable hue 0-360 from a string — used to colour the placeholder when a
	 * category has no thumbnail. Mirrors the design's tone+hue placeholders.
	 *
	 * @param string $key
	 * @return int
	 */
	private function hue_from( $key ) {
		$h = crc32( $key );
		return abs( $h ) % 360;
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$terms = $this->fetch_terms( $s );
		if ( empty( $terms ) ) {
			if ( $this->is_elementor_edit_mode() ) {
				echo '<div class="cs-empty">' . esc_html__( 'No product categories found. Adjust the Query controls or add a thumbnail to a category.', 'freeman-core' ) . '</div>';
			}
			return;
		}

		$per_view        = max( 2, $this->slider_int( $s['per_view'] ?? null, 5 ) );
		$per_view_tablet = max( 2, $this->slider_int( $s['per_view_tablet'] ?? null, 4 ) );
		$per_view_mobile = max( 1, $this->slider_int( $s['per_view_mobile'] ?? null, 2 ) );
		$gap             = max( 0, $this->slider_int( $s['gap'] ?? null, 20 ) );
		$card_height     = max( 100, $this->slider_int( $s['card_height'] ?? null, 280 ) );
		$shape           = in_array( $s['shape'] ?? 'soft', array( 'circle', 'soft', 'rect', 'pill' ), true ) ? $s['shape'] : 'soft';
		$show_count      = in_array( $s['show_count'] ?? 'hover', array( 'hover', 'always', 'none' ), true ) ? $s['show_count'] : 'hover';
		$snap            = in_array( $s['snap'] ?? 'none', array( 'none', 'card', 'page' ), true ) ? $s['snap'] : 'none';
		$show_arrows     = ( $s['show_arrows'] ?? 'yes' ) === 'yes';
		$show_progress   = ( $s['show_progress'] ?? 'yes' ) === 'yes';
		$mouse_drag      = ( $s['mouse_drag'] ?? '' ) === 'yes';
		$dir             = $this->resolve_direction( $s['direction'] ?? 'auto' );
		$is_rtl          = ( 'rtl' === $dir );

		$style_vars = sprintf(
			'--cs-per: %d; --cs-per-tablet: %d; --cs-per-mobile: %d; --cs-gap: %dpx; --cs-card-h: %dpx;',
			$per_view,
			$per_view_tablet,
			$per_view_mobile,
			$gap,
			$card_height
		);

		$total = count( $terms );
		?>
		<div class="cs" dir="<?php echo esc_attr( $dir ); ?>" data-cs-snap="<?php echo esc_attr( $snap ); ?>" data-cs-mouse-drag="<?php echo $mouse_drag ? '1' : '0'; ?>" style="<?php echo esc_attr( $style_vars ); ?>">
			<div class="cs-head">
				<?php if ( ! empty( $s['eyebrow'] ) ) : ?>
					<div class="cs-eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $s['headline'] ) || ! empty( $s['headline_mute'] ) ) : ?>
					<div class="cs-headline">
						<?php echo esc_html( $s['headline'] ); ?>
						<?php if ( ! empty( $s['headline_mute'] ) ) : ?>
							<span class="cs-headline-mute"> <?php echo esc_html( $s['headline_mute'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( $show_arrows ) : ?>
					<div class="cs-arrows" role="group" aria-label="<?php esc_attr_e( 'Scroll categories', 'freeman-core' ); ?>">
						<button type="button" class="cs-arrow" data-cs-dir="-1" aria-label="<?php esc_attr_e( 'Previous', 'freeman-core' ); ?>">
							<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M9 3L5 7L9 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
						<button type="button" class="cs-arrow" data-cs-dir="1" aria-label="<?php esc_attr_e( 'Next', 'freeman-core' ); ?>">
							<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M5 3L9 7L5 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
						</button>
					</div>
				<?php endif; ?>
			</div>

			<div class="cs-track" data-cs-track>
				<?php foreach ( $terms as $term ) :
					$url   = $this->safe_term_url( $term );
					$count = (int) $term->count;
					$thumb = $this->get_thumbnail_url( $term->term_id );
					$hue   = $this->hue_from( $term->slug );
					ob_start();
					?>
					<a
						href="<?php echo esc_url( $url ); ?>"
						class="cs-card cs-shape-<?php echo esc_attr( $shape ); ?>"
						data-cat="1"
						aria-label="<?php echo esc_attr( sprintf(
							/* translators: 1: category name, 2: product count */
							_n( '%1$s — %2$s item', '%1$s — %2$s items', $count, 'freeman-core' ),
							$term->name,
							number_format_i18n( $count )
						) ); ?>"
					>
						<div class="cs-imgwrap">
							<?php if ( $thumb ) : ?>
								<div class="cs-img" style="background-image:url('<?php echo esc_url( $thumb ); ?>');"></div>
							<?php else : ?>
								<div class="cs-img cs-img-placeholder" style="--cs-hue: <?php echo (int) $hue; ?>;">
									<span class="cs-img-label"><?php echo esc_html( strtolower( $term->name ) ); ?></span>
								</div>
							<?php endif; ?>
							<div class="cs-ring" aria-hidden="true"></div>
						</div>
						<div class="cs-meta">
							<span class="cs-name"><?php echo esc_html( $term->name ); ?></span>
							<?php if ( 'none' !== $show_count ) : ?>
								<span class="cs-count cs-count-<?php echo esc_attr( $show_count ); ?>">
									<?php
									printf(
										/* translators: %s = product count */
										esc_html( _n( '%s item', '%s items', $count, 'freeman-core' ) ),
										esc_html( number_format_i18n( $count ) )
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</a>
					<?php
					$card_html = (string) ob_get_clean();
					/**
					 * Filter the rendered HTML for a single Category Slider card.
					 *
					 * @since 1.11.1
					 *
					 * @param string   $card_html Rendered card HTML (already escaped).
					 * @param \WP_Term $term      Source term.
					 * @param array    $context   Render context: url, count, thumb, hue, shape, show_count.
					 */
					echo apply_filters(
						'freeman_core/category_slider/render_card',
						$card_html,
						$term,
						array(
							'url'        => $url,
							'count'      => $count,
							'thumb'      => $thumb,
							'hue'        => $hue,
							'shape'      => $shape,
							'show_count' => $show_count,
						)
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $card_html already escaped; filter contract documented above.
				endforeach;
				?>
			</div>

			<?php if ( $show_progress ) : ?>
				<div class="cs-foot">
					<div class="cs-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
						<div class="cs-progress-bar" data-cs-progress-bar></div>
					</div>
					<div class="cs-foot-label" data-cs-foot-label>
						<span data-cs-foot-current><?php echo esc_html( str_pad( (string) min( $total, $per_view ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<span class="cs-foot-sep"> / <?php echo esc_html( str_pad( (string) $total, 2, '0', STR_PAD_LEFT ) ); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
