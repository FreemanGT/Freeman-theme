<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Database;
use PHPUnit\Framework\TestCase;

/**
 * The index-table SQL shape. Tests the pure schema_sql() builder; the live
 * install()/drop() ($wpdb + dbDelta) are integration / live QA.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Database
 */
final class ShopFiltersDatabaseTest extends TestCase {

	public function test_schema_sql_contains_columns_and_keys(): void {
		$sql = Database::schema_sql( 'wp_freeman_shop_filter_index', 'DEFAULT CHARSET=utf8mb4' );

		$this->assertStringContainsString( 'CREATE TABLE wp_freeman_shop_filter_index', $sql );
		$this->assertStringContainsString( 'product_id bigint(20) unsigned NOT NULL', $sql );
		$this->assertStringContainsString( 'taxonomy varchar(32) NOT NULL', $sql );
		$this->assertStringContainsString( 'term_id bigint(20) unsigned NOT NULL', $sql );
		$this->assertStringContainsString( 'in_stock tinyint(1) NOT NULL DEFAULT 1', $sql );
		$this->assertStringContainsString( 'PRIMARY KEY (product_id, taxonomy, term_id)', $sql );
		$this->assertStringContainsString( 'KEY tax_term_stock (taxonomy, term_id, in_stock)', $sql );
		$this->assertStringContainsString( 'DEFAULT CHARSET=utf8mb4', $sql );
	}

	public function test_schema_sql_omits_price_and_rating_columns(): void {
		// The table deliberately omits price / rating — those come from
		// wc_product_meta_lookup. Guard against accidental duplication.
		$sql = Database::schema_sql( 'wp_x', '' );

		$this->assertStringNotContainsString( 'min_price', $sql );
		$this->assertStringNotContainsString( 'max_price', $sql );
		$this->assertStringNotContainsString( 'rating', $sql );
	}
}
