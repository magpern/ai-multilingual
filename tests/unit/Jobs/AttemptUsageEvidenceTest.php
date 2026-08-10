<?php
/**
 * Attempt usage evidence unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\AttemptUsageEvidence;
use PHPUnit\Framework\TestCase;

/**
 * Guards against status-inference and 0→1 coercion regressions.
 */
final class AttemptUsageEvidenceTest extends TestCase {

	public function test_known_zero_tm_reuse_is_zero_requests(): void {
		$usage = AttemptUsageEvidence::known_zero( 'tm_direct_reuse' );
		$this->assertSame( 0, $usage->provider_requests );
		$this->assertSame( 0, $usage->input_tokens );
		$this->assertSame( 0, $usage->output_tokens );
		$this->assertTrue( $usage->usage_known );
		$this->assertSame( 'tm_direct_reuse', $usage->tm_outcome_code );
	}

	public function test_provider_attempt_retains_explicit_zero_requests(): void {
		$usage = AttemptUsageEvidence::provider_attempt( 0, 0, 0 );
		$this->assertSame( 0, $usage->provider_requests );
		$this->assertTrue( $usage->usage_known );
	}

	public function test_provider_success_records_requests_and_tokens(): void {
		$usage = AttemptUsageEvidence::provider_success( 1, 12, 34 );
		$this->assertSame( 1, $usage->provider_requests );
		$this->assertSame( 12, $usage->input_tokens );
		$this->assertSame( 34, $usage->output_tokens );
		$this->assertTrue( $usage->usage_known );
	}
}
