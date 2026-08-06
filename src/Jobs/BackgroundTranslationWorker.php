<?php
/**
 * Background translation job worker — lease, item loop, result recording.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;
use WP_Post;

/**
 * Orchestrates item processing via ItemProcessor only (plan §19.1).
 */
final class BackgroundTranslationWorker {

	/**
	 * Maximum items processed per Action Scheduler wake.
	 */
	public const MAX_ITEMS_PER_WAKE = 10;

	/**
	 * Maximum item attempts before terminal failure (plan §14; full policy in J4).
	 */
	private const MAX_ITEM_ATTEMPTS = 5;

	/**
	 * Job domain service.
	 *
	 * @var BackgroundTranslationJobService
	 */
	private BackgroundTranslationJobService $jobs;

	/**
	 * Job repository.
	 *
	 * @var BackgroundTranslationJobRepository
	 */
	private BackgroundTranslationJobRepository $job_repo;

	/**
	 * Item repository.
	 *
	 * @var BackgroundTranslationItemRepository
	 */
	private BackgroundTranslationItemRepository $items;

	/**
	 * Lease service.
	 *
	 * @var JobLeaseService
	 */
	private JobLeaseService $leases;

	/**
	 * Progress reconciler.
	 *
	 * @var JobProgressReconciler
	 */
	private JobProgressReconciler $reconciler;

	/**
	 * Per-item domain boundary.
	 *
	 * @var BackgroundTranslationItemProcessor
	 */
	private BackgroundTranslationItemProcessor $processor;

	/**
	 * Builds the worker.
	 *
	 * @param BackgroundTranslationItemProcessor       $processor  Item processor.
	 * @param BackgroundTranslationJobService|null     $jobs       Job service.
	 * @param BackgroundTranslationJobRepository|null  $job_repo   Job repository.
	 * @param BackgroundTranslationItemRepository|null $items      Item repository.
	 * @param JobLeaseService|null                     $leases     Lease service.
	 * @param JobProgressReconciler|null               $reconciler Reconciler.
	 */
	public function __construct(
		BackgroundTranslationItemProcessor $processor,
		?BackgroundTranslationJobService $jobs = null,
		?BackgroundTranslationJobRepository $job_repo = null,
		?BackgroundTranslationItemRepository $items = null,
		?JobLeaseService $leases = null,
		?JobProgressReconciler $reconciler = null
	) {
		$this->processor  = $processor;
		$this->job_repo   = $job_repo ?? new BackgroundTranslationJobRepository();
		$this->items      = $items ?? new BackgroundTranslationItemRepository();
		$this->leases     = $leases ?? new JobLeaseService( $this->job_repo, $this->items );
		$this->reconciler = $reconciler ?? new JobProgressReconciler( $this->job_repo, $this->items );
		$this->jobs       = $jobs ?? new BackgroundTranslationJobService(
			$this->job_repo,
			$this->items,
			$this->leases,
			$this->reconciler
		);
	}

	/**
	 * Claim lease and process up to MAX_ITEMS_PER_WAKE items.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $owner_token Worker lease token; generated when empty.
	 * @return object|WP_Error
	 */
	public function run( int $job_id, string $owner_token = '' ) {
		$job = $this->job_repo->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		if ( JobStatuses::is_terminal( (string) $job->status ) ) {
			return $job;
		}

		if ( '' === $owner_token ) {
			$owner_token = $this->generate_owner_token();
		}

		$lease_check = $this->resolve_lease_claim( $job_id, $owner_token );
		if ( is_wp_error( $lease_check ) ) {
			return $lease_check;
		}
		if ( null === $lease_check ) {
			return new WP_Error( 'lease_held', 'Job lease is held by another worker.' );
		}

		$job = $lease_check;

		$post = get_post( (int) $job->source_id );
		if ( ! $post instanceof WP_Post ) {
			$this->leases->release( $job_id, $owner_token );

			return new WP_Error( 'source_not_found', 'Job source post no longer exists.' );
		}

		try {
			return $this->process_items( $job_id, $owner_token, $job, $post );
		} finally {
			$this->leases->release( $job_id, $owner_token );
		}
	}

	/**
	 * Item processing loop for one wake.
	 *
	 * @param int     $job_id      Job id.
	 * @param string  $owner_token Lease owner.
	 * @param object  $job         Job row.
	 * @param WP_Post $post        Source post.
	 * @return object|WP_Error
	 */
	private function process_items( int $job_id, string $owner_token, object $job, WP_Post $post ) {
		$processed = 0;

		while ( $processed < self::MAX_ITEMS_PER_WAKE ) {
			$boundary = $this->jobs->observe_requested_action_at_boundary( $job_id );
			if ( $boundary instanceof WP_Error ) {
				return $boundary;
			}
			if ( null !== $boundary ) {
				return $boundary;
			}

			$item = $this->items->claim_next( $job_id );
			if ( null === $item ) {
				break;
			}

			$result = $this->processor->process( $job, $item, $post );
			$this->record_item_result( (int) $item->item_id, $result );

			$this->reconciler->reconcile( $job_id );
			$this->leases->heartbeat( $job_id, $owner_token );

			++$processed;
		}

		$fresh = $this->job_repo->find( $job_id );
		if ( null === $fresh ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		if ( JobStatuses::is_terminal( (string) $fresh->status ) ) {
			return $fresh;
		}

		if ( ! $this->has_claimable_items( $job_id ) ) {
			$final = $this->jobs->finalize_job_from_items( $job_id );
			return is_wp_error( $final ) ? $final : $final;
		}

		return $this->job_repo->find( $job_id ) ?? $fresh;
	}

	/**
	 * Attempt lease claim or detect duplicate callback.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $owner_token Worker token.
	 * @return object|WP_Error|null Claimed job, null when held by other, or error.
	 */
	private function resolve_lease_claim( int $job_id, string $owner_token ) {
		$claimed = $this->leases->claim( $job_id, $owner_token );
		if ( is_wp_error( $claimed ) ) {
			return $claimed;
		}

		if ( null !== $claimed ) {
			return $claimed;
		}

		$fresh = $this->job_repo->find( $job_id );
		if ( null === $fresh ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		$holder = (string) ( $fresh->lease_owner ?? '' );
		if ( '' !== $holder && $holder !== $owner_token ) {
			$expires = (string) ( $fresh->lease_expires_at ?? '' );
			if ( '' !== $expires && $expires > current_time( 'mysql', true ) ) {
				return null;
			}
		}

		if ( RequestedActions::is_pending( (string) $fresh->requested_action ) ) {
			return null;
		}

		return $this->leases->claim( $job_id, $owner_token );
	}

	/**
	 * Whether queued or retry_wait items remain.
	 *
	 * @param int $job_id Job id.
	 */
	private function has_claimable_items( int $job_id ): bool {
		foreach ( array( ItemStatuses::QUEUED, ItemStatuses::RETRY_WAIT ) as $status ) {
			if ( array() !== $this->items->list_by_job( $job_id, $status ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Persist item outcome from ItemResult.
	 *
	 * @param int        $item_id Item id.
	 * @param ItemResult $result  Processor outcome.
	 */
	private function record_item_result( int $item_id, ItemResult $result ): void {
		$item   = $this->items->find( $item_id );
		$status = $result->status;

		if ( ItemStatuses::RETRY_WAIT === $status ) {
			$attempts = null !== $item ? (int) ( $item->attempt_count ?? 0 ) : 0;
			if ( $attempts >= self::MAX_ITEM_ATTEMPTS ) {
				$status = ItemStatuses::FAILED;
			}
		}

		$fields = array(
			'status'                  => $status,
			'result_code'             => $result->result_code,
			'glossary_version_actual' => $result->glossary_version_actual,
			'finished_at'             => ItemStatuses::is_terminal( $status ) || ItemStatuses::RETRY_WAIT === $status
				? current_time( 'mysql', true )
				: null,
		);

		if ( '' !== $result->error_code ) {
			$fields['last_error_code']    = $result->error_code;
			$fields['last_error_class']   = $result->error_class;
			$fields['last_error_message'] = $result->error_message;
		}

		$this->items->update( $item_id, $fields );
	}

	/**
	 * Generate an opaque worker lease token.
	 */
	private function generate_owner_token(): string {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 32, false, false );
		}

		return bin2hex( random_bytes( 16 ) );
	}
}
