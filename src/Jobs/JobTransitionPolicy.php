<?php
/**
 * Legal background translation job status transitions.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Job aggregate transition matrix (plan §6.5).
 */
final class JobTransitionPolicy {

	/**
	 * Whether a direct status transition is legal.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 */
	public static function can_transition( string $from, string $to ): bool {
		if ( JobStatuses::is_terminal( $from ) ) {
			return false;
		}

		if ( JobStatuses::QUEUED === $from && JobStatuses::RUNNING === $to ) {
			return true;
		}

		if ( JobStatuses::RUNNING === $from && JobStatuses::RETRY_WAIT === $to ) {
			return true;
		}

		if ( JobStatuses::RETRY_WAIT === $from && JobStatuses::RUNNING === $to ) {
			return true;
		}

		if ( JobStatuses::PAUSED === $from && JobStatuses::QUEUED === $to ) {
			return true;
		}

		if ( JobStatuses::FAILED === $from && JobStatuses::QUEUED === $to ) {
			return true;
		}

		if ( JobStatuses::PAUSED === $to ) {
			return in_array( $from, array( JobStatuses::RUNNING, JobStatuses::QUEUED, JobStatuses::RETRY_WAIT ), true );
		}

		if ( JobStatuses::CANCELLED === $to ) {
			return in_array(
				$from,
				array(
					JobStatuses::RUNNING,
					JobStatuses::QUEUED,
					JobStatuses::RETRY_WAIT,
					JobStatuses::PAUSED,
				),
				true
			);
		}

		if ( JobStatuses::RUNNING === $from ) {
			return in_array(
				$to,
				array(
					JobStatuses::COMPLETED,
					JobStatuses::COMPLETED_WITH_ERRORS,
					JobStatuses::FAILED,
				),
				true
			);
		}

		if ( in_array( $to, array( JobStatuses::COMPLETED, JobStatuses::COMPLETED_WITH_ERRORS, JobStatuses::FAILED ), true ) ) {
			return in_array(
				$from,
				array(
					JobStatuses::QUEUED,
					JobStatuses::RUNNING,
					JobStatuses::RETRY_WAIT,
					JobStatuses::PAUSED,
				),
				true
			);
		}

		return false;
	}

	/**
	 * Validate a transition or return WP_Error.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @return true|WP_Error
	 */
	public static function validate_transition( string $from, string $to ) {
		if ( self::can_transition( $from, $to ) ) {
			return true;
		}

		return new WP_Error(
			'illegal_transition',
			sprintf( 'Illegal job transition from %s to %s.', $from, $to )
		);
	}

	/**
	 * Validate resume for a job row.
	 *
	 * @param object $job Job row.
	 * @return true|WP_Error
	 */
	public static function validate_resume( object $job ) {
		$status = (string) $job->status;

		if ( JobStatuses::CANCELLED === $status ) {
			return new WP_Error( 'job_not_resumable', 'Cancelled jobs cannot be resumed.' );
		}

		if ( JobStatuses::PAUSED !== $status ) {
			return new WP_Error( 'illegal_transition', 'Only paused jobs can be resumed.' );
		}

		return true;
	}

	/**
	 * Target job status when pause is observed at a safe boundary.
	 */
	public static function pause_target(): string {
		return JobStatuses::PAUSED;
	}

	/**
	 * Target job status when cancel is observed at a safe boundary.
	 */
	public static function cancel_target(): string {
		return JobStatuses::CANCELLED;
	}

	/**
	 * Target job status after resume.
	 */
	public static function resume_target(): string {
		return JobStatuses::QUEUED;
	}
}
