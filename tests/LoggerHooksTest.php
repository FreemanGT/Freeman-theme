<?php
declare(strict_types=1);

use Freeman\Core\Core\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerHooksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fr_opts']  = array();
		$GLOBALS['fr_hooks'] = array();
	}

	public function test_entry_filter_mutates_stored_entry(): void {
		add_filter(
			'freeman_core/logger/entry',
			static function ( $entry ) {
				$entry['context'] = 'x';
				return $entry;
			}
		);

		Logger::log( 'hi' );

		$entries = Logger::entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( 'x', $entries[0]['context'] );
		$this->assertSame( 'hi', $entries[0]['message'] );
	}

	public function test_entry_filter_receives_message_and_level(): void {
		$captured = array();
		add_filter(
			'freeman_core/logger/entry',
			static function ( $entry, $message, $level ) use ( &$captured ) {
				$captured = array( $message, $level );
				return $entry;
			},
			10,
			3
		);

		Logger::log( 'hi', 'error' );

		$this->assertSame( array( 'hi', 'error' ), $captured );
	}

	public function test_written_action_fires_with_payload(): void {
		$calls = array();
		add_action(
			'freeman_core/logger/written',
			static function ( $entry, $log ) use ( &$calls ) {
				$calls[] = array( 'entry' => $entry, 'log_count' => count( $log ) );
			},
			10,
			2
		);

		Logger::log( 'first' );
		Logger::log( 'second' );

		$this->assertCount( 2, $calls );
		$this->assertSame( 'first', $calls[0]['entry']['message'] );
		$this->assertSame( 1, $calls[0]['log_count'] );
		$this->assertSame( 'second', $calls[1]['entry']['message'] );
		$this->assertSame( 2, $calls[1]['log_count'] );
	}

	/**
	 * With no listeners registered, the stored option payload must match the
	 * pre-hooks Logger output byte-for-byte. The expected serialized blob below
	 * was captured from the unmodified Logger before this PR's changes.
	 */
	public function test_no_listeners_means_byte_identical_output(): void {
		$expected = 'a:1:{i:0;a:3:{s:4:"time";N;s:5:"level";s:4:"info";s:7:"message";s:4:"test";}}';

		Logger::log( 'test', 'info' );

		$this->assertSame( $expected, serialize( get_option( 'freeman_core_log' ) ) );
	}
}
