<?php
/**
 * Background translation job domain lifecycle (create, pause, cancel, finalize).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Job orchestration domain service — no Action Scheduler wake in J2 (plan §19).
 */
final class BackgroundTranslationJobService {

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
	 * Builds the job service.
	 *
	 * @param BackgroundTranslationJobRepository|null  $jobs       Job repository.
	 * @param BackgroundTranslationItemRepository|null $items      Item repository.
	 * @param JobLeaseService|null                     $leases     Lease service.
	 * @param JobProgressReconciler|null               $reconciler Progress reconciler.
	 */
	public function __construct(
		?BackgroundTranslationJobRepository $jobs = null,
		?BackgroundTranslationItemRepository $items = null,
		?JobLeaseService $leases = null,
		?JobProgressReconciler $reconciler = null
	) {
		$this->jobs       = $jobs ?? new BackgroundTranslationJobRepository();
		$this->items      = $items ?? new BackgroundTranslationItemRepository();
		$this->leases     = $leases ?? new JobLeaseService( $this->jobs, $this->items );
		$this->reconciler = $reconciler ?? new JobProgressReconciler( $this->jobs, $this->items );
	}

	/**
	 * Create a job, materialize items, and acquire active_lock_key.
	 *
	 * J2 note: translate_missing and retranslate_stale accept a pre-resolved
	 * segment_keys list (and optional source_hash_captured / translation_hash_captured
	 * per item via segment_snapshots). Store resolution arrives in J3.
	 *
	 * @param array<string, mixed> $args Create arguments.
	 * @return object|WP_Error
	 */
	public function create_job( array $args ) {
		$validation = $this->validate_create_args( $args );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$job_type = (string) $args['job_type'];
		$segments = $this->resolve_segment_keys( $args, $job_type );
		if ( is_wp_error( $segments ) ) {
			return $segments;
		}

		if ( array() === $segments ) {
			return new WP_Error( 'empty_workload', 'No segments to materialize for this job.' );
		}

		$idempotency = $this->resolve_idempotency_key( $args );
		if ( is_wp_error( $idempotency ) ) {
			return $idempotency;
		}

		$idempotency_key = $idempotency['key'];
		$force_new       = $idempotency['force_new'];
		if ( ! $force_new ) {
			$existing = $this->jobs->find_by_idempotency_key( $idempotency_key );
			if ( null !== $existing ) {
				$reuse = $this->handle_idempotency_hit( $existing, $args );
				if ( null !== $reuse ) {
					return $reuse;
				}
			}
		}

		$lock_key = JobLockKey::build(
			(string) $args['source_type'],
			(int) $args['source_id'],
			(int) ( $args['language_id'] ?? 0 )
		);

		$lock_holder = $this->jobs->find_active_by_lock_key( $lock_key );
		if ( null !== $lock_holder && empty( $args['force_new'] ) ) {
			if ( ! $force_new ) {
				$existing = $this->jobs->find_by_idempotency_key( $idempotency_key );
				if ( null !== $existing && (int) $existing->job_id === (int) $lock_holder->job_id ) {
					return $existing;
				}
			}

			return new WP_Error(
				'lock_key_conflict',
				'Another active job holds the object+language lock.',
				array( 'existing_job_id' => (int) $lock_holder->job_id )
			);
		}

		$job_row = array(
			'job_type'                  => $job_type,
			'status'                    => JobStatuses::QUEUED,
			'requested_action'          => RequestedActions::NONE,
			'batch_id'                  => isset( $args['batch_id'] ) ? (string) $args['batch_id'] : null,
			'idempotency_key'           => $idempotency_key,
			'source_type'               => (string) $args['source_type'],
			'source_id'                 => (int) $args['source_id'],
			'language_id'               => (int) $args['language_id'],
			'lock_key'                  => $lock_key,
			'active_lock_key'           => $this->leases->active_lock_for_create( $lock_key ),
			'provider_id'               => (string) ( $args['provider_id'] ?? '' ),
			'prompt_profile'            => (string) ( $args['prompt_profile'] ?? '' ),
			'prompt_version'            => (string) ( $args['prompt_version'] ?? '' ),
			'provider_config_fp'        => (string) ( $args['provider_config_fp'] ?? '' ),
			'glossary_version_intended' => (int) ( $args['glossary_version_intended'] ?? 0 ),
			'created_by'                => (int) ( $args['created_by'] ?? 0 ),
		);

		$inserted = $this->jobs->insert( $job_row );
		if ( is_wp_error( $inserted ) ) {
			return $this->map_create_insert_error( $inserted, $lock_key, $args );
		}

		$snapshots = (array) ( $args['segment_snapshots'] ?? array() );
		foreach ( $segments as $segment_key ) {
			$snapshot = (array) ( $snapshots[ $segment_key ] ?? array() );
			$item     = $this->items->insert(
				array(
					'job_id'                    => (int) $inserted->job_id,
					'segment_key'               => $segment_key,
					'status'                    => ItemStatuses::QUEUED,
					'source_hash_captured'      => (string) ( $snapshot['source_hash_captured'] ?? '' ),
					'translation_hash_captured' => (string) ( $snapshot['translation_hash_captured'] ?? '' ),
				)
			);

			if ( is_wp_error( $item ) ) {
				return $item;
			}
		}

		$reconciled = $this->reconciler->reconcile( (int) $inserted->job_id );
		if ( is_wp_error( $reconciled ) ) {
			return $reconciled;
		}

		if ( 0 === (int) $reconciled->total_items ) {
			$failed = $this->transition_job(
				(int) $inserted->job_id,
				JobStatuses::FAILED,
				array(
					'last_error_code'    => 'empty_workload',
					'last_error_message' => 'Job materialized zero items.',
					'finished_at'        => current_time( 'mysql', true ),
				)
			);
			if ( is_wp_error( $failed ) ) {
				return $failed;
			}
			$this->leases->clear_active_lock_on_terminal( (int) $inserted->job_id );

			return $this->jobs->find( (int) $inserted->job_id );
		}

		return $this->jobs->find( (int) $inserted->job_id );
	}

	/**
	 * Set requested_action to pause (observed at safe item boundary).
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error
	 */
	public function request_pause( int $job_id ) {
		return $this->set_requested_action( $job_id, RequestedActions::PAUSE );
	}

	/**
	 * Set requested_action to cancel (observed at safe item boundary).
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error
	 */
	public function request_cancel( int $job_id ) {
		return $this->set_requested_action( $job_id, RequestedActions::CANCEL );
	}

	/**
	 * Resume a paused job.
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error
	 */
	public function resume( int $job_id ) {
		$job = $this->jobs->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		$valid = JobTransitionPolicy::validate_resume( $job );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$updated = $this->transition_job(
			$job_id,
			JobTransitionPolicy::resume_target(),
			array(
				'requested_action' => RequestedActions::NONE,
			)
		);

		return is_wp_error( $updated ) ? $updated : $updated;
	}

	/**
	 * Apply pause/cancel at a safe item boundary.
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error|null Null when no pending requested_action.
	 */
	public function observe_requested_action_at_boundary( int $job_id ) {
		$job = $this->jobs->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		$action = (string) $job->requested_action;
		if ( ! RequestedActions::is_pending( $action ) ) {
			return null;
		}

		if ( RequestedActions::PAUSE === $action ) {
			$updated = $this->transition_job(
				$job_id,
				JobTransitionPolicy::pause_target(),
				array(
					'requested_action' => RequestedActions::NONE,
				)
			);

			return is_wp_error( $updated ) ? $updated : $updated;
		}

		if ( RequestedActions::CANCEL === $action ) {
			$this->cancel_remaining_items( $job_id );

			$updated = $this->transition_job(
				$job_id,
				JobTransitionPolicy::cancel_target(),
				array(
					'requested_action' => RequestedActions::NONE,
					'finished_at'      => current_time( 'mysql', true ),
				)
			);

			if ( is_wp_error( $updated ) ) {
				return $updated;
			}

			$this->leases->clear_active_lock_on_terminal( $job_id );
			$this->jobs->archive_idempotency_key( $job_id );
			$this->reconciler->reconcile( $job_id );

			return $this->jobs->find( $job_id );
		}

		return null;
	}

	/**
	 * Reset failed items to queued for operator retry-failed.
	 *
	 * Preserves last_error_* evidence until a new attempt starts (J2: not cleared on reset).
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error
	 */
	public function retry_failed_items( int $job_id ) {
		$job = $this->jobs->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		if ( JobStatuses::is_terminal( (string) $job->status ) ) {
			return new WP_Error( 'illegal_transition', 'Cannot retry items on a terminal job.' );
		}

		$failed = $this->items->list_by_job( $job_id, ItemStatuses::FAILED );
		foreach ( $failed as $item ) {
			$check = ItemTransitionPolicy::validate_transition( ItemStatuses::FAILED, ItemStatuses::QUEUED );
			if ( is_wp_error( $check ) ) {
				continue;
			}

			$this->items->update(
				(int) $item->item_id,
				array(
					'status'      => ItemStatuses::QUEUED,
					'result_code' => '',
				)
			);
		}

		if ( JobStatuses::FAILED === (string) $job->status ) {
			$this->transition_job(
				$job_id,
				JobStatuses::QUEUED,
				array(
					'finished_at' => null,
				)
			);
		}

		$reconciled = $this->reconciler->reconcile( $job_id );
		return is_wp_error( $reconciled ) ? $reconciled : $reconciled;
	}

	/**
	 * Derive terminal job status from item rows (plan §16).
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error
	 */
	public function finalize_job_from_items( int $job_id ) {
		$job = $this->jobs->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		if ( JobStatuses::CANCELLED === (string) $job->status ) {
			return $job;
		}

		$counts = $this->items->count_by_status( $job_id );

		$completed            = (int) ( $counts[ ItemStatuses::COMPLETED ] ?? 0 );
		$terminal_non_success = 0;

		foreach ( $counts as $status => $count ) {
			if ( ItemStatuses::is_non_success_terminal( (string) $status ) ) {
				$terminal_non_success += (int) $count;
			}
		}

		$active_items = 0;
		foreach ( array( ItemStatuses::QUEUED, ItemStatuses::RUNNING, ItemStatuses::RETRY_WAIT ) as $active_status ) {
			$active_items += (int) ( $counts[ $active_status ] ?? 0 );
		}

		if ( $active_items > 0 ) {
			return $job;
		}

		$target = JobStatuses::FAILED;
		if ( $completed > 0 && 0 === $terminal_non_success ) {
			$target = JobStatuses::COMPLETED;
		} elseif ( $completed > 0 && $terminal_non_success > 0 ) {
			$target = JobStatuses::COMPLETED_WITH_ERRORS;
		} elseif ( 0 === $completed && $terminal_non_success > 0 ) {
			$target = JobStatuses::FAILED;
		} elseif ( 0 === $completed && 0 === $terminal_non_success ) {
			$target = JobStatuses::FAILED;
		}

		$updated = $this->transition_job(
			$job_id,
			$target,
			array(
				'finished_at' => current_time( 'mysql', true ),
			)
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$this->leases->clear_active_lock_on_terminal( $job_id );

		if ( in_array( $target, array( JobStatuses::COMPLETED, JobStatuses::COMPLETED_WITH_ERRORS, JobStatuses::FAILED ), true ) ) {
			$this->jobs->archive_idempotency_key( $job_id );
		}

		$this->reconciler->reconcile( $job_id );

		return $this->jobs->find( $job_id );
	}

	/**
	 * Validate create args and workload bounds.
	 *
	 * @param array<string, mixed> $args Create arguments.
	 * @return true|WP_Error
	 */
	private function validate_create_args( array $args ) {
		$job_type = (string) ( $args['job_type'] ?? '' );
		if ( ! in_array( $job_type, JobTypes::all(), true ) ) {
			return new WP_Error( 'invalid_job_type', 'Unknown job type.' );
		}

		if ( empty( $args['source_type'] ) || empty( $args['source_id'] ) || empty( $args['language_id'] ) ) {
			return new WP_Error( 'invalid_job_scope', 'Job requires source_type, source_id, and language_id.' );
		}

		if ( JobTypes::TRANSLATE_SELECTED === $job_type ) {
			$keys = (array) ( $args['segment_keys'] ?? array() );
			if ( count( $keys ) > JobBounds::MAX_SELECTED_SEGMENTS ) {
				return new WP_Error( 'workload_limit_exceeded', 'translate_selected exceeds max segment count.' );
			}
		}

		$segments = (array) ( $args['segment_keys'] ?? array() );
		if ( count( $segments ) > JobBounds::MAX_ITEMS_PER_JOB ) {
			return new WP_Error( 'workload_limit_exceeded', 'Job exceeds max items per job.' );
		}

		return true;
	}

	/**
	 * Resolve segment keys for materialization.
	 *
	 * @param array<string, mixed> $args     Create arguments.
	 * @param string               $job_type Job type.
	 * @return list<string>|WP_Error
	 */
	private function resolve_segment_keys( array $args, string $job_type ) {
		if ( ! JobTypes::requires_explicit_segments( $job_type ) ) {
			return new WP_Error( 'invalid_job_type', 'Job type requires explicit segment_keys in J2.' );
		}

		$keys = array_values(
			array_unique(
				array_map(
					static fn( $key ): string => trim( (string) $key ),
					(array) ( $args['segment_keys'] ?? array() )
				)
			)
		);

		$keys = array_values( array_filter( $keys, static fn( string $key ): bool => '' !== $key ) );

		if ( JobTypes::TRANSLATE_SELECTED === $job_type && array() === $keys ) {
			return new WP_Error( 'empty_workload', 'translate_selected requires segment_keys.' );
		}

		return $keys;
	}

	/**
	 * Resolve idempotency key, honoring force_new.
	 *
	 * @param array<string, mixed> $args Create arguments.
	 * @return array{key: string, force_new: bool}|WP_Error
	 */
	private function resolve_idempotency_key( array $args ) {
		$force_new = ! empty( $args['force_new'] );
		$base      = JobIdempotencyKey::build( $args );

		if ( $force_new ) {
			$suffix = function_exists( 'wp_generate_password' )
				? wp_generate_password( 12, false, false )
				: bin2hex( random_bytes( 6 ) );

			return array(
				'key'       => hash( 'sha256', $base . '|fn|' . $suffix ),
				'force_new' => true,
			);
		}

		return array(
			'key'       => $base,
			'force_new' => false,
		);
	}

	/**
	 * Handle an existing idempotency key hit.
	 *
	 * @param object               $existing Existing job row.
	 * @param array<string, mixed> $args     Create arguments.
	 * @return object|WP_Error|null Existing job, error, or null to proceed with insert.
	 */
	private function handle_idempotency_hit( object $existing, array $args ) {
		$status = (string) $existing->status;

		if ( JobStatuses::is_active( $status ) ) {
			if ( JobIdempotencyKey::args_match( $args, $this->args_from_job( $existing, $args ) ) ) {
				return $existing;
			}

			if ( ! empty( $args['client_token'] ) ) {
				return new WP_Error( 'idempotency_conflict', 'Active job idempotency key with different parameters.' );
			}

			return new WP_Error( 'idempotency_conflict', 'Active job idempotency key with different parameters.' );
		}

		if ( in_array( $status, array( JobStatuses::COMPLETED, JobStatuses::COMPLETED_WITH_ERRORS ), true ) ) {
			return new WP_Error( 'idempotency_conflict', 'Completed job retains idempotency key.' );
		}

		if ( in_array( $status, array( JobStatuses::CANCELLED, JobStatuses::FAILED ), true ) ) {
			$this->jobs->archive_idempotency_key( (int) $existing->job_id );

			return null;
		}

		return new WP_Error( 'idempotency_conflict', 'Conflicting idempotency key state.' );
	}

	/**
	 * Reconstruct comparable create args from a stored job row.
	 *
	 * @param object               $job  Job row.
	 * @param array<string, mixed> $args Incoming args (for segment keys).
	 * @return array<string, mixed>
	 */
	private function args_from_job( object $job, array $args ): array {
		$items = $this->items->list_by_job( (int) $job->job_id );
		$keys  = array_map(
			static fn( object $item ): string => (string) $item->segment_key,
			$items
		);

		return array(
			'job_type'       => (string) $job->job_type,
			'source_type'    => (string) $job->source_type,
			'source_id'      => (int) $job->source_id,
			'language_id'    => (int) $job->language_id,
			'segment_keys'   => $keys,
			'provider_id'    => (string) $job->provider_id,
			'prompt_profile' => (string) $job->prompt_profile,
			'prompt_version' => (string) $job->prompt_version,
			'created_by'     => (int) $job->created_by,
			'client_token'   => (string) ( $args['client_token'] ?? '' ),
		);
	}

	/**
	 * Map insert failures to stable domain errors.
	 *
	 * @param WP_Error             $error    Repository error.
	 * @param string               $lock_key Lock key.
	 * @param array<string, mixed> $args     Create args.
	 * @return WP_Error
	 */
	private function map_create_insert_error( WP_Error $error, string $lock_key, array $args ): WP_Error {
		$code = $error->get_error_code();

		if ( 'job_idempotency_conflict' === $code ) {
			$existing = $this->jobs->find_by_idempotency_key( JobIdempotencyKey::build( $args ) );
			if ( null !== $existing ) {
				$reuse = $this->handle_idempotency_hit( $existing, $args );
				if ( $reuse instanceof WP_Error ) {
					return new WP_Error( 'idempotency_conflict', $reuse->get_error_message() );
				}
				if ( null !== $reuse ) {
					return $reuse;
				}
			}

			return new WP_Error( 'idempotency_conflict', 'Duplicate job idempotency key.' );
		}

		if ( 'job_lock_key_conflict' === $code ) {
			return new WP_Error( 'lock_key_conflict', 'Another active job holds the object+language lock.' );
		}

		return $error;
	}

	/**
	 * Cancel queued/retry_wait items when job cancel is observed.
	 *
	 * @param int $job_id Job id.
	 */
	private function cancel_remaining_items( int $job_id ): void {
		foreach ( array( ItemStatuses::QUEUED, ItemStatuses::RETRY_WAIT ) as $status ) {
			$items = $this->items->list_by_job( $job_id, $status );
			foreach ( $items as $item ) {
				$this->items->update(
					(int) $item->item_id,
					array(
						'status'      => ItemStatuses::CANCELLED,
						'result_code' => ItemStatuses::CANCELLED,
						'finished_at' => current_time( 'mysql', true ),
					)
				);
			}
		}
	}

	/**
	 * Set requested_action when job is non-terminal.
	 *
	 * @param int    $job_id Job id.
	 * @param string $action Requested action.
	 * @return object|WP_Error
	 */
	private function set_requested_action( int $job_id, string $action ) {
		$job = $this->jobs->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		if ( JobStatuses::is_terminal( (string) $job->status ) ) {
			return new WP_Error( 'illegal_transition', 'Cannot request action on a terminal job.' );
		}

		if ( ! in_array( $action, RequestedActions::all(), true ) ) {
			return new WP_Error( 'invalid_requested_action', 'Unknown requested_action.' );
		}

		$updated = $this->jobs->update(
			$job_id,
			array(
				'requested_action' => $action,
			)
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return $this->jobs->find( $job_id );
	}

	/**
	 * Transition job status with validation.
	 *
	 * @param int                  $job_id Job id.
	 * @param string               $to     Target status.
	 * @param array<string, mixed> $extra  Extra fields.
	 * @return object|WP_Error|null
	 */
	private function transition_job( int $job_id, string $to, array $extra = array() ) {
		$job = $this->jobs->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		$from  = (string) $job->status;
		$valid = JobTransitionPolicy::validate_transition( $from, $to );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$fields           = $extra;
		$fields['status'] = $to;

		$updated = $this->jobs->update( $job_id, $fields );

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return $updated ?? $this->jobs->find( $job_id );
	}
}
