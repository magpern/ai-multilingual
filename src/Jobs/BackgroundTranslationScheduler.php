<?php
/**
 * Action Scheduler wake-up for background translation jobs.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Enqueues AS callbacks; does not own orchestration state (plan §15).
 */
class BackgroundTranslationScheduler {

	public const HOOK_RUN_JOB = 'aiml_run_job';

	public const HOOK_SWEEP = 'aiml_jobs_sweep';

	public const GROUP = 'aiml-jobs';

	/**
	 * Maximum jobs processed per sweep run (bounded stub for J7 retention).
	 */
	public const SWEEP_BATCH_LIMIT = 50;

	/**
	 * Whether Action Scheduler enqueue APIs are available.
	 */
	public function is_available(): bool {
		return function_exists( 'as_enqueue_async_action' ) || function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Action Scheduler health snapshot for create-time rejection (plan §15).
	 *
	 * @return array{available: bool, message: string}
	 */
	public function health(): array {
		if ( $this->is_available() ) {
			return array(
				'available' => true,
				'message'   => 'Action Scheduler is available.',
			);
		}

		return array(
			'available' => false,
			'message'   => 'Action Scheduler is not available.',
		);
	}

	/**
	 * Enqueue an async worker wake for one job.
	 *
	 * @param int $job_id Job id.
	 * @return true|WP_Error
	 */
	public function enqueue_job( int $job_id ) {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'action_scheduler_unavailable', 'Action Scheduler is not available.' );
		}

		as_enqueue_async_action(
			self::HOOK_RUN_JOB,
			array( 'job_id' => $job_id ),
			self::GROUP
		);

		return true;
	}

	/**
	 * Schedule a delayed worker wake (retry backoff).
	 *
	 * @param int $job_id         Job id.
	 * @param int $delay_seconds  Delay before wake.
	 * @return true|WP_Error
	 */
	public function enqueue_job_delayed( int $job_id, int $delay_seconds ) {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'action_scheduler_unavailable', 'Action Scheduler is not available.' );
		}

		$delay_seconds = max( 0, $delay_seconds );
		$timestamp     = time() + $delay_seconds;

		as_schedule_single_action(
			$timestamp,
			self::HOOK_RUN_JOB,
			array( 'job_id' => $job_id ),
			self::GROUP
		);

		return true;
	}

	/**
	 * Register Action Scheduler callback hooks.
	 *
	 * @param BackgroundTranslationWorker $worker Worker instance.
	 * @param JobLeaseService|null        $leases Lease service for sweep recovery.
	 */
	public function register_hooks( BackgroundTranslationWorker $worker, ?JobLeaseService $leases = null ): void {
		add_action(
			self::HOOK_RUN_JOB,
			static function ( int $job_id ) use ( $worker ): void {
				$worker->run( $job_id );
			},
			10,
			1
		);

		$lease_service = $leases ?? new JobLeaseService();
		add_action(
			self::HOOK_SWEEP,
			function () use ( $lease_service ): void {
				$this->run_sweep( $lease_service );
			},
			10,
			0
		);
	}

	/**
	 * Bounded sweep: stale lease recovery + retention stub (plan §18; full retention in J7).
	 *
	 * @param JobLeaseService $leases Lease service.
	 */
	public function run_sweep( JobLeaseService $leases ): void {
		$recovered = $leases->recover_stale_leases();
		$count     = count( $recovered );
		$limit     = self::SWEEP_BATCH_LIMIT;

		if ( $count > $limit ) {
			$recovered = array_slice( $recovered, 0, $limit );
		}

		/**
		 * Retention cleanup stub — J7 implements bounded deletion.
		 *
		 * @since 0.1.0
		 *
		 * @param int $recovered_count Leases reclaimed this sweep.
		 */
		do_action( 'aiml_jobs_sweep_retention_stub', count( $recovered ) );
	}

	/**
	 * Schedule a recurring sweep when Action Scheduler is available.
	 *
	 * @return true|WP_Error
	 */
	public function schedule_sweep() {
		if ( ! $this->is_available() ) {
			return new WP_Error( 'action_scheduler_unavailable', 'Action Scheduler is not available.' );
		}

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK_SWEEP, array(), self::GROUP ) ) {
			return true;
		}

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action(
				time() + HOUR_IN_SECONDS,
				HOUR_IN_SECONDS,
				self::HOOK_SWEEP,
				array(),
				self::GROUP
			);
		}

		return true;
	}
}
