<?php
/**
 * Site-wide running-job concurrency admission (TI.6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Sole authoritative site-cap policy for Jobs.
 *
 * Counts only jobs with status = running. Lease/item claim remain separate
 * duplicate-execution protections.
 */
final class BackgroundTranslationConcurrencyPolicy {

	public const ERROR_CODE = 'concurrency_limit_exceeded';

	/**
	 * Job repository.
	 *
	 * @var BackgroundTranslationJobRepository
	 */
	private BackgroundTranslationJobRepository $jobs;

	/**
	 * Build the admission policy.
	 *
	 * @param BackgroundTranslationJobRepository|null $jobs Job repository.
	 */
	public function __construct( ?BackgroundTranslationJobRepository $jobs = null ) {
		$this->jobs = $jobs ?? new BackgroundTranslationJobRepository();
	}

	/**
	 * Whether a job may be admitted into site-active running work.
	 *
	 * @param int|null $excluding_job_id Job already running that is continuing (not a new slot).
	 * @return true|WP_Error
	 */
	public function admit_running( ?int $excluding_job_id = null ) {
		$count = $this->jobs->count_by_status( JobStatuses::RUNNING );

		if ( null !== $excluding_job_id && $excluding_job_id > 0 ) {
			$current = $this->jobs->find( $excluding_job_id );
			if (
				null !== $current
				&& JobStatuses::RUNNING === (string) ( $current->status ?? '' )
			) {
				// Continuation of an already-running job does not consume a new slot.
				return true;
			}
		}

		if ( $count >= JobBounds::MAX_CONCURRENT_RUNNING ) {
			return new WP_Error(
				self::ERROR_CODE,
				sprintf(
					'Maximum concurrent running jobs (%d) reached.',
					JobBounds::MAX_CONCURRENT_RUNNING
				),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * Atomically try to transition a job into running under the site cap.
	 *
	 * Used when waking from queued/retry_wait into running. Rejects without
	 * partial status change when the cap is saturated.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $from_status Expected current status (queued|retry_wait|running).
	 * @return object|WP_Error|null Updated job row, null if status race lost, or error.
	 */
	public function admit_and_mark_running( int $job_id, string $from_status ) {
		if ( JobStatuses::RUNNING === $from_status ) {
			$admit = $this->admit_running( $job_id );
			return is_wp_error( $admit ) ? $admit : $this->jobs->find( $job_id );
		}

		if ( ! in_array( $from_status, array( JobStatuses::QUEUED, JobStatuses::RETRY_WAIT ), true ) ) {
			return new WP_Error(
				'illegal_transition',
				sprintf( 'Cannot admit job from status %s into running.', $from_status )
			);
		}

		return $this->jobs->try_transition_to_running_under_cap(
			$job_id,
			$from_status,
			JobBounds::MAX_CONCURRENT_RUNNING
		);
	}
}
