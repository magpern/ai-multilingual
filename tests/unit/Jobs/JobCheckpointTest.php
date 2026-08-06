<?php
/**
 * JobCheckpoint unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\JobCheckpoint;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * J1 — checkpoint allowlist, cap, and decode behavior.
 */
final class JobCheckpointTest extends TestCase {

	public function test_encode_returns_null_for_empty_array(): void {
		$this->assertNull( JobCheckpoint::encode( array() ) );
	}

	public function test_encode_round_trip_allowlisted_keys(): void {
		$data = array(
			'checkpoint_schema_version' => 1,
			'stage'                     => 'batch',
			'batch_index'               => 2,
			'segment_ids'               => array( 'seg-a', 'seg-b' ),
			'last_item_id'              => 42,
		);

		$encoded = JobCheckpoint::encode( $data );
		$this->assertIsString( $encoded );

		$decoded = JobCheckpoint::decode( $encoded );
		$this->assertSame( 1, $decoded['checkpoint_schema_version'] );
		$this->assertSame( 'batch', $decoded['stage'] );
		$this->assertSame( 2, $decoded['batch_index'] );
		$this->assertSame( array( 'seg-a', 'seg-b' ), $decoded['segment_ids'] );
		$this->assertSame( 42, $decoded['last_item_id'] );
	}

	public function test_encode_rejects_non_allowlisted_key(): void {
		$result = JobCheckpoint::encode( array( 'prompt' => 'secret instructions' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'job_checkpoint_invalid_key', $result->get_error_code() );
	}

	public function test_encode_rejects_forbidden_key_fragment(): void {
		$result = JobCheckpoint::encode( array( 'api_key' => 'sk-test' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'job_checkpoint_invalid_key', $result->get_error_code() );
	}

	public function test_encode_rejects_over_soft_cap(): void {
		$segments = array();
		for ( $i = 0; $i < 2000; $i++ ) {
			$segments[] = str_repeat( 'x', 20 ) . $i;
		}

		$result = JobCheckpoint::encode(
			array(
				'segment_ids' => $segments,
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'job_checkpoint_too_large', $result->get_error_code() );
	}

	public function test_decode_returns_empty_array_for_null_or_invalid_json(): void {
		$this->assertSame( array(), JobCheckpoint::decode( null ) );
		$this->assertSame( array(), JobCheckpoint::decode( '' ) );
		$this->assertSame( array(), JobCheckpoint::decode( 'not-json' ) );
	}
}
