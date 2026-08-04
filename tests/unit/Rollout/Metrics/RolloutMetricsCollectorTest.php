<?php
/**
 * Rollout metrics collector unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Rollout\Metrics;

use AIMultilingual\Rollout\Metrics\RolloutHotMetricsStore;
use AIMultilingual\Rollout\Metrics\RolloutMetricsCollector;
use AIMultilingual\Rollout\Metrics\RolloutMetricsRepository;
use AIMultilingual\Rollout\RolloutPolicyDecision;
use AIMultilingual\Rollout\RolloutReasonCodes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Rollout\Metrics\RolloutMetricsCollector
 */
final class RolloutMetricsCollectorTest extends TestCase {

	public function test_policy_decision_buffers_without_throwing_when_table_missing(): void {
		$collector = new RolloutMetricsCollector( new RolloutMetricsRepository(), new RolloutHotMetricsStore() );
		$decision  = RolloutPolicyDecision::deny(
			RolloutReasonCodes::POST_NOT_ALLOWLISTED,
			2,
			3
		);

		$collector->on_policy_decision( $decision, false );
		$collector->flush();

		$this->assertTrue( $collector->is_incomplete() );
	}

	public function test_reset_buffer_clears_state(): void {
		$collector = new RolloutMetricsCollector();
		$collector->reset_buffer();
		$this->assertFalse( $collector->is_incomplete() );
	}
}
