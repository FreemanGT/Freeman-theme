<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Url_State;
use PHPUnit\Framework\TestCase;

/**
 * Pure URL filter-state parse / serialize.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Url_State
 */
final class ShopFiltersUrlStateTest extends TestCase {

	public function test_parses_csv_filters_and_scalars(): void {
		$state = Url_State::parse(
			array(
				'filter_pa_color' => 'red,blue',
				'filter_pa_size'  => 'm',
				'min_price'       => '10',
				'max_price'       => '50.5',
				'onsale'          => '1',
				'orderby'         => 'price',
				'paged'           => '3',
				'unrelated'       => 'x',
			)
		);

		$this->assertSame( array( 'red', 'blue' ), $state['filters']['pa_color'] );
		$this->assertSame( array( 'm' ), $state['filters']['pa_size'] );
		$this->assertSame( 10.0, $state['min_price'] );
		$this->assertSame( 50.5, $state['max_price'] );
		$this->assertTrue( $state['onsale'] );
		$this->assertFalse( $state['in_stock'] );
		$this->assertSame( 'price', $state['orderby'] );
		$this->assertSame( 3, $state['paged'] );
		$this->assertArrayNotHasKey( 'unrelated', $state['filters'] );
	}

	public function test_rejects_unknown_orderby_and_dedupes_sanitizes_slugs(): void {
		$state = Url_State::parse(
			array(
				'filter_pa_color' => 'red,red,RED,  ',
				'orderby'         => 'haxx',
			)
		);

		$this->assertSame( array( 'red' ), $state['filters']['pa_color'] ); // lowercased + deduped + blanks dropped.
		$this->assertSame( '', $state['orderby'] );                          // not in whitelist.
		$this->assertSame( 1, $state['paged'] );                             // default.
	}

	public function test_serialize_round_trips_and_omits_defaults(): void {
		$params = Url_State::serialize(
			array(
				'filters'   => array( 'pa_color' => array( 'red', 'blue' ) ),
				'min_price' => 10.0,
				'max_price' => null,
				'onsale'    => false,
				'in_stock'  => true,
				'orderby'   => 'price',
				'paged'     => 1,
			)
		);

		$this->assertSame( 'red,blue', $params['filter_pa_color'] );
		$this->assertSame( '10', $params['min_price'] );
		$this->assertArrayNotHasKey( 'max_price', $params );
		$this->assertArrayNotHasKey( 'onsale', $params );
		$this->assertSame( '1', $params['in_stock'] );
		$this->assertArrayNotHasKey( 'paged', $params ); // page 1 omitted.

		$reparsed = Url_State::parse( $params );
		$this->assertSame( array( 'red', 'blue' ), $reparsed['filters']['pa_color'] );
	}

	public function test_preserves_hyphenated_attribute_taxonomy(): void {
		// Regression: pa_shoe-size / pa_clothing-size are valid taxonomies; the
		// hyphen must survive parse + serialize or the tax_query matches nothing.
		$state = Url_State::parse( array( 'filter_pa_shoe-size' => '1-3-37,eu-40' ) );
		$this->assertArrayHasKey( 'pa_shoe-size', $state['filters'] );
		$this->assertSame( array( '1-3-37', 'eu-40' ), $state['filters']['pa_shoe-size'] );

		$params = Url_State::serialize( array( 'filters' => array( 'pa_shoe-size' => array( '1-3-37' ) ) ) );
		$this->assertSame( '1-3-37', $params['filter_pa_shoe-size'] );
	}
}
