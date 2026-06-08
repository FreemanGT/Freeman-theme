<?php
/**
 * Shop Filters facet-configuration matrix — an admin surface on the
 * Freeman → Shop Filters page that lets the shop owner override the
 * auto-derived facet defaults: turn a filter on/off, reorder it, and hide it
 * on chosen categories. Writes the `freeman_core_shop_filters_facet_config`
 * option that Facet_Config reads.
 *
 * When no configuration has been saved, Facet_Config falls back to the
 * automatic defaults. The "type" of each facet is auto-derived (categories =
 * tree, attributes = checkbox/swatch), so the matrix exposes only the controls
 * that change real storefront output.
 *
 * The render echoes (live-QA); the sanitisation lives in the pure, unit-tested
 * Facet_Config::sanitize().
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

use Freeman\Core\Core\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Facet-config admin matrix.
 */
final class Admin_Config_Page {

	const NONCE  = 'freeman_core_shop_filters_save_facet_config';
	const ACTION = 'freeman_core_shop_filters_save_facet_config';

	/**
	 * Index storage (source of the configurable taxonomies).
	 *
	 * @var Index_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param Index_Repository|null $repo Repository (injected for tests).
	 */
	public function __construct( Index_Repository $repo = null ) {
		$this->repo = $repo ? $repo : new Index_Repository();
	}

	/**
	 * Register the matrix renderer + the save handler.
	 */
	public function boot() {
		add_action( 'freeman_core/module_page/shop_filters', array( $this, 'render' ), 30 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_save' ) );
	}

	/**
	 * Persist a matrix submission.
	 */
	public function handle_save() {
		Security::verify_nonce( self::NONCE );
		Security::require_cap( 'manage_woocommerce' );

		$raw   = ( isset( $_POST['facets'] ) && is_array( $_POST['facets'] ) )
			? wp_unslash( $_POST['facets'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised by Facet_Config::sanitize() below (whitelists taxonomies + coerces every value).
			: array();
		$valid = $this->repo->available_taxonomies();

		update_option( Facet_Config::OPTION, Facet_Config::sanitize( $raw, $valid ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'freeman-shop_filters',
					'facets-updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the matrix on the module settings page.
	 */
	public function render() {
		$taxonomies = $this->repo->available_taxonomies();

		echo '<h2>' . esc_html__( 'Filter configuration', 'freeman-core' ) . '</h2>';

		if ( empty( $taxonomies ) ) {
			echo '<p>' . esc_html__( 'No indexed attributes yet. Build the index (above) first — the filters you can configure are the ones the index holds.', 'freeman-core' ) . '</p>';
			return;
		}

		$rows = array();
		foreach ( Facet_Config::all_defs( $taxonomies ) as $def ) {
			$rows[] = array(
				'taxonomy' => (string) $def['taxonomy'],
				'label'    => self::taxonomy_label( (string) $def['taxonomy'] ),
				'enabled'  => ! empty( $def['enabled'] ),
				'order'    => (int) ( $def['order'] ?? 0 ),
				'hide'     => array_map( 'intval', (array) ( $def['hide_on_categories'] ?? array() ) ),
			);
		}

		$categories   = $this->category_choices();
		$action_url   = admin_url( 'admin-post.php' );
		$action_name  = self::ACTION;
		$nonce_action = self::NONCE;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
		$saved = isset( $_GET['facets-updated'] );

		include FREEMAN_CORE_PATH . 'src/Modules/ShopFilters/templates/admin-facet-config.php';
	}

	/**
	 * Human label for a configurable taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	public static function taxonomy_label( $taxonomy ) {
		if ( 'product_cat' === $taxonomy ) {
			return __( 'Categories', 'freeman-core' );
		}
		if ( function_exists( 'wc_attribute_label' ) ) {
			return (string) wc_attribute_label( $taxonomy );
		}
		return (string) $taxonomy;
	}

	/**
	 * All product categories as an ordered, depth-tagged list for the
	 * hide-on-categories multiselect (parent → child, indented).
	 *
	 * @return array<int,array{id:int,name:string,depth:int}>
	 */
	private function category_choices() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		$ordered = array();
		$walk    = static function ( $parent_id, $depth ) use ( &$walk, &$ordered, $by_parent ) {
			if ( empty( $by_parent[ $parent_id ] ) ) {
				return;
			}
			foreach ( $by_parent[ $parent_id ] as $term ) {
				$ordered[] = array(
					'id'    => (int) $term->term_id,
					'name'  => (string) $term->name,
					'depth' => (int) $depth,
				);
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );

		return $ordered;
	}
}
