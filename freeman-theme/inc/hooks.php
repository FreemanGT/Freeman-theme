<?php
/**
 * Theme-level hooks that customise Freeman Core behaviour from the theme.
 *
 * Keep this file small — it's the seam between the theme and the plugin.
 *
 * @package FreemanTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Expose the theme's accent colour to Core modules via a filter so CSS tokens
 * can be generated consistently.
 */
add_filter(
	'freeman_core/design/tokens',
	static function ( array $tokens ) {
		$defaults = array(
			'accent'       => '#111111',
			'accent_text'  => '#ffffff',
			'card_radius'  => '6px',
			'card_gap'     => '24px',
			'motion_fast'  => '180ms',
			'motion_base'  => '280ms',
		);
		return array_merge( $defaults, $tokens );
	}
);
