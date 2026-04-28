<?php
declare(strict_types=1);

require_once __DIR__ . '/Scrubber.php';

use Freeman\Tests\Snapshots\Scrubber;
use PHPUnit\Framework\TestCase;

final class ScrubberTest extends TestCase {

	public function test_timestamps_replaces_iso_and_mysql_formats(): void {
		$in = 'a 2026-04-29T12:34:56+00:00 b 2026-04-29T12:34:56Z c 2026-04-29 12:34:56 d';
		$expected = 'a <scrubbed:timestamp> b <scrubbed:timestamp> c <scrubbed:timestamp> d';
		$this->assertSame( $expected, Scrubber::timestamps( $in ) );
	}

	public function test_nonces_replaces_input_value_and_query_param(): void {
		$in = '<input type="hidden" name="_wpnonce" value="abc1234567"/>'
			. ' link?_wpnonce=deadbeef99 keep=42';
		$out = Scrubber::nonces( $in );
		$this->assertStringContainsString( 'value="<scrubbed:nonce>"', $out );
		$this->assertStringContainsString( '_wpnonce=<scrubbed:nonce>', $out );
		$this->assertStringContainsString( 'keep=42', $out );
	}

	public function test_versions_replaces_semver_in_version_attribute(): void {
		$in = '<products version="1.10.16" generated="...">';
		$this->assertSame(
			'<products version="<scrubbed:version>" generated="...">',
			Scrubber::versions( $in )
		);
	}

	public function test_site_url_replaces_exact_match(): void {
		$in  = 'see https://example.test/wp-admin/ for details';
		$out = Scrubber::site_url( $in, 'https://example.test' );
		$this->assertSame(
			'see <scrubbed:site_url>/wp-admin/ for details',
			$out
		);
	}

	public function test_json_keys_replaces_only_named_keys(): void {
		$in       = array(
			'version'     => 1,
			'exported_at' => '2026-04-29T00:00:00Z',
			'site_url'    => 'https://example.test',
			'options'     => array( 'foo' => 'bar' ),
		);
		$expected = array(
			'version'     => 1,
			'exported_at' => '<scrubbed>',
			'site_url'    => '<scrubbed>',
			'options'     => array( 'foo' => 'bar' ),
		);
		$this->assertSame(
			$expected,
			Scrubber::json_keys( $in, array( 'exported_at', 'site_url' ), '<scrubbed>' )
		);
	}
}
