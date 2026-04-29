<?php
/**
 * Minimal `\WC_Product` stub used by XmlSnapshotTest.
 *
 * The real WooCommerce class is not available under PHPUnit. This shim
 * declares just enough API for ProductFeed Generator's simple-product
 * code paths to run against deterministic data.
 *
 * Methods covered (all reached by Generator::product_xml() simple branch
 * and its helpers Generator::common_fields() / stock_fields() /
 * price_fields()):
 *
 *   is_type, is_visible, get_id, get_sku, get_name, get_slug, get_status,
 *   get_catalog_visibility, get_description, get_short_description,
 *   get_date_created, get_date_modified, get_weight, get_length,
 *   get_width, get_height, get_tax_class, get_tax_status, get_image_id,
 *   get_gallery_image_ids, get_attributes, get_type, is_in_stock,
 *   get_stock_quantity, get_stock_status, get_manage_stock,
 *   backorders_allowed, get_low_stock_amount, get_price,
 *   get_regular_price, get_sale_price, is_on_sale,
 *   get_date_on_sale_from, get_date_on_sale_to, get_permalink
 *
 * NOT covered (Wave 2.1 will need to extend the stub):
 *   - The variable-product branch: get_variation_price,
 *     get_variation_regular_price, get_children, plus a full
 *     `\WC_Product_Variation` stub for variation_xml().
 *   - Anything Generator::write_feed() reaches via wc_get_products()
 *     / get_posts() iteration is bypassed; the test invokes
 *     product_xml() directly via Reflection.
 *
 * If you add a new getter call inside Generator and the snapshot test
 * starts failing with a "call to undefined method" fatal, add the
 * method here AND extend the comment block above.
 *
 * @package FreemanCore
 */

// phpcs:disable Generic.Classes.DuplicateClassName.Found

if ( ! class_exists( '\WC_Product' ) ) {

	class WC_Product {

		public function is_type( $type ) {
			return 'simple' === $type;
		}

		public function is_visible() {
			return true;
		}

		public function get_id() {
			return 42;
		}

		public function get_sku() {
			return 'TEST-SKU-1';
		}

		public function get_name() {
			return 'Test Product';
		}

		public function get_slug() {
			return 'test-product';
		}

		public function get_status() {
			return 'publish';
		}

		public function get_catalog_visibility() {
			return 'visible';
		}

		public function get_description() {
			return 'A description with <b>HTML</b>.';
		}

		public function get_short_description() {
			return 'Short.';
		}

		public function get_date_created() {
			return null;
		}

		public function get_date_modified() {
			return null;
		}

		public function get_weight() {
			return '1.5';
		}

		public function get_length() {
			return '10';
		}

		public function get_width() {
			return '5';
		}

		public function get_height() {
			return '2';
		}

		public function get_tax_class() {
			return '';
		}

		public function get_tax_status() {
			return 'taxable';
		}

		public function get_image_id() {
			return 0;
		}

		public function get_gallery_image_ids() {
			return array();
		}

		public function get_attributes() {
			return array();
		}

		public function get_type() {
			return 'simple';
		}

		public function is_in_stock() {
			return true;
		}

		public function get_stock_quantity() {
			return 10;
		}

		public function get_stock_status() {
			return 'instock';
		}

		public function get_manage_stock() {
			return true;
		}

		public function backorders_allowed() {
			return false;
		}

		public function get_low_stock_amount() {
			return null;
		}

		public function get_price() {
			return '29.99';
		}

		public function get_regular_price() {
			return '39.99';
		}

		public function get_sale_price() {
			return '29.99';
		}

		public function is_on_sale() {
			return true;
		}

		public function get_date_on_sale_from() {
			return null;
		}

		public function get_date_on_sale_to() {
			return null;
		}

		public function get_permalink() {
			return 'https://example.test/?p=42';
		}

		public function get_variation_attributes() {
			return array();
		}
	}
}
