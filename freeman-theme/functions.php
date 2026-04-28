<?php
/**
 * Freeman Theme bootstrap.
 *
 * @package FreemanTheme
 */

defined( 'ABSPATH' ) || exit;

define( 'FREEMAN_THEME_VERSION',   '1.0.3' );
define( 'FREEMAN_THEME_PATH',      get_stylesheet_directory() );
define( 'FREEMAN_THEME_URL',       get_stylesheet_directory_uri() );
define( 'FREEMAN_THEME_ASSETS',    FREEMAN_THEME_URL . '/assets' );
define( 'FREEMAN_CORE_MIN_VERSION', '1.0.0' );

require_once FREEMAN_THEME_PATH . '/inc/class-freeman-theme.php';
require_once FREEMAN_THEME_PATH . '/inc/plugin-dependencies.php';
require_once FREEMAN_THEME_PATH . '/inc/hooks.php';
require_once FREEMAN_THEME_PATH . '/inc/woocommerce.php';

Freeman_Theme::instance();
Freeman_Theme_Plugin_Dependencies::instance();
