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

	/* -----------------------------------------------------------------
	 * Read path (the facet engine — Phase 6.3a)
	 * ----------------------------------------------------------------- */

	/**
	 * Distinct taxonomies present in the index (e.g. product_cat, pa_color).
	 * Drives the auto-derived facet config.
	 *
	 * @return string[]
	 */
	public function available_taxonomies() {
		global $wpdb;
		$table = Database::table_name();
		$rows  = $wpdb->get_col( "SELECT DISTINCT taxonomy FROM {$table} ORDER BY taxonomy" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return array_map( 'strval', (array) $rows );
	}

	/**
	 * Product ids assigned to any of the given terms of one taxonomy. Used to
	 * seed the base universe on a category page (the caller passes the queried
	 * category plus its descendants, since the index stores only directly-
	 * assigned product_cat rows).
	 *
	 * @param string $taxonomy      Taxonomy.
	 * @param int[]  $term_ids      Term ids.
	 * @param bool   $in_stock_only Restrict to in-stock products (the product_cat
	 *                              rows carry overall product stock, so this
	 *                              mirrors a store that hides out-of-stock items).
	 * @return int[]
	 */
	public function product_ids_in_terms( $taxonomy, array $term_ids, $in_stock_only = false ) {
		global $wpdb;
		$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
		if ( '' === (string) $taxonomy || empty( $term_ids ) ) {
			return array();
		}
		$table        = Database::table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );
		$sql          = "SELECT DISTINCT product_id FROM {$table} WHERE taxonomy = %s AND term_id IN ({$placeholders})";
		if ( $in_stock_only ) {
			$sql .= ' AND in_stock = 1';
		}
		$args = array_merge( array( (string) $taxonomy ), $term_ids );
		$rows = $wpdb->get_col( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return array_map( 'intval', (array) $rows );
	}

	/**
	 * All distinct product ids in the index (the shop-page base universe).
	 *
	 * @param bool $in_stock_only Restrict to products with an in-stock row
	 *                            (a fully out-of-stock product has every row at
	 *                            in_stock=0, so it is excluded — matching a store
	 *                            that hides out-of-stock items).
	 * @return int[]
	 */
	public function all_product_ids( $in_stock_only = false ) {
		global $wpdb;
		$table = Database::table_name();
		$where = $in_stock_only ? ' WHERE in_stock = 1' : '';
		$rows  = $wpdb->get_col( "SELECT DISTINCT product_id FROM {$table}{$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return array_map( 'intval', (array) $rows );
	}

	/**
	 * Build the inverted index slice for a set of products: taxonomy => term_id
	 * => product ids. When $in_stock_only is true only rows flagged in_stock=1
	 * are returned, so an out-of-stock-only value disappears under the
	 * in-stock-only filter (requirement #2).
	 *
	 * @param int[] $product_ids   Base product ids.
	 * @param bool  $in_stock_only Restrict to in-stock rows.
	 * @return array<string,array<int,int[]>>
	 */
	public function postings_for_products( array $product_ids, $in_stock_only = false ) {
		global $wpdb;
		$product_ids = array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );
		if ( empty( $product_ids ) ) {
			return array();
		}
		$table        = Database::table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
		$where        = "product_id IN ({$placeholders})";
		if ( $in_stock_only ) {
			$where .= ' AND in_stock = 1';
		}
		$sql  = "SELECT taxonomy, term_id, product_id FROM {$table} WHERE {$where}";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $product_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		$postings = array();
		foreach ( (array) $rows as $row ) {
			$postings[ (string) $row->taxonomy ][ (int) $row->term_id ][] = (int) $row->product_id;
		}
		return $postings;
	}

	/**
	 * Per-term index stats for the diagnostic: one row per (taxonomy, term_id)
	 * with the indexed product count and the in-stock count. The composite PK is
	 * (product_id, taxonomy, term_id), so COUNT(*) per group is the distinct
	 * product count.
	 *
	 * @return array<int,array{taxonomy:string,term_id:int,products:int,in_stock:int}>
	 */
	public function term_stats() {
		global $wpdb;
		$table = Database::table_name();
		$rows  = $wpdb->get_results( "SELECT taxonomy, term_id, COUNT(*) AS products, SUM(in_stock) AS in_stock FROM {$table} GROUP BY taxonomy, term_id ORDER BY taxonomy, term_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		$stats = array();
		foreach ( (array) $rows as $row ) {
			$stats[] = array(
				'taxonomy' => (string) $row->taxonomy,
				'term_id'  => (int) $row->term_id,
				'products' => (int) $row->products,
				'in_stock' => (int) $row->in_stock,
			);
		}
		return $stats;
	}
}
