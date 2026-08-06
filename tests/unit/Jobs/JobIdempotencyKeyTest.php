<?php
/**
 * JobIdempotencyKey unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\JobIdempotencyKey;
use PHPUnit\Framework\TestCase;

/**
 * J2 — idempotency key builder.
 */
final class JobIdempotencyKeyTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function base_args(): array {
		return array(
			'job_type'       => 'translate_selected',
			'source_type'    => 'post',
			'source_id'      => 10,
			'language_id'    => 2,
			'segment_keys'   => array( 'b', 'a' ),
			'provider_id'    => 'openai',
			'prompt_profile' => 'default',
			'prompt_version' => '1',
			'created_by'     => 1,
		);
	}

	public function test_stable_hash_and_segment_sorting(): void {
		$a = JobIdempotencyKey::build( $this->base_args() );
		$b = JobIdempotencyKey::build(
			array_merge(
				$this->base_args(),
				array( 'segment_keys' => array( 'a', 'b' ) )
			)
		);

		$this->assertSame( $a, $b );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $a );
	}

	public function test_client_token_changes_hash(): void {
		$without = JobIdempotencyKey::build( $this->base_args() );
		$with    = JobIdempotencyKey::build(
			array_merge( $this->base_args(), array( 'client_token' => 'abc' ) )
		);

		$this->assertNotSame( $without, $with );
	}

	public function test_args_match(): void {
		$this->assertTrue( JobIdempotencyKey::args_match( $this->base_args(), $this->base_args() ) );
		$this->assertFalse(
			JobIdempotencyKey::args_match(
				$this->base_args(),
				array_merge( $this->base_args(), array( 'provider_id' => 'other' ) )
			)
		);
	}
}
