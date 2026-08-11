<?php
/**
 * Authoritative UI admission for Jobs operator mutations (OTL.4 / TI.6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Reusable Jobs operation-admission contract.
 *
 * UI admission only — mutations must still revalidate in JobService + concurrency.
 * OTL and Jobs UI consume this; neither may recreate transition/retry rules.
 */
final class JobsOperationAdmission {

	public const OP_RESUME       = 'resume';
	public const OP_RETRY_FAILED = 'retry-failed';
	public const OP_PAUSE        = 'pause';
	public const OP_CANCEL       = 'cancel';
	public const OP_RUN          = 'run';

	public const SCOPE_JOB = 'job';

	public const REASON_CAPABILITY_DENIED = 'capability_denied';
	public const REASON_STATE_INELIGIBLE  = 'state_ineligible';
	public const REASON_NO_FAILED_ITEMS   = 'no_failed_items';
	public const REASON_JOB_MISSING       = 'job_missing';

	/**
	 * Item listing collaborator (BackgroundTranslationItemRepository or test double).
	 *
	 * @var object
	 */
	private object $items;

	/**
	 * Builds the admission service.
	 *
	 * @param object|null $items Object with list_by_job( int, ?string ): array.
	 */
	public function __construct( ?object $items = null ) {
		$this->items = $items ?? new BackgroundTranslationItemRepository();
	}

	/**
	 * Evaluates all primary operator operations for a job row.
	 *
	 * @param object|null         $job  Job row or null.
	 * @param array<string, bool> $caps Keys: can_run, can_cancel (optional can_manage).
	 * @return list<array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}>
	 */
	public function evaluate( ?object $job, array $caps = array() ): array {
		return array(
			$this->admit( self::OP_RESUME, $job, $caps ),
			$this->admit( self::OP_RETRY_FAILED, $job, $caps ),
			$this->admit( self::OP_PAUSE, $job, $caps ),
			$this->admit( self::OP_CANCEL, $job, $caps ),
			$this->admit( self::OP_RUN, $job, $caps ),
		);
	}

	/**
	 * Evaluates one operation.
	 *
	 * @param string              $operation_id Operation id.
	 * @param object|null         $job          Job row.
	 * @param array<string, bool> $caps         Capability flags.
	 * @return array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}
	 */
	public function admit( string $operation_id, ?object $job, array $caps = array() ): array {
		if ( null === $job ) {
			return $this->denied( $operation_id, self::REASON_JOB_MISSING );
		}

		return match ( $operation_id ) {
			self::OP_RESUME => $this->admit_resume( $job, $caps ),
			self::OP_RETRY_FAILED => $this->admit_retry_failed( $job, $caps ),
			self::OP_PAUSE => $this->admit_pause( $job, $caps ),
			self::OP_CANCEL => $this->admit_cancel( $job, $caps ),
			self::OP_RUN => $this->admit_run( $job, $caps ),
			default => $this->denied( $operation_id, self::REASON_STATE_INELIGIBLE ),
		};
	}

	/**
	 * Whether retry-failed is eligible for the job (mirrors JobService::retry_failed_items).
	 *
	 * @param object $job Job row.
	 */
	public function is_retry_failed_eligible( object $job ): bool {
		$status = (string) ( $job->status ?? '' );
		if (
			JobStatuses::is_terminal( $status )
			&& ! in_array( $status, array( JobStatuses::FAILED, JobStatuses::COMPLETED_WITH_ERRORS ), true )
		) {
			return false;
		}

		$failed = $this->items->list_by_job( (int) $job->job_id, ItemStatuses::FAILED );
		return array() !== $failed;
	}

	/**
	 * Resume admission — JobTransitionPolicy::validate_resume + cancel cap.
	 *
	 * @param object              $job  Job row.
	 * @param array<string, bool> $caps Caps.
	 * @return array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}
	 */
	private function admit_resume( object $job, array $caps ): array {
		if ( empty( $caps['can_cancel'] ) ) {
			return $this->denied( self::OP_RESUME, self::REASON_CAPABILITY_DENIED );
		}

		$valid = JobTransitionPolicy::validate_resume( $job );
		if ( is_wp_error( $valid ) ) {
			return $this->denied( self::OP_RESUME, self::REASON_STATE_INELIGIBLE );
		}

		return $this->allowed( self::OP_RESUME );
	}

	/**
	 * Retry-failed admission — extracted from JobService::retry_failed_items + run cap.
	 *
	 * @param object              $job  Job row.
	 * @param array<string, bool> $caps Caps.
	 * @return array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}
	 */
	private function admit_retry_failed( object $job, array $caps ): array {
		if ( empty( $caps['can_run'] ) ) {
			return $this->denied( self::OP_RETRY_FAILED, self::REASON_CAPABILITY_DENIED );
		}

		$status = (string) ( $job->status ?? '' );
		if (
			JobStatuses::is_terminal( $status )
			&& ! in_array( $status, array( JobStatuses::FAILED, JobStatuses::COMPLETED_WITH_ERRORS ), true )
		) {
			return $this->denied( self::OP_RETRY_FAILED, self::REASON_STATE_INELIGIBLE );
		}

		if ( ! $this->is_retry_failed_eligible( $job ) ) {
			return $this->denied( self::OP_RETRY_FAILED, self::REASON_NO_FAILED_ITEMS );
		}

		return $this->allowed( self::OP_RETRY_FAILED );
	}

	/**
	 * Pause admission (Jobs UI parity).
	 *
	 * @param object              $job  Job row.
	 * @param array<string, bool> $caps Caps.
	 * @return array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}
	 */
	private function admit_pause( object $job, array $caps ): array {
		if ( empty( $caps['can_cancel'] ) ) {
			return $this->denied( self::OP_PAUSE, self::REASON_CAPABILITY_DENIED );
		}

		$status = (string) ( $job->status ?? '' );
		$action = (string) ( $job->requested_action ?? '' );
		if ( in_array( $action, array( RequestedActions::PAUSE, RequestedActions::CANCEL ), true ) ) {
			return $this->denied( self::OP_PAUSE, self::REASON_STATE_INELIGIBLE );
		}

		if ( ! in_array( $status, array( JobStatuses::QUEUED, JobStatuses::RUNNING, JobStatuses::RETRY_WAIT ), true ) ) {
			return $this->denied( self::OP_PAUSE, self::REASON_STATE_INELIGIBLE );
		}

		return $this->allowed( self::OP_PAUSE );
	}

	/**
	 * Cancel admission (Jobs UI parity).
	 *
	 * @param object              $job  Job row.
	 * @param array<string, bool> $caps Caps.
	 * @return array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}
	 */
	private function admit_cancel( object $job, array $caps ): array {
		if ( empty( $caps['can_cancel'] ) ) {
			return $this->denied( self::OP_CANCEL, self::REASON_CAPABILITY_DENIED );
		}

		$status = (string) ( $job->status ?? '' );
		$action = (string) ( $job->requested_action ?? '' );
		if ( RequestedActions::CANCEL === $action ) {
			return $this->denied( self::OP_CANCEL, self::REASON_STATE_INELIGIBLE );
		}

		if (
			! in_array(
				$status,
				array(
					JobStatuses::QUEUED,
					JobStatuses::RUNNING,
					JobStatuses::RETRY_WAIT,
					JobStatuses::PAUSED,
				),
				true
			)
		) {
			return $this->denied( self::OP_CANCEL, self::REASON_STATE_INELIGIBLE );
		}

		return $this->allowed( self::OP_CANCEL );
	}

	/**
	 * Run/wake admission (Jobs UI parity).
	 *
	 * @param object              $job  Job row.
	 * @param array<string, bool> $caps Caps.
	 * @return array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}
	 */
	private function admit_run( object $job, array $caps ): array {
		if ( empty( $caps['can_run'] ) ) {
			return $this->denied( self::OP_RUN, self::REASON_CAPABILITY_DENIED );
		}

		$status = (string) ( $job->status ?? '' );
		if (
			! in_array(
				$status,
				array(
					JobStatuses::QUEUED,
					JobStatuses::PAUSED,
					JobStatuses::RETRY_WAIT,
					JobStatuses::FAILED,
					JobStatuses::COMPLETED_WITH_ERRORS,
				),
				true
			)
		) {
			return $this->denied( self::OP_RUN, self::REASON_STATE_INELIGIBLE );
		}

		return $this->allowed( self::OP_RUN );
	}

	/**
	 * Allowed admission descriptor.
	 *
	 * @param string $operation_id Operation id.
	 * @return array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}
	 */
	private function allowed( string $operation_id ): array {
		return array(
			'operation_id'   => $operation_id,
			'allowed'        => true,
			'reason_code'    => null,
			'mutation_scope' => self::SCOPE_JOB,
		);
	}

	/**
	 * Denied admission descriptor.
	 *
	 * @param string $operation_id Operation id.
	 * @param string $reason       Reason code.
	 * @return array{operation_id: string, allowed: bool, reason_code: string|null, mutation_scope: string}
	 */
	private function denied( string $operation_id, string $reason ): array {
		return array(
			'operation_id'   => $operation_id,
			'allowed'        => false,
			'reason_code'    => $reason,
			'mutation_scope' => self::SCOPE_JOB,
		);
	}
}
