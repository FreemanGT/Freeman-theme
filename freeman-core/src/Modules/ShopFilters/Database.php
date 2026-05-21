<?php
/**
 * Shop Filters index table installer.
 *
 * Owns the module's single custom table, a narrow "term / category membership"
 * fact table used to compute facet availability and counts quickly. It stores
 * only what WooCommerce's own {prefix}wc_product_meta_lookup cannot express —
 * per-attribute-value in-stock truth and category membership; price / stock /
 * rating are read from wc_product_meta_lookup, never duplicated here.
 *
 * Installed automatically by Freeman\Core\Core\Migrations::run() on the version
 * bump (it discovers any module's sibling Database::install()). Dropped on
 * uninstall via Module::on_uninstall(). Inert on a version downgrade.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Index table installer.
 */
final class Database {

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'freeman_shop_filter_index';
	}

	/**
	 * dbDelta-compatible CREATE TABLE statement. Split out from install() so the
	 * SQL shape is unit-testable without a live $wpdb / dbDelta.
	 *
	 * The composite primary key (product_id, taxonomy, term_id) guarantees one
	 * row per membership and makes product-keyed deletes (reindex) fast via the
	 * leftmost PK column. The single secondary index (taxonomy, term_id,
	 * in_stock) covers facet-value lookups and in-stock-only filtering.
	 *
	 * @param string $table   Table name.
	 * @param string $charset Charset/collation clause (from $wpdb->get_charset_collate()).
	 * @return string
	 */
	public static function schema_sql( $table, $charset = '' ) {
		return "CREATE TABLE {$table} (
			product_id bigint(20) unsigned NOT NULL,
			taxonomy varchar(32) NOT NULL,
			term_id bigint(20) unsigned NOT NULL,
			in_stock tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (product_id, taxonomy, term_id),
			KEY tax_term_stock (taxonomy, term_id, in_stock)
		) {$charset};";
	}

	/**
	 * Create / upgrade the table. Idempotent (dbDelta).
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::schema_sql( self::table_name(), $wpdb->get_charset_collate() ) );
	}

	/**
	 * Drop the table. Called on uninstall — the data is a pure derived cache
	 * with no user value, so unlike subscriber tables we drop rather than keep.
	 */
	public static function drop() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
