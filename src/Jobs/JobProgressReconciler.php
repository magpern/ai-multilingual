<?php
/**
 * Reconcile job progress counters from item rows.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Derives job counter fields from canonical item status (plan §16).
 */
final class JobProgressReconciler {

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
	 * Builds the reconciler.
	 *
	 * @param BackgroundTranslationJobRepository|null  $jobs  Job repository.
	 * @param BackgroundTranslationItemRepository|null $items Item repository.
	 */
	public function __construct(
		?BackgroundTranslationJobRepository $jobs = null,
		?BackgroundTranslationItemRepository $items = null
	) {
		$this->jobs  = $jobs ?? new BackgroundTranslationJobRepository();
		$this->items = $items ?? new BackgroundTranslationItemRepository();
	}

	/**
	 * Recount item statuses and persist counter fields on the job row.
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error
	 */
	public function reconcile( int $job_id ) {
		$job = $this->jobs->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		$counts = $this->items->count_by_status( $job_id );

		$total     = 0;
		$queued    = 0;
		$running   = 0;
		$completed = 0;
		$failed    = 0;
		$skipped   = 0;
		$stale     = 0;
		$cancelled = 0;

		foreach ( $counts as $status => $count ) {
			$count  = (int) $count;
			$total += $count;

			switch ( $status ) {
				case ItemStatuses::QUEUED:
				case ItemStatuses::RETRY_WAIT:
					$queued += $count;
					break;
				case ItemStatuses::RUNNING:
					$running += $count;
					break;
				case ItemStatuses::COMPLETED:
					$completed += $count;
					break;
				case ItemStatuses::FAILED:
					$failed += $count;
					break;
				case ItemStatuses::SKIPPED_CONFLICT:
					$skipped += $count;
					break;
				case ItemStatuses::STALE_SOURCE:
					$stale += $count;
					break;
				case ItemStatuses::CANCELLED:
					$cancelled += $count;
					break;
			}
		}

		$updated = $this->jobs->update(
			$job_id,
			array(
				'total_items'     => $total,
				'queued_items'    => $queued,
				'running_items'   => $running,
				'completed_items' => $completed,
				'failed_items'    => $failed,
				'skipped_items'   => $skipped,
				'stale_items'     => $stale,
				'cancelled_items' => $cancelled,
			)
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return $updated ?? $this->jobs->find( $job_id );
	}
}
