<?php
/**
 * Action Scheduler wake-up for background translation jobs (J3 thin layer).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Enqueues AS callbacks; does not own orchestration state (plan §15).
 */
final class BackgroundTranslationScheduler {

	public const HOOK_RUN_JOB = 'aiml_run_job';
	public const HOOK_SWEEP   = 'aiml_jobs_sweep';

	public const GROUP = 'aiml-jobs';

	/**
	 * Whether Action Scheduler enqueue APIs are available.
	 */
	public function is_available(): bool {
		return function_exists( 'as_enqueue_async_action' ) || function_exists( 'as_schedule_single_action' );
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
	 * Register Action Scheduler callback hooks.
	 *
	 * @param BackgroundTranslationWorker $worker Worker instance.
	 */
	public function register_hooks( BackgroundTranslationWorker $worker ): void {
		add_action(
			self::HOOK_RUN_JOB,
			static function ( int $job_id ) use ( $worker ): void {
				$worker->run( $job_id );
			},
			10,
			1
		);
	}
}
