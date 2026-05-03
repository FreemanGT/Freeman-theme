<?php
/**
 * Settings hub — owns the single "Freeman" admin menu and auto-renders every
 * module's declarative settings schema.
 *
 * Module settings schemas return an associative array of:
 *   'setting_key' => [
 *       'label'       => string,
 *       'type'        => 'text' | 'textarea' | 'checkbox' | 'select' | 'color' | 'number',
 *       'description' => string,
 *       'default'     => mixed,
 *       'choices'     => [ value => label ]   (for select),
 *       'section'     => string,              (optional grouping)
 *   ]
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Settings hub.
 */
final class Settings_Hub {

	const MENU_SLUG = 'freeman';
	const CAP       = 'manage_woocommerce';

	/**
	 * Registry reference.
	 *
	 * @var Module_Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Module_Registry $registry Registry.
	 */
	public function __construct( Module_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Register admin hooks.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_freeman_toggle_module', array( $this, 'handle_toggle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin CSS on Freeman screens.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'freeman' ) ) {
			return;
		}
		wp_enqueue_style(
			'freeman-core-admin',
			FREEMAN_CORE_ASSETS . '/css/admin.css',
			array(),
			FREEMAN_CORE_VERSION
		);
	}

	/**
	 * Register menu + submenus (one per module).
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Freeman', 'freeman-core' ),
			__( 'Freeman', 'freeman-core' ),
			self::CAP,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-admin-generic',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'freeman-core' ),
			__( 'Dashboard', 'freeman-core' ),
			self::CAP,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		foreach ( $this->registry->all() as $module ) {
			$id = $module->id();
			add_submenu_page(
				self::MENU_SLUG,
				$module->label(),
				$module->label(),
				self::CAP,
				self::MENU_SLUG . '-' . $id,
				function () use ( $module ) {
					$this->render_module_page( $module );
				}
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tools', 'freeman-core' ),
			__( 'Tools', 'freeman-core' ),
			self::CAP,
			self::MENU_SLUG . '-tools',
			array( $this, 'render_tools' )
		);
	}

	/**
	 * Register option-API settings for every module's schema.
	 */
	public function register_settings() {
		foreach ( $this->registry->all() as $module ) {
			$schema = $module->settings_schema();
			if ( empty( $schema ) ) {
				continue;
			}
			foreach ( $schema as $key => $def ) {
				$option = $module->option_name( $key );
				register_setting(
					'freeman_' . $module->id(),
					$option,
					array(
						'sanitize_callback' => $this->sanitizer_for( $def ),
						'default'           => isset( $def['default'] ) ? $def['default'] : '',
					)
				);
			}
		}

		register_setting(
			'freeman_core_modules_group',
			'freeman_core_modules',
			array(
				'sanitize_callback' => array( $this, 'sanitize_modules_toggle' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize an incoming module-toggle form.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public function sanitize_modules_toggle( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$output = array();
		foreach ( $this->registry->all() as $module ) {
			$output[ $module->id() ] = ! empty( $input[ $module->id() ] );
		}
		return $output;
	}

	/**
	 * Pick a sanitizer callback for a schema entry.
	 *
	 * @param array $def Schema entry.
	 * @return callable
	 */
	private function sanitizer_for( $def ) {
		$type = isset( $def['type'] ) ? $def['type'] : 'text';
		switch ( $type ) {
			case 'textarea':
				return 'sanitize_textarea_field';
			case 'checkbox':
				return static function ( $v ) {
					return ! empty( $v ) ? 1 : 0;
				};
			case 'number':
				return static function ( $v ) {
					return is_numeric( $v ) ? $v + 0 : 0;
				};
			case 'email':
				return 'sanitize_email';
			case 'url':
				return 'esc_url_raw';
			case 'color':
				return static function ( $v ) {
					return preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $v ) ? $v : '';
				};
			default:
				return 'sanitize_text_field';
		}
	}

	/**
	 * Handle a POST that toggles a single module on/off (used by Dashboard).
	 */
	public function handle_toggle() {
		Security::verify_nonce( 'freeman_toggle_module' );
		Security::require_cap( self::CAP );

		$id      = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		$enabled = ! empty( $_POST['enabled'] );

		if ( $id && $this->registry->get( $id ) ) {
			$this->registry->set_enabled( $id, $enabled );
			$module = $this->registry->get( $id );
			if ( $enabled ) {
				$module->on_activate();
			} else {
				$module->on_deactivate();
			}
			flush_rewrite_rules();
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/* -----------------------------------------------------------------
	 * Renderers (delegated to Admin\Dashboard and simple inline renderers)
	 * ----------------------------------------------------------------- */

	/**
	 * Dashboard page.
	 */
	public function render_dashboard() {
		$plugin    = Plugin::instance();
		$dashboard = new \Freeman\Core\Admin\Dashboard( $plugin );
		$dashboard->render();
	}

	/**
	 * Render a single module's settings page.
	 *
	 * @param Module_Interface $module Module.
	 */
	public function render_module_page( $module ) {
		Security::require_cap( self::CAP );

		$schema = $module->settings_schema();
		echo '<div class="wrap freeman-wrap">';
		echo '<h1>' . esc_html( $module->label() ) . '</h1>';
		echo '<p class="description">' . esc_html( $module->description() ) . '</p>';

		if ( ! $module->is_enabled() ) {
			echo '<div class="notice notice-warning inline"><p>' .
				esc_html__( 'This module is disabled. Enable it from the Dashboard.', 'freeman-core' ) .
				'</p></div>';
		}

		if ( ! empty( $schema ) ) {
			echo '<form method="post" action="options.php">';
			settings_fields( 'freeman_' . $module->id() );

			$sections = array();
			foreach ( $schema as $key => $def ) {
				$section                  = isset( $def['section'] ) ? $def['section'] : __( 'General', 'freeman-core' );
				$sections[ $section ][ $key ] = $def;
			}

			foreach ( $sections as $section_title => $fields ) {
				echo '<h2>' . esc_html( $section_title ) . '</h2>';
				echo '<table class="form-table"><tbody>';
				foreach ( $fields as $key => $def ) {
					$this->render_field( $module, $key, $def );
				}
				echo '</tbody></table>';
			}

			submit_button();
			echo '</form>';
		}

		do_action( 'freeman_core/module_page/' . $module->id(), $module );
		echo '</div>';
	}

	/**
	 * Render a single schema field.
	 *
	 * @param Module_Interface $module Module.
	 * @param string           $key    Setting key.
	 * @param array            $def    Schema entry.
	 */
	private function render_field( $module, $key, $def ) {
		$option = $module->option_name( $key );
		$value  = get_option( $option, isset( $def['default'] ) ? $def['default'] : '' );
		$label  = isset( $def['label'] ) ? $def['label'] : $key;
		$desc   = isset( $def['description'] ) ? $def['description'] : '';
		$type   = isset( $def['type'] ) ? $def['type'] : 'text';

		echo '<tr><th scope="row"><label for="' . esc_attr( $option ) . '">' . esc_html( $label ) . '</label></th><td>';

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%1$s" rows="4" cols="60" class="large-text">%2$s</textarea>',
					esc_attr( $option ),
					esc_textarea( (string) $value )
				);
				break;
			case 'checkbox':
				$checked = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				printf(
					'<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s/> %3$s</label>',
					esc_attr( $option ),
					checked( true === $checked, true, false ),
					esc_html( isset( $def['checkbox_label'] ) ? $def['checkbox_label'] : '' )
				);
				break;
			case 'select':
				$choices = isset( $def['choices'] ) ? $def['choices'] : array();
				echo '<select id="' . esc_attr( $option ) . '" name="' . esc_attr( $option ) . '">';
				foreach ( $choices as $val => $label2 ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $val ),
						selected( $value, $val, false ),
						esc_html( $label2 )
					);
				}
				echo '</select>';
				break;
			case 'color':
				printf(
					'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text freeman-color-field" placeholder="#000000"/>',
					esc_attr( $option ),
					esc_attr( (string) $value )
				);
				break;
			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%1$s" value="%2$s" class="small-text"/>',
					esc_attr( $option ),
					esc_attr( (string) $value )
				);
				break;
			default:
				printf(
					'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text"/>',
					esc_attr( $option ),
					esc_attr( (string) $value )
				);
		}

		if ( $desc ) {
			echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
		}

		echo '</td></tr>';
	}

	/**
	 * Tools page.
	 */
	public function render_tools() {
		Security::require_cap( self::CAP );
		include FREEMAN_CORE_PATH . 'src/Admin/views/tools.php';
	}
}
