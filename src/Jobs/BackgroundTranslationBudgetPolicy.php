<?php
/**
 * Preflight and runtime budget enforcement for background translation jobs.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Integer request/token counters with fail-closed semantics (plan §13).
 */
final class BackgroundTranslationBudgetPolicy {

	/**
	 * Job repository for atomic usage counters.
	 *
	 * @var BackgroundTranslationJobRepository
	 */
	private BackgroundTranslationJobRepository $jobs;

	/**
	 * Builds the budget policy.
	 *
	 * @param BackgroundTranslationJobRepository|null $jobs Job repository.
	 */
	public function __construct( ?BackgroundTranslationJobRepository $jobs = null ) {
		$this->jobs = $jobs ?? new BackgroundTranslationJobRepository();
	}

	/**
	 * Preflight create-time checks against configured limits.
	 *
	 * @param array<string, mixed> $args        Create arguments.
	 * @param int                  $item_count  Materialized item count.
	 * @return true|WP_Error
	 */
	public function preflight( array $args, int $item_count ) {
		$item_count = max( 0, $item_count );

		$max_requests = (int) ( $args['budget_max_requests'] ?? 0 );
		if ( $max_requests > 0 && $item_count > $max_requests ) {
			return new WP_Error(
				'workload_limit_exceeded',
				'Job item count exceeds budget_max_requests.'
			);
		}

		$max_tokens = (int) ( $args['budget_max_tokens'] ?? 0 );
		if ( $max_tokens > 0 && $item_count > $max_tokens ) {
			return new WP_Error(
				'workload_limit_exceeded',
				'Estimated item workload exceeds budget_max_tokens.'
			);
		}

		return true;
	}

	/**
	 * Whether the worker may claim another item under configured hard limits.
	 *
	 * Fail closed when limits are configured and already reached.
	 *
	 * @param object $job Job row.
	 */
	public function can_claim_next( object $job ): bool {
		$max_requests = (int) ( $job->budget_max_requests ?? 0 );
		$max_tokens   = (int) ( $job->budget_max_tokens ?? 0 );

		if ( 0 === $max_requests && 0 === $max_tokens ) {
			return true;
		}

		$used_requests = (int) ( $job->budget_used_requests ?? 0 );
		$used_tokens   = (int) ( $job->budget_used_tokens ?? 0 );

		if ( $max_requests > 0 && $used_requests >= $max_requests ) {
			return false;
		}

		if ( $max_tokens > 0 && $used_tokens >= $max_tokens ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether usage crossed the configured warning threshold.
	 *
	 * @param object $job Job row.
	 */
	public function is_warning( object $job ): bool {
		$warning_pct = (int) ( $job->budget_warning_pct ?? 80 );
		if ( $warning_pct <= 0 || $warning_pct >= 100 ) {
			return false;
		}

		$max_requests = (int) ( $job->budget_max_requests ?? 0 );
		if ( $max_requests > 0 ) {
			$used      = (int) ( $job->budget_used_requests ?? 0 );
			$threshold = (int) floor( ( $max_requests * $warning_pct ) / 100 );
			if ( $used >= max( 1, $threshold ) ) {
				return true;
			}
		}

		$max_tokens = (int) ( $job->budget_max_tokens ?? 0 );
		if ( $max_tokens > 0 ) {
			$used      = (int) ( $job->budget_used_tokens ?? 0 );
			$threshold = (int) floor( ( $max_tokens * $warning_pct ) / 100 );
			if ( $used >= max( 1, $threshold ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Record provider usage atomically on the job row.
	 *
	 * Unknown or zero request usage counts as one request unit (plan §13).
	 *
	 * @param int $job_id   Job id.
	 * @param int $requests Request units (0 → 1).
	 * @param int $tokens   Token units.
	 * @return object|WP_Error
	 */
	public function record_usage( int $job_id, int $requests, int $tokens ) {
		if ( $requests <= 0 ) {
			$requests = 1;
		}

		if ( $tokens < 0 ) {
			$tokens = 0;
		}

		$result = $this->jobs->increment_budget_usage( $job_id, $requests, $tokens );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $this->is_warning( $result ) ) {
			/**
			 * Fires when job budget usage crosses the warning threshold.
			 *
			 * @since 0.1.0
			 *
			 * @param int    $job_id Job id.
			 * @param object $job    Updated job row.
			 */
			do_action( 'aiml_translation_job_budget_warning', $job_id, $result );
		}

		return $result;
	}
}
