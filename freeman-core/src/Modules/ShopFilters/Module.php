<?php
/**
 * Shop Filters module.
 *
 * Faceted AJAX product filters for shop / category pages, built on a background
 * index. Covers the background index + "Reindex now" tool, the storefront read
 * path (the [freeman_shop_filters] shortcode + public query endpoint), the
 * filtered-URL SEO policy, and the admin facet-configuration matrix. Graduated
 * to always-on in 1.12.25 (previously gated by per-surface feature flags).
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

use Freeman\Core\Core\Module_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Module.
 */
final class Module extends Module_Base {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id() {
		return 'shop_filters';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Shop Filters', 'freeman-core' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Faceted, context-aware product filters for shop and category pages, backed by a lightweight background index.', 'freeman-core' );
	}

	/**
	 * Settings schema. Exposes the storefront label overrides, price bands and
	 * default sort on the Freeman → Shop Filters page.
	 *
	 * @return array
	 */
	public function settings_schema() {
		$schema = array();

		// Storefront label overrides: one text field per panel string. Blank = the
		// English default (Labels::get() falls back), so leaving these empty keeps
		// the current output. Lets the site set Hebrew wording without code.
		$first = true;
		foreach ( Labels::defaults() as $key => $def ) {
			/* translators: %s: the English default wording for this field. */
			$desc = sprintf( __( 'Default: %s', 'freeman-core' ), $def['default'] );
			if ( $first ) {
				$desc = __( 'Filter panel wording — leave a field blank to use its English default.', 'freeman-core' ) . ' ' . $desc;
			}
			$schema[ 'label_' . $key ] = array(
				'label'       => $def['label'],
				'type'        => 'text',
				'default'     => '',
				'description' => $desc,
			);
			$first = false;
		}

		$schema['price_bands'] = array(
			'label'       => __( 'Price bands', 'freeman-core' ),
			'type'        => 'text',
			'default'     => '',
			'description' => __( 'Comma-separated upper price points for the price filter, e.g. 50, 100, 200, 500 (gives 0–50, 50–100, 100–200, 200–500, 500+). Leave blank to auto-derive bands from your catalogue prices.', 'freeman-core' ),
		);

		$choices = array( '' => __( '— WooCommerce default —', 'freeman-core' ) );
		foreach ( Url_State::orderby_whitelist() as $orderby ) {
			$choices[ $orderby ] = self::orderby_label( $orderby );
		}
		$schema['default_sort'] = array(
			'label'       => __( 'Default sort', 'freeman-core' ),
			'type'        => 'select',
			'choices'     => $choices,
			'default'     => '',
			'description' => __( 'Default product ordering for shop and category pages (until the shopper picks a sort). Blank keeps the WooCommerce default.', 'freeman-core' ),
		);

		return $schema;
	}

	/**
	 * Human label for a sort option (mirrors WooCommerce's catalog-ordering names).
	 *
	 * @param string $orderby Orderby key from Url_State::orderby_whitelist().
	 * @return string
	 */
	public static function orderby_label( $orderby ) {
		$labels = array(
			'menu_order' => __( 'Default sorting', 'freeman-core' ),
			'popularity' => __( 'Popularity', 'freeman-core' ),
			'rating'     => __( 'Average rating', 'freeman-core' ),
			'date'       => __( 'Latest', 'freeman-core' ),
			'price'      => __( 'Price: low to high', 'freeman-core' ),
			'price-desc' => __( 'Price: high to low', 'freeman-core' ),
		);
		return isset( $labels[ $orderby ] ) ? $labels[ $orderby ] : (string) $orderby;
	}

	/**
	 * Boot. Registers the background indexer (lifecycle hooks + reconcile sweep),
	 * the storefront read path (shortcode + public query endpoint), the
	 * filtered-URL SEO policy, and — in wp-admin — the index status / reindex
	 * tool and the facet-configuration matrix.
	 */
	public function boot() {
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) );

		$indexer = new Indexer();
		$indexer->register_hooks();
		// Defer scheduling to `init`: Action Scheduler's store isn't ready at
		// plugins_loaded, so as_schedule_recurring_action() there silently
		// no-ops. Running at `init` (after AS initialises) makes both the
		// schedule call and the status check use the same, ready scheduler.
		add_action( 'init', array( $indexer, 'ensure_scheduled' ) );

		( new Query() )->register();
		( new Shortcode() )->register();
		( new Ajax() )->register();

		// Filtered-URL SEO policy acts on filter params in the URL whether or not
		// the storefront panel is rendered.
		( new Seo() )->register();

		if ( is_admin() ) {
			( new Admin_Page( $indexer ) )->boot();
			( new Admin_Config_Page() )->boot();
		}
	}

	/**
	 * Register the 5-minute recurrence the wp-cron fallback path uses. (When
	 * Action Scheduler is available the indexer uses that instead and this is
	 * unused but harmless.)
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public function register_cron_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		if ( ! isset( $schedules[ Indexer::CRON_SCHEDULE ] ) ) {
			$schedules[ Indexer::CRON_SCHEDULE ] = array(
				'interval' => Indexer::SWEEP_INTERVAL,
				'display'  => __( 'Every 5 minutes (Freeman Shop Filters)', 'freeman-core' ),
			);
		}
		return $schedules;
	}

	/**
	 * On deactivation — clear the indexer's scheduled events and queue.
	 */
	public function on_deactivate() {
		( new Indexer() )->unschedule();
	}

	/**
	 * On uninstall — delete the module's options, clear scheduling, and drop the
	 * index table (the data is a pure derived cache with no user value).
	 */
	public function on_uninstall() {
		parent::on_uninstall();
		( new Indexer() )->unschedule();
		Database::drop();
	}
}
