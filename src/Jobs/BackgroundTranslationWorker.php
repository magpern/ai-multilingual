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
	 * Retry taxonomy and backoff.
	 *
	 * @var BackgroundTranslationRetryPolicy
	 */
	private BackgroundTranslationRetryPolicy $retry_policy;

	/**
	 * Runtime budget enforcement.
	 *
	 * @var BackgroundTranslationBudgetPolicy
	 */
	private BackgroundTranslationBudgetPolicy $budget;

	/**
	 * Action Scheduler wake scheduling.
	 *
	 * @var BackgroundTranslationScheduler|null
	 */
	private ?BackgroundTranslationScheduler $scheduler;

	/**
	 * Provider availability checks.
	 *
	 * @var BackgroundTranslationJobProviderValidator|null
	 */
	private ?BackgroundTranslationJobProviderValidator $provider_validator;

	/**
	 * Audit logger (J7).
	 *
	 * @var BackgroundTranslationJobAuditLogger|null
	 */
	private ?BackgroundTranslationJobAuditLogger $audit;

	/**
	 * Bounded diagnostics (J7).
	 *
	 * @var BackgroundTranslationDiagnostics|null
	 */
	private ?BackgroundTranslationDiagnostics $diagnostics;

	/**
	 * Builds the worker.
	 *
	 * @param BackgroundTranslationItemProcessor             $processor           Item processor.
	 * @param BackgroundTranslationJobService|null           $jobs                Job service.
	 * @param BackgroundTranslationJobRepository|null        $job_repo            Job repository.
	 * @param BackgroundTranslationItemRepository|null       $items               Item repository.
	 * @param JobLeaseService|null                           $leases              Lease service.
	 * @param JobProgressReconciler|null                     $reconciler          Reconciler.
	 * @param BackgroundTranslationRetryPolicy|null          $retry_policy        Retry policy.
	 * @param BackgroundTranslationBudgetPolicy|null         $budget              Budget policy.
	 * @param BackgroundTranslationScheduler|null            $scheduler           AS scheduler.
	 * @param BackgroundTranslationJobProviderValidator|null $provider_validator  Provider validator.
	 * @param BackgroundTranslationJobAuditLogger|null       $audit               Audit logger.
	 * @param BackgroundTranslationDiagnostics|null          $diagnostics         Diagnostics.
	 */
	public function __construct(
		BackgroundTranslationItemProcessor $processor,
		?BackgroundTranslationJobService $jobs = null,
		?BackgroundTranslationJobRepository $job_repo = null,
		?BackgroundTranslationItemRepository $items = null,
		?JobLeaseService $leases = null,
		?JobProgressReconciler $reconciler = null,
		?BackgroundTranslationRetryPolicy $retry_policy = null,
		?BackgroundTranslationBudgetPolicy $budget = null,
		?BackgroundTranslationScheduler $scheduler = null,
		?BackgroundTranslationJobProviderValidator $provider_validator = null,
		?BackgroundTranslationJobAuditLogger $audit = null,
		?BackgroundTranslationDiagnostics $diagnostics = null
	) {
		$this->processor          = $processor;
		$this->job_repo           = $job_repo ?? new BackgroundTranslationJobRepository();
		$this->items              = $items ?? new BackgroundTranslationItemRepository();
		$this->leases             = $leases ?? new JobLeaseService( $this->job_repo, $this->items );
		$this->reconciler         = $reconciler ?? new JobProgressReconciler( $this->job_repo, $this->items );
		$this->retry_policy       = $retry_policy ?? new BackgroundTranslationRetryPolicy();
		$this->budget             = $budget ?? new BackgroundTranslationBudgetPolicy( $this->job_repo );
		$this->scheduler          = $scheduler;
		$this->provider_validator = $provider_validator;
		$this->audit              = $audit;
		$this->diagnostics        = $diagnostics;
		$this->jobs               = $jobs ?? new BackgroundTranslationJobService(
			$this->job_repo,
			$this->items,
			$this->leases,
			$this->reconciler,
			null,
			null,
			$scheduler,
			$this->budget,
			$provider_validator,
			$audit,
			$diagnostics
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

		$prior_status = (string) $job->status;

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

		if (
			JobStatuses::RUNNING === (string) $job->status
			&& in_array( $prior_status, array( JobStatuses::QUEUED, JobStatuses::RETRY_WAIT ), true )
		) {
			$this->audit_started( $job );
		}

		if ( null !== $this->provider_validator && ! $this->provider_validator->is_provider_available( $job ) ) {
			$paused = $this->jobs->pause_for_orchestration_error(
				$job_id,
				'provider_unavailable',
				'Job provider is not available for execution.',
				'permanent'
			);
			$this->leases->release( $job_id, $owner_token );

			return is_wp_error( $paused ) ? $paused : $paused;
		}

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

			$fresh_job = $this->job_repo->find( $job_id );
			if ( null === $fresh_job ) {
				return new WP_Error( 'job_not_found', 'Job not found.' );
			}

			if ( ! $this->has_claimable_items( $job_id ) ) {
				break;
			}

			if ( ! $this->budget->can_claim_next( $fresh_job ) ) {
				$paused = $this->jobs->pause_for_orchestration_error(
					$job_id,
					'budget_exceeded',
					'Job budget hard limit reached before next item.',
					'permanent'
				);

				return is_wp_error( $paused ) ? $paused : $paused;
			}

			$item = $this->items->claim_next( $job_id );
			if ( null === $item ) {
				break;
			}

			$result = $this->processor->process( $fresh_job, $item, $post );
			$this->record_item_result( $job_id, (int) $item->item_id, $result );

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
	 * @param int        $job_id  Job id.
	 * @param int        $item_id Item id.
	 * @param ItemResult $result  Processor outcome.
	 */
	private function record_item_result( int $job_id, int $item_id, ItemResult $result ): void {
		$item         = $this->items->find( $item_id );
		$attempts     = null !== $item ? (int) ( $item->attempt_count ?? 0 ) : 0;
		$status       = $result->status;
		$record_usage = ItemStatuses::COMPLETED === $status;

		if ( ItemStatuses::RETRY_WAIT === $status ) {
			if ( ! $this->retry_policy->should_retry( $attempts ) ) {
				$status = ItemStatuses::FAILED;
			}
		}

		if ( $record_usage ) {
			$usage = $this->budget->record_usage(
				$job_id,
				$result->usage_requests,
				$result->usage_tokens
			);
			if ( is_wp_error( $usage ) ) {
				$this->jobs->pause_for_orchestration_error(
					$job_id,
					'budget_exceeded',
					'Failed to record job budget usage.',
					'permanent'
				);
			}
		}

		if ( ItemStatuses::RETRY_WAIT === $status && $result->is_retryable() ) {
			$delay = $this->retry_policy->delay_seconds( $attempts, $result->retry_after_seconds );
			if ( null !== $this->scheduler ) {
				$this->scheduler->enqueue_job_delayed( $job_id, $delay );
			}

			$this->maybe_transition_job_retry_wait( $job_id );
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

		$this->audit_item_result( $job_id, $item_id, $status, $result );
	}

	/**
	 * Emits item-level audit events and increments diagnostics counters.
	 *
	 * @param int        $job_id  Job id.
	 * @param int        $item_id Item id.
	 * @param string     $status  Final item status.
	 * @param ItemResult $result  Processor outcome.
	 */
	private function audit_item_result( int $job_id, int $item_id, string $status, ItemResult $result ): void {
		$job  = $this->job_repo->find( $job_id );
		$item = $this->items->find( $item_id );
		if ( null === $job || null === $item ) {
			return;
		}

		if ( ItemStatuses::STALE_SOURCE === $status ) {
			if ( null !== $this->diagnostics ) {
				$this->diagnostics->increment( BackgroundTranslationDiagnostics::STALE_SOURCE_CONFLICTS );
			}
			if ( null !== $this->audit ) {
				$this->audit->log(
					BackgroundTranslationJobAuditEvents::STALE_SOURCE,
					$this->audit->payload_from_job(
						$job,
						array(
							'item_id'        => $item_id,
							'segment_key'    => (string) $item->segment_key,
							'attempts'       => (int) ( $item->attempt_count ?? 0 ),
							'result_class'   => $result->error_class,
							'error_code'     => $result->error_code,
							'source_surface' => 'worker',
						)
					)
				);
			}
			return;
		}

		if ( ItemStatuses::RETRY_WAIT === $status && $result->is_retryable() ) {
			if ( null !== $this->diagnostics ) {
				$this->diagnostics->increment( BackgroundTranslationDiagnostics::ITEM_RETRIES );
			}
		}

		if ( ItemStatuses::FAILED === $status ) {
			if ( null !== $this->diagnostics && 'retryable' === $result->error_class ) {
				$this->diagnostics->increment( BackgroundTranslationDiagnostics::PROVIDER_ERRORS );
			}
			if ( null !== $this->audit ) {
				$this->audit->log(
					BackgroundTranslationJobAuditEvents::ITEM_FAILED,
					$this->audit->payload_from_job(
						$job,
						array(
							'item_id'        => $item_id,
							'segment_key'    => (string) $item->segment_key,
							'attempts'       => (int) ( $item->attempt_count ?? 0 ),
							'result_class'   => $result->error_class,
							'error_code'     => $result->error_code,
							'source_surface' => 'worker',
						)
					)
				);
			}
		}
	}

	/**
	 * Emits translation_job_started when a worker claims a runnable job.
	 *
	 * @param object $job Job row after lease claim.
	 */
	private function audit_started( object $job ): void {
		if ( null === $this->audit ) {
			return;
		}

		$this->audit->log(
			BackgroundTranslationJobAuditEvents::STARTED,
			$this->audit->payload_from_job( $job, array( 'source_surface' => 'worker' ) )
		);
	}

	/**
	 * Transition running job to retry_wait when an item enters backoff.
	 *
	 * @param int $job_id Job id.
	 */
	private function maybe_transition_job_retry_wait( int $job_id ): void {
		$job = $this->job_repo->find( $job_id );
		if ( null === $job ) {
			return;
		}

		if ( JobStatuses::RUNNING !== (string) $job->status ) {
			return;
		}

		$valid = JobTransitionPolicy::validate_transition( JobStatuses::RUNNING, JobStatuses::RETRY_WAIT );
		if ( is_wp_error( $valid ) ) {
			return;
		}

		$this->job_repo->update(
			$job_id,
			array(
				'status' => JobStatuses::RETRY_WAIT,
			)
		);
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
