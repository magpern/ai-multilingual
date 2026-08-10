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
 * Verifies truthful bounded usage evidence.
 */
final class AttemptUsageEvidenceTest extends TestCase {

	public function test_known_zero_is_truthful_zero(): void {
		$usage = AttemptUsageEvidence::known_zero( 'tm_direct_reuse' );

		$this->assertTrue( $usage->usage_known );
		$this->assertSame( 0, $usage->provider_requests );
		$this->assertSame( 0, $usage->token_units() );
		$this->assertSame( 'tm_direct_reuse', $usage->tm_outcome_code );
	}

	public function test_provider_success_sums_token_units(): void {
		$usage = AttemptUsageEvidence::provider_success( 1, 12, 7 );

		$this->assertSame( 1, $usage->provider_requests );
		$this->assertSame( 19, $usage->token_units() );
	}

	public function test_unknown_does_not_fabricate_usage(): void {
		$usage = AttemptUsageEvidence::unknown();

		$this->assertFalse( $usage->usage_known );
		$this->assertSame( 0, $usage->provider_requests );
		$this->assertSame( 0, $usage->token_units() );
	}
}
