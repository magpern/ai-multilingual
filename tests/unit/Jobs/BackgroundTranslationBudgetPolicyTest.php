<?php
/**
 * BackgroundTranslationBudgetPolicy unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\BackgroundTranslationBudgetPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Budget preflight and runtime gate unit coverage (J4).
 */
final class BackgroundTranslationBudgetPolicyTest extends TestCase {

	public function test_preflight_does_not_infer_provider_usage_from_item_count(): void {
		$policy = new BackgroundTranslationBudgetPolicy();

		$result = $policy->preflight(
			array(
				'budget_max_requests' => 2,
			),
			3
		);

		$this->assertTrue( $result );
	}

	public function test_can_claim_next_false_when_request_hard_limit_hit(): void {
		$policy = new BackgroundTranslationBudgetPolicy();
		$job    = (object) array(
			'budget_max_requests'  => 5,
			'budget_max_tokens'    => 0,
			'budget_used_requests' => 5,
			'budget_used_tokens'   => 0,
		);

		$this->assertFalse( $policy->can_claim_next( $job ) );
	}

	public function test_can_claim_next_true_when_no_limits_configured(): void {
		$policy = new BackgroundTranslationBudgetPolicy();
		$job    = (object) array(
			'budget_max_requests'  => 0,
			'budget_max_tokens'    => 0,
			'budget_used_requests' => 999,
			'budget_used_tokens'   => 999,
		);

		$this->assertTrue( $policy->can_claim_next( $job ) );
	}

	public function test_is_warning_at_configured_threshold(): void {
		$policy = new BackgroundTranslationBudgetPolicy();
		$job    = (object) array(
			'budget_max_requests'  => 10,
			'budget_max_tokens'    => 0,
			'budget_used_requests' => 8,
			'budget_warning_pct'   => 80,
		);

		$this->assertTrue( $policy->is_warning( $job ) );
	}
}
