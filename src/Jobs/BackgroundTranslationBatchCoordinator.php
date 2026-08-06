<?php
/**
 * Lightweight bulk job grouping under batch_id.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Creates independent child jobs sharing batch_id — no parent aggregate (plan §9).
 */
final class BackgroundTranslationBatchCoordinator {

	/**
	 * Job domain service.
	 *
	 * @var BackgroundTranslationJobService
	 */
	private BackgroundTranslationJobService $jobs;

	/**
	 * Job repository (batch queries).
	 *
	 * @var BackgroundTranslationJobRepository
	 */
	private BackgroundTranslationJobRepository $job_repo;

	/**
	 * Action Scheduler health gate (J4).
	 *
	 * @var BackgroundTranslationScheduler|null
	 */
	private ?BackgroundTranslationScheduler $scheduler;

	/**
	 * Builds the batch coordinator.
	 *
	 * @param BackgroundTranslationJobService|null    $jobs      Job service.
	 * @param BackgroundTranslationJobRepository|null $job_repo  Job repository.
	 * @param BackgroundTranslationScheduler|null     $scheduler AS scheduler.
	 */
	public function __construct(
		?BackgroundTranslationJobService $jobs = null,
		?BackgroundTranslationJobRepository $job_repo = null,
		?BackgroundTranslationScheduler $scheduler = null
	) {
		$this->job_repo  = $job_repo ?? new BackgroundTranslationJobRepository();
		$this->jobs      = $jobs ?? new BackgroundTranslationJobService( $this->job_repo );
		$this->scheduler = $scheduler;
	}

	/**
	 * Create up to MAX_POSTS_PER_BULK independent jobs sharing a batch_id.
	 *
	 * @param list<array<string, mixed>> $posts       Per-post create arg sets (source_id + segment_keys, etc.).
	 * @param int                        $language_id Shared language id applied when missing per post.
	 * @param array<string, mixed>       $shared_args Shared create args (provider, prompt, created_by, ...).
	 * @return array{batch_id: string, jobs: list<object>}|WP_Error
	 */
	public function create_bulk( array $posts, int $language_id, array $shared_args = array() ) {
		if ( count( $posts ) > JobBounds::MAX_POSTS_PER_BULK ) {
			return new WP_Error( 'workload_limit_exceeded', 'Bulk request exceeds max posts per bulk.' );
		}

		if ( array() === $posts ) {
			return new WP_Error( 'empty_workload', 'Bulk request requires at least one post.' );
		}

		if ( null !== $this->scheduler ) {
			$health = $this->scheduler->health();
			if ( empty( $health['available'] ) ) {
				return new WP_Error(
					'action_scheduler_unavailable',
					(string) ( $health['message'] ?? 'Action Scheduler is not available.' )
				);
			}
		}

		$batch_id = $this->generate_batch_id();
		$created  = array();

		foreach ( $posts as $post_args ) {
			$args = array_merge(
				$shared_args,
				(array) $post_args,
				array(
					'job_type'    => JobTypes::BULK_TRANSLATE,
					'language_id' => (int) ( $post_args['language_id'] ?? $language_id ),
					'batch_id'    => $batch_id,
				)
			);

			$result = $this->jobs->create_job( $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$created[] = $result;
		}

		return array(
			'batch_id' => $batch_id,
			'jobs'     => $created,
		);
	}

	/**
	 * Request cancel on all non-terminal jobs in a batch.
	 *
	 * @param string $batch_id Batch identifier.
	 * @return list<object|WP_Error>
	 */
	public function cancel_batch( string $batch_id ): array {
		$results = array();

		foreach ( $this->job_repo->list_by_batch_id( $batch_id ) as $job ) {
			if ( JobStatuses::is_terminal( (string) $job->status ) ) {
				$results[] = $job;
				continue;
			}

			$cancelled = $this->jobs->request_cancel( (int) $job->job_id );
			if ( is_wp_error( $cancelled ) ) {
				$results[] = $cancelled;
				continue;
			}

			$observed  = $this->jobs->observe_requested_action_at_boundary( (int) $job->job_id );
			$results[] = is_wp_error( $observed ) ? $observed : ( $observed ?? $cancelled );
		}

		return $results;
	}

	/**
	 * Derive aggregate progress from child jobs (no persisted parent).
	 *
	 * @param string $batch_id Batch identifier.
	 * @return array<string, mixed>
	 */
	public function batch_progress( string $batch_id ): array {
		$children = $this->job_repo->list_by_batch_id( $batch_id );

		$summary = array(
			'batch_id'        => $batch_id,
			'job_count'       => count( $children ),
			'total_items'     => 0,
			'queued_items'    => 0,
			'running_items'   => 0,
			'completed_items' => 0,
			'failed_items'    => 0,
			'skipped_items'   => 0,
			'stale_items'     => 0,
			'cancelled_items' => 0,
			'jobs_by_status'  => array(),
		);

		foreach ( $children as $job ) {
			$status = (string) $job->status;
			if ( ! isset( $summary['jobs_by_status'][ $status ] ) ) {
				$summary['jobs_by_status'][ $status ] = 0;
			}
			++$summary['jobs_by_status'][ $status ];

			foreach ( array( 'total_items', 'queued_items', 'running_items', 'completed_items', 'failed_items', 'skipped_items', 'stale_items', 'cancelled_items' ) as $field ) {
				$summary[ $field ] += (int) $job->{$field};
			}
		}

		return $summary;
	}

	/**
	 * Generate a UUID v4 batch identifier.
	 */
	private function generate_batch_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
	}
}
