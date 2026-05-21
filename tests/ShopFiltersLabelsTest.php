<?php
declare(strict_types=1);

use Freeman\Core\Modules\ShopFilters\Labels;
use PHPUnit\Framework\TestCase;

/**
 * Storefront label resolver: the defaults map, option override + blank
 * fallback, and the singular/plural count line.
 *
 * @covers \Freeman\Core\Modules\ShopFilters\Labels
 */
final class ShopFiltersLabelsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['fr_opts'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['fr_opts'] = array();
	}

	public function test_defaults_define_label_and_default_for_every_key(): void {
		$defaults = Labels::defaults();

		$this->assertNotEmpty( $defaults );
		foreach ( $defaults as $key => $def ) {
			$this->assertIsString( $key );
			$this->assertArrayHasKey( 'label', $def );
			$this->assertArrayHasKey( 'default', $def );
			$this->assertNotSame( '', (string) $def['label'] );
			$this->assertNotSame( '', (string) $def['default'] );
		}
	}

	public function test_get_returns_english_default_when_option_unset(): void {
		$this->assertSame( 'Clear all', Labels::get( 'clear_all' ) );
		$this->assertSame( 'Categories', Labels::get( 'categories' ) );
	}

	public function test_get_returns_override_when_set(): void {
		$GLOBALS['fr_opts']['freeman_core_shop_filters_label_clear_all'] = 'נקה הכל';

		$this->assertSame( 'נקה הכל', Labels::get( 'clear_all' ) );
	}

	public function test_get_falls_back_when_override_is_blank(): void {
		$GLOBALS['fr_opts']['freeman_core_shop_filters_label_apply'] = '   ';

		$this->assertSame( 'Apply filters', Labels::get( 'apply' ) );
	}

	public function test_get_returns_empty_for_unknown_key(): void {
		$this->assertSame( '', Labels::get( 'no_such_label' ) );
	}

	public function test_count_text_picks_singular_then_plural(): void {
		$this->assertSame( '1 product', Labels::count_text( 1 ) );
		$this->assertSame( '4 products', Labels::count_text( 4 ) );
		$this->assertSame( '0 products', Labels::count_text( 0 ) );
	}

	public function test_count_text_honours_overrides(): void {
		$GLOBALS['fr_opts']['freeman_core_shop_filters_label_count_plural'] = '%d מוצרים';

		$this->assertSame( '5 מוצרים', Labels::count_text( 5 ) );
	}
}
