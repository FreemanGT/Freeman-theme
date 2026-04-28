<?php
declare(strict_types=1);

use Freeman\Core\Core\Security;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_transients'] = array();
	}

	public function test_rate_limit_allows_within_budget(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertTrue(
				Security::rate_limit( 'unit_test', 3, 60 ),
				"request #$i should be allowed within the budget"
			);
		}
	}

	public function test_rate_limit_rejects_over_budget(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		for ( $i = 0; $i < 3; $i++ ) {
			Security::rate_limit( 'unit_test', 3, 60 );
		}
		$this->assertFalse(
			Security::rate_limit( 'unit_test', 3, 60 ),
			'fourth request should be rejected'
		);
	}

	public function test_rate_limit_bucket_isolation(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		for ( $i = 0; $i < 3; $i++ ) {
			Security::rate_limit( 'bucket_a', 3, 60 );
		}
		// Different bucket must have its own counter.
		$this->assertTrue( Security::rate_limit( 'bucket_b', 3, 60 ) );
	}

	public function test_sanitize_recursive_handles_nested(): void {
		$in  = array( 'a' => 'x', 'b' => array( 'c' => 'y' ), 'd' => 42 );
		$out = Security::sanitize_recursive( $in );
		$this->assertSame( array( 'a' => 'x', 'b' => array( 'c' => 'y' ), 'd' => '42' ), $out );
	}

	public function test_sanitize_recursive_drops_non_scalars_leaves(): void {
		$in  = array( 'obj' => new \stdClass() );
		$out = Security::sanitize_recursive( $in );
		// Objects collapse to '' per Security::sanitize_recursive contract.
		$this->assertSame( array( 'obj' => '' ), $out );
	}
}
