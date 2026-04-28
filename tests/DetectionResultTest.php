<?php
declare(strict_types=1);

use Freeman\Core\Core\Detection_Result;
use PHPUnit\Framework\TestCase;

final class DetectionResultTest extends TestCase {

	public function test_constructor_coerces_types(): void {
		$r = new Detection_Result( 1, '', 'foo/bar.php' );
		$this->assertTrue( $r->installed );   // 1 → true
		$this->assertFalse( $r->active );     // '' → false
		$this->assertSame( 'foo/bar.php', $r->file );
	}

	public function test_from_returns_self_when_given_instance(): void {
		$in  = new Detection_Result( true, false, 'x.php' );
		$out = Detection_Result::from( $in );
		$this->assertSame( $in, $out );
	}

	public function test_from_coerces_legacy_array_shape(): void {
		$out = Detection_Result::from( array( 'installed' => true, 'active' => false, 'file' => 'x.php' ) );
		$this->assertInstanceOf( Detection_Result::class, $out );
		$this->assertTrue( $out->installed );
		$this->assertFalse( $out->active );
		$this->assertSame( 'x.php', $out->file );
	}

	public function test_from_rejects_bad_shape(): void {
		$this->assertNull( Detection_Result::from( null ) );
		$this->assertNull( Detection_Result::from( 'not-an-array' ) );
		$this->assertNull( Detection_Result::from( array( 'installed' => true ) ) );
	}

	public function test_array_access_read_compat(): void {
		$r = new Detection_Result( true, false, 'x.php' );
		$this->assertTrue( $r['installed'] );
		$this->assertFalse( $r['active'] );
		$this->assertSame( 'x.php', $r['file'] );
		$this->assertTrue( isset( $r['installed'] ) );
		$this->assertFalse( isset( $r['nope'] ) );
	}

	public function test_immutable_via_array_access(): void {
		$r = new Detection_Result( true, false, 'x.php' );
		$r['installed'] = false; // ignored
		$this->assertTrue( $r->installed );
	}

	public function test_to_array_and_jsonserialize_match_shape(): void {
		$r = new Detection_Result( true, false, 'x.php' );
		$this->assertSame(
			array( 'installed' => true, 'active' => false, 'file' => 'x.php' ),
			$r->to_array()
		);
		$this->assertSame( $r->to_array(), $r->jsonSerialize() );
	}
}
