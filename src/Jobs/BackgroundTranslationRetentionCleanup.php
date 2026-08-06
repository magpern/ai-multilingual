<?php
/**
 * Bounded retention cleanup for background translation jobs (plan §18).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Deletes terminal, unleased jobs and orphan items — never touches Store/TM/Glossary.
 */
final class BackgroundTranslationRetentionCleanup {

	/**
	 * Retention for completed terminal jobs (seconds).
	 */
	public const COMPLETED_RETENTION_SECONDS = 30 * DAY_IN_SECONDS;

	/**
	 * Retention for failed/cancelled jobs (seconds).
	 */
	public const FAILED_RETENTION_SECONDS = 90 * DAY_IN_SECONDS;

	/**
	 * Job repository.
	 *
	 * @var BackgroundTranslationJobRepository
	 */
	private BackgroundTranslationJobRepository $jobs;

	/**
	 * Item repository.
	 *
	 * @var BackgroundTranslationItemRepository
	 */
	private BackgroundTranslationItemRepository $items;

	/**
	 * Diagnostics counters.
	 *
	 * @var BackgroundTranslationDiagnostics|null
	 */
	private ?BackgroundTranslationDiagnostics $diagnostics;

	/**
	 * Builds the cleanup service.
	 *
	 * @param BackgroundTranslationJobRepository|null  $jobs        Job repository.
	 * @param BackgroundTranslationItemRepository|null $items       Item repository.
	 * @param BackgroundTranslationDiagnostics|null    $diagnostics Diagnostics.
	 */
	public function __construct(
		?BackgroundTranslationJobRepository $jobs = null,
		?BackgroundTranslationItemRepository $items = null,
		?BackgroundTranslationDiagnostics $diagnostics = null
	) {
		$this->jobs        = $jobs ?? new BackgroundTranslationJobRepository();
		$this->items       = $items ?? new BackgroundTranslationItemRepository();
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Runs bounded, idempotent retention cleanup.
	 *
	 * @param int $limit Maximum jobs to delete this run.
	 * @return array{jobs_deleted: int, items_deleted: int, orphans_deleted: int}
	 */
	public function run( int $limit = BackgroundTranslationScheduler::SWEEP_BATCH_LIMIT ): array {
		$limit = max( 1, $limit );
		$now   = time();

		$metrics = array(
			'jobs_deleted'    => 0,
			'items_deleted'   => 0,
			'orphans_deleted' => 0,
		);

		$completed_cutoff = gmdate( 'Y-m-d H:i:s', $now - self::COMPLETED_RETENTION_SECONDS );
		$failed_cutoff    = gmdate( 'Y-m-d H:i:s', $now - self::FAILED_RETENTION_SECONDS );

		$candidates = array_merge(
			$this->jobs->find_retention_candidates(
				array( JobStatuses::COMPLETED, JobStatuses::COMPLETED_WITH_ERRORS ),
				$completed_cutoff,
				$limit
			),
			$this->jobs->find_retention_candidates(
				array( JobStatuses::FAILED, JobStatuses::CANCELLED ),
				$failed_cutoff,
				$limit
			)
		);

		$seen = array();
		foreach ( $candidates as $job ) {
			if ( $metrics['jobs_deleted'] >= $limit ) {
				break;
			}

			$job_id = (int) $job->job_id;
			if ( isset( $seen[ $job_id ] ) ) {
				continue;
			}
			$seen[ $job_id ] = true;

			if ( ! $this->is_deletable( $job ) ) {
				continue;
			}

			$deleted_items = $this->items->delete_by_job( $job_id );
			if ( is_wp_error( $deleted_items ) ) {
				continue;
			}

			$deleted_job = $this->jobs->delete_terminal( $job_id );
			if ( is_wp_error( $deleted_job ) || ! $deleted_job ) {
				continue;
			}

			++$metrics['jobs_deleted'];
			$metrics['items_deleted'] += (int) $deleted_items;
		}

		$orphan_limit = max( 0, $limit - $metrics['jobs_deleted'] );
		if ( $orphan_limit > 0 ) {
			$orphans = $this->items->delete_orphans( $orphan_limit );
			if ( ! is_wp_error( $orphans ) ) {
				$metrics['orphans_deleted'] = (int) $orphans;
			}
		}

		if ( null !== $this->diagnostics ) {
			if ( $metrics['jobs_deleted'] > 0 ) {
				$this->diagnostics->increment(
					BackgroundTranslationDiagnostics::CLEANUP_JOBS_DELETED,
					$metrics['jobs_deleted']
				);
			}
			if ( $metrics['items_deleted'] > 0 ) {
				$this->diagnostics->increment(
					BackgroundTranslationDiagnostics::CLEANUP_ITEMS_DELETED,
					$metrics['items_deleted']
				);
			}
			if ( $metrics['orphans_deleted'] > 0 ) {
				$this->diagnostics->increment(
					BackgroundTranslationDiagnostics::CLEANUP_ORPHANS_DELETED,
					$metrics['orphans_deleted']
				);
			}
		}

		if ( function_exists( 'do_action' ) ) {
			/**
			 * Fires after a bounded retention cleanup run.
			 *
			 * @since 0.1.0
			 *
			 * @param array{jobs_deleted: int, items_deleted: int, orphans_deleted: int} $metrics Cleanup metrics.
			 */
			do_action( 'aiml_jobs_retention_cleanup', $metrics );
		}

		return $metrics;
	}

	/**
	 * Whether a job row is safe to delete (terminal, unleased, lock cleared).
	 *
	 * @param object $job Job row.
	 */
	private function is_deletable( object $job ): bool {
		$status = (string) ( $job->status ?? '' );
		if ( ! JobStatuses::is_terminal( $status ) ) {
			return false;
		}

		if ( '' !== (string) ( $job->lease_owner ?? '' ) ) {
			$expires = (string) ( $job->lease_expires_at ?? '' );
			if ( '' !== $expires && $expires > current_time( 'mysql', true ) ) {
				return false;
			}
		}

		if ( null !== ( $job->active_lock_key ?? null ) && '' !== (string) $job->active_lock_key ) {
			return false;
		}

		return true;
	}
}
