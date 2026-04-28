<?php
/**
 * Freeman Theme main class.
 *
 * @package FreemanTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the Freeman child theme.
 */
final class Freeman_Theme {

	/**
	 * Singleton instance.
	 *
	 * @var Freeman_Theme|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton.
	 *
	 * @return Freeman_Theme
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook registration.
	 */
	private function __construct() {
		add_action( 'after_setup_theme', array( $this, 'setup' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Theme supports. Hello Elementor already declares most of these but we
	 * add our own so the theme is self-contained if Hello ever changes.
	 */
	public function setup() {
		add_theme_support( 'align-wide' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'post-thumbnails' );

		if ( apply_filters( 'freeman_theme_add_woocommerce_support', true ) ) {
			add_theme_support( 'woocommerce' );
			add_theme_support( 'wc-product-gallery-zoom' );
			add_theme_support( 'wc-product-gallery-lightbox' );
			add_theme_support( 'wc-product-gallery-slider' );
		}
	}

	/**
	 * Load text-domain for translations.
	 */
	public function load_textdomain() {
		load_child_theme_textdomain( 'freeman-theme', FREEMAN_THEME_PATH . '/languages' );
	}

	/**
	 * Enqueue theme stylesheets and scripts.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'freeman-tokens',
			FREEMAN_THEME_ASSETS . '/css/freeman-tokens.css',
			array( 'hello-elementor-theme-style' ),
			FREEMAN_THEME_VERSION
		);

		wp_enqueue_style(
			'freeman-theme',
			FREEMAN_THEME_ASSETS . '/css/freeman.css',
			array( 'freeman-tokens' ),
			FREEMAN_THEME_VERSION
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'freeman-theme-rtl',
				FREEMAN_THEME_ASSETS . '/css/freeman-rtl.css',
				array( 'freeman-theme' ),
				FREEMAN_THEME_VERSION
			);
		}

		wp_enqueue_script(
			'freeman-theme',
			FREEMAN_THEME_ASSETS . '/js/freeman.js',
			array(),
			FREEMAN_THEME_VERSION,
			true
		);
	}

	/**
	 * Add identifying body class so module CSS can scope to the theme.
	 *
	 * @param string[] $classes Existing classes.
	 * @return string[]
	 */
	public function add_body_class( $classes ) {
		$classes[] = 'freeman-theme';
		if ( is_rtl() ) {
			$classes[] = 'freeman-rtl';
		}
		return $classes;
	}
}
