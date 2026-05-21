<?php
/**
 * Thin $wpdb wrapper over the Shop Filters index table.
 *
 * Keeps all raw SQL for the table in one place. Write methods are used by the
 * Indexer; read methods grow in later phases (the facet engine). $wpdb-touching
 * by nature, so exercised by integration / live QA rather than unit tests.
 *
 * @package FreemanCore
 */

namespace Freeman\Core\Modules\ShopFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Index repository.
 */
final class Index_Repository {

	/**
	 * Replace all of a product's rows with the supplied set, atomically enough
	 * for our purposes (delete then bulk insert). Each row is an associative
	 * array: product_id, taxonomy, term_id, in_stock.
	 *
	 * @param int   $product_id Product id.
	 * @param array $rows       Rows to insert.
	 */
	public function replace_product( $product_id, array $rows ) {
		global $wpdb;
		$table      = Database::table_name();
		$product_id = (int) $product_id;

		$wpdb->delete( $table, array( 'product_id' => $product_id ), array( '%d' ) );

		if ( empty( $rows ) ) {
			return;
		}

		$placeholders = array();
		$values       = array();
		foreach ( $rows as $row ) {
			$placeholders[] = '(%d, %s, %d, %d)';
			$values[]       = (int) $row['product_id'];
			$values[]       = (string) $row['taxonomy'];
			$values[]       = (int) $row['term_id'];
			$values[]       = empty( $row['in_stock'] ) ? 0 : 1;
		}

		// INSERT IGNORE so a defensive duplicate row never aborts the batch.
		$sql = "INSERT IGNORE INTO {$table} (product_id, taxonomy, term_id, in_stock) VALUES " . implode( ', ', $placeholders );
		$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Remove a product's rows (e.g. it was deleted or became non-indexable).
	 *
	 * @param int $product_id Product id.
	 */
	public function delete_product( $product_id ) {
		global $wpdb;
		$wpdb->delete( Database::table_name(), array( 'product_id' => (int) $product_id ), array( '%d' ) );
	}

	/**
	 * Distinct products currently represented in the index.
	 *
	 * @return int
	 */
	public function count_indexed_products() {
		global $wpdb;
		$table = Database::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Total rows in the index.
	 *
	 * @return int
	 */
	public function count_rows() {
		global $wpdb;
		$table = Database::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Empty the whole index (used before a full rebuild).
	 */
	public function clear_all() {
		global $wpdb;
		$table = Database::table_name();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}
}
