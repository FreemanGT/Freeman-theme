<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Seo;
use Freeman\Core\Modules\ShopFilters\Url_State;
use PHPUnit\Framework\TestCase;

/**
 * Filtered-URL SEO policy (Phase 6.5a): the pure decision seams — what counts
 * as a filtered view, how a URL is cleaned to its canonical, and the robots
 * directive mutations. Plugin routing + tag emission are live-QA.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Seo
 */
final class ShopFiltersSeoTest extends TestCase {

	/**
	 * @dataProvider filtered_params
	 */
	public function test_is_filtered_state_true_for_filter_selections( array $params ): void {
		$this->assertTrue( Seo::is_filtered_state( Url_State::parse( $params ) ) );
	}

	public static function filtered_params(): array {
		return array(
			'attribute' => array( array( 'filter_pa_color' => 'red,blue' ) ),
			'price band' => array( array( 'filter_price' => '0-50' ) ),
			'min price' => array( array( 'min_price' => '10' ) ),
			'max price' => array( array( 'max_price' => '99' ) ),
			'onsale'    => array( array( 'onsale' => '1' ) ),
			'in stock'  => array( array( 'in_stock' => '1' ) ),
		);
	}

	/**
	 * @dataProvider unfiltered_params
	 */
	public function test_is_filtered_state_false_for_sort_pagination_and_empty( array $params ): void {
		$this->assertFalse( Seo::is_filtered_state( Url_State::parse( $params ) ) );
	}

	public static function unfiltered_params(): array {
		return array(
			'sort only' => array( array( 'orderby' => 'price' ) ),
			'page only' => array( array( 'paged' => '2' ) ),
			'empty'     => array( array() ),
		);
	}

	public function test_clean_url_strips_filters_sort_and_pagination_keeps_rest(): void {
		$dirty = 'https://shop.test/product-category/shoes/page/2/?filter_pa_color=red&filter_price=0-50&orderby=price&utm=spring';

		$this->assertSame(
			'https://shop.test/product-category/shoes/?utm=spring',
			Seo::clean_url( $dirty )
		);
	}

	public function test_clean_url_strips_paged_query_param(): void {
		$this->assertSame(
			'https://shop.test/shop/',
			Seo::clean_url( 'https://shop.test/shop/?paged=3&min_price=10&max_price=80' )
		);
	}

	public function test_clean_url_is_idempotent_on_a_clean_url(): void {
		$clean = 'https://shop.test/product-category/shoes/';
		$this->assertSame( $clean, Seo::clean_url( $clean ) );
	}

	public function test_clean_url_preserves_port(): void {
		$this->assertSame(
			'https://shop.test:8443/shop/',
			Seo::clean_url( 'https://shop.test:8443/shop/?filter_pa_size=m' )
		);
	}

	public function test_noindex_index_follow_sets_directives_and_keeps_others(): void {
		$result = Seo::noindex_index_follow(
			array(
				'index'       => 'index',
				'follow'      => 'follow',
				'max-snippet' => '-1',
			)
		);

		$this->assertSame( 'noindex', $result['index'] );
		$this->assertSame( 'follow', $result['follow'] );
		$this->assertSame( '-1', $result['max-snippet'] );
	}

	public function test_noindex_core_sets_noindex_drops_index_keeps_others(): void {
		$result = Seo::noindex_core(
			array(
				'index'             => true,
				'max-image-preview' => 'large',
			)
		);

		$this->assertArrayNotHasKey( 'index', $result );
		$this->assertTrue( $result['noindex'] );
		$this->assertTrue( $result['follow'] );
		$this->assertSame( 'large', $result['max-image-preview'] );
	}
}
