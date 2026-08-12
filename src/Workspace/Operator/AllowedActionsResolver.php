<?php
/**
 * Read-only UI admission resolver for OTL operator actions.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Operator;

use AIMultilingual\Plugin;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Jobs\JobsOperationAdmission;
use AIMultilingual\Surface\SurfaceCapabilityNames;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Translation\Publication\PublicationDecision;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Review\ReviewCapabilities;
use WP_Post;

/**
 * Computes effective allowed_actions for the current operator and observed state.
 *
 * UI admission only — NOT mutation authority. Mutations must revalidate via
 * ReviewWorkflowService / PublicationService / Jobs at execution time.
 */
final class AllowedActionsResolver {

	public const ACTION_EDIT                = 'edit';
	public const ACTION_SUBMIT_FOR_REVIEW   = 'submit_for_review';
	public const ACTION_APPROVE             = 'approve';
	public const ACTION_REJECT              = 'reject';
	public const ACTION_UNPUBLISH           = 'unpublish';
	public const ACTION_EXPLAIN_PUBLICATION = 'explain_publication';
	public const ACTION_PUBLISH             = 'publish';
	public const ACTION_OPEN_SOURCE         = 'open_source';
	public const ACTION_OPEN_FRONTEND       = 'open_frontend';
	public const ACTION_RETRANSLATE_STALE   = 'retranslate_stale';
	public const ACTION_OPEN_JOB            = 'open_job';
	public const ACTION_OPEN_JOBS           = 'open_jobs';
	public const ACTION_RESUME_JOB          = 'resume_job';
	public const ACTION_RETRY_FAILED_JOB    = 'retry_failed_job';

	/**
	 * Optional surface registry for mutate/supports facts (O(1) lookup).
	 *
	 * @var SurfaceRegistry|null
	 */
	private static ?SurfaceRegistry $surfaces = null;

	/**
	 * Wires the surface registry for OTL list/detail admission facts.
	 *
	 * @param SurfaceRegistry|null $surfaces Registry or null to clear.
	 */
	public static function set_surface_registry( ?SurfaceRegistry $surfaces ): void {
		self::$surfaces = $surfaces;
	}

	/**
	 * Whether the row's source_type supports mutate via registry (or legacy post).
	 *
	 * @param object $row Store row.
	 */
	private function source_supports_mutate( object $row ): bool {
		$source_type = (string) ( $row->source_type ?? '' );
		if ( null !== self::$surfaces ) {
			return self::$surfaces->supports( $source_type, SurfaceCapabilityNames::MUTATE );
		}
		return Store::SOURCE_POST === $source_type;
	}

	/**
	 * Resolves list-safe actions (no publish; no TI.7 eligibility evaluation).
	 *
	 * @param object               $row              Hydrated Store row.
	 * @param array<string, mixed> $capability_flags Keys: can_translate, can_review, can_edit_source.
	 * @param array<string, mixed> $links            Optional link availability hints.
	 * @return list<array{id: string, allowed: bool, reason_code: string|null}>
	 */
	public function resolve_for_list( object $row, array $capability_flags, array $links = array() ): array {
		$actions = array(
			$this->action_edit( $row, $capability_flags ),
			$this->action_submit( $row, $capability_flags ),
			$this->action_approve( $row, $capability_flags ),
			$this->action_reject( $row, $capability_flags ),
			$this->action_unpublish( $row, $capability_flags ),
			$this->action_explain( $capability_flags ),
			$this->descriptor( self::ACTION_PUBLISH, false, ActionReasonCodes::DETAIL_ONLY ),
			$this->action_open_source( $links, $capability_flags ),
			$this->action_open_frontend( $links ),
			$this->action_retranslate_stale( $row, $capability_flags ),
		);

		return $actions;
	}

	/**
	 * Resolves full detail actions including publish via TI.7 decision and Jobs admission.
	 *
	 * @param object                    $row              Hydrated Store row.
	 * @param array<string, mixed>      $capability_flags Capability flags.
	 * @param PublicationDecision|null  $publication      TI.7 decision (required for publish).
	 * @param array<string, mixed>      $links            Link hints.
	 * @param array<string, mixed>|null $jobs            OTL Jobs subtree (detail).
	 * @return list<array{id: string, allowed: bool, reason_code: string|null}>
	 */
	public function resolve_for_detail(
		object $row,
		array $capability_flags,
		?PublicationDecision $publication,
		array $links = array(),
		?array $jobs = null
	): array {
		$actions  = $this->resolve_for_list( $row, $capability_flags, $links );
		$publish  = $this->action_publish( $row, $capability_flags, $publication );
		$replaced = false;
		foreach ( $actions as $index => $action ) {
			if ( self::ACTION_PUBLISH === $action['id'] ) {
				$actions[ $index ] = $publish;
				$replaced          = true;
				break;
			}
		}
		if ( ! $replaced ) {
			$actions[] = $publish;
		}

		foreach ( $this->jobs_actions( $capability_flags, $jobs ) as $jobs_action ) {
			$actions[] = $jobs_action;
		}

		return $actions;
	}

	/**
	 * Resolves edit admission.
	 *
	 * @param object               $row              Row.
	 * @param array<string, mixed> $capability_flags Caps.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_edit( object $row, array $capability_flags ): array {
		if ( ! $this->source_supports_mutate( $row ) ) {
			return $this->descriptor( self::ACTION_EDIT, false, ActionReasonCodes::MUTATION_UNSUPPORTED_TYPE );
		}
		if ( empty( $capability_flags['can_translate'] ) ) {
			return $this->descriptor( self::ACTION_EDIT, false, ActionReasonCodes::CAPABILITY_DENIED );
		}
		if ( empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_EDIT, false, ActionReasonCodes::SOURCE_ACCESS_DENIED );
		}
		$status = (string) ( $row->status ?? '' );
		if ( Store::STATUS_IGNORED === $status ) {
			return $this->descriptor( self::ACTION_EDIT, false, ActionReasonCodes::STATE_INELIGIBLE );
		}

		return $this->descriptor( self::ACTION_EDIT, true, null );
	}

	/**
	 * Mirrors ReviewWorkflowService::submit eligibility (presentation only).
	 *
	 * @param object               $row              Row.
	 * @param array<string, mixed> $capability_flags Caps.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_submit( object $row, array $capability_flags ): array {
		if ( ! $this->source_supports_mutate( $row ) ) {
			return $this->descriptor( self::ACTION_SUBMIT_FOR_REVIEW, false, ActionReasonCodes::MUTATION_UNSUPPORTED_TYPE );
		}
		if ( empty( $capability_flags['can_translate'] ) || empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_SUBMIT_FOR_REVIEW, false, ActionReasonCodes::CAPABILITY_DENIED );
		}

		$text   = (string) ( $row->translated_text ?? '' );
		$status = (string) ( $row->status ?? '' );
		$review = (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED );

		if ( '' === $text ) {
			return $this->descriptor( self::ACTION_SUBMIT_FOR_REVIEW, false, ActionReasonCodes::STATE_INELIGIBLE );
		}
		if ( in_array( $status, array( Store::STATUS_MISSING, Store::STATUS_IGNORED, Store::STATUS_FAILED ), true ) ) {
			return $this->descriptor( self::ACTION_SUBMIT_FOR_REVIEW, false, ActionReasonCodes::STATE_INELIGIBLE );
		}
		if ( Store::REVIEW_APPROVED === $review ) {
			return $this->descriptor( self::ACTION_SUBMIT_FOR_REVIEW, false, ActionReasonCodes::STATE_INELIGIBLE );
		}
		if ( Store::REVIEW_PENDING === $review ) {
			// Resubmit only with hash drift — without client hash, treat as ineligible for admission.
			return $this->descriptor( self::ACTION_SUBMIT_FOR_REVIEW, false, ActionReasonCodes::STATE_INELIGIBLE );
		}
		if ( ! in_array( $review, array( Store::REVIEW_NOT_SUBMITTED, Store::REVIEW_REJECTED ), true ) ) {
			return $this->descriptor( self::ACTION_SUBMIT_FOR_REVIEW, false, ActionReasonCodes::STATE_INELIGIBLE );
		}

		return $this->descriptor( self::ACTION_SUBMIT_FOR_REVIEW, true, null );
	}

	/**
	 * Resolves approve admission.
	 *
	 * @param object               $row              Row.
	 * @param array<string, mixed> $capability_flags Caps.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_approve( object $row, array $capability_flags ): array {
		if ( ! $this->source_supports_mutate( $row ) ) {
			return $this->descriptor( self::ACTION_APPROVE, false, ActionReasonCodes::MUTATION_UNSUPPORTED_TYPE );
		}
		if ( empty( $capability_flags['can_review'] ) || empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_APPROVE, false, ActionReasonCodes::CAPABILITY_DENIED );
		}
		if ( Store::REVIEW_PENDING !== (string) ( $row->review_status ?? '' ) ) {
			return $this->descriptor( self::ACTION_APPROVE, false, ActionReasonCodes::STATE_INELIGIBLE );
		}

		return $this->descriptor( self::ACTION_APPROVE, true, null );
	}

	/**
	 * Resolves reject admission.
	 *
	 * @param object               $row              Row.
	 * @param array<string, mixed> $capability_flags Caps.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_reject( object $row, array $capability_flags ): array {
		if ( ! $this->source_supports_mutate( $row ) ) {
			return $this->descriptor( self::ACTION_REJECT, false, ActionReasonCodes::MUTATION_UNSUPPORTED_TYPE );
		}
		if ( empty( $capability_flags['can_review'] ) || empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_REJECT, false, ActionReasonCodes::CAPABILITY_DENIED );
		}
		if ( Store::REVIEW_PENDING !== (string) ( $row->review_status ?? '' ) ) {
			return $this->descriptor( self::ACTION_REJECT, false, ActionReasonCodes::STATE_INELIGIBLE );
		}

		return $this->descriptor( self::ACTION_REJECT, true, null );
	}

	/**
	 * Resolves unpublish admission.
	 *
	 * @param object               $row              Row.
	 * @param array<string, mixed> $capability_flags Caps.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_unpublish( object $row, array $capability_flags ): array {
		if ( ! $this->source_supports_mutate( $row ) ) {
			return $this->descriptor( self::ACTION_UNPUBLISH, false, ActionReasonCodes::MUTATION_UNSUPPORTED_TYPE );
		}
		if ( empty( $capability_flags['can_translate'] ) || empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_UNPUBLISH, false, ActionReasonCodes::CAPABILITY_DENIED );
		}
		if ( Store::PUBLISH_PUBLISHED !== (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED ) ) {
			return $this->descriptor( self::ACTION_UNPUBLISH, false, ActionReasonCodes::NOT_PUBLISHED );
		}

		return $this->descriptor( self::ACTION_UNPUBLISH, true, null );
	}

	/**
	 * Resolves explain_publication admission.
	 *
	 * @param array<string, mixed> $capability_flags Caps.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_explain( array $capability_flags ): array {
		if ( empty( $capability_flags['can_translate'] ) || empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_EXPLAIN_PUBLICATION, false, ActionReasonCodes::CAPABILITY_DENIED );
		}

		return $this->descriptor( self::ACTION_EXPLAIN_PUBLICATION, true, null );
	}

	/**
	 * Publish admission from TI.7 decision only (detail).
	 *
	 * @param object                   $row              Row.
	 * @param array<string, mixed>     $capability_flags Caps.
	 * @param PublicationDecision|null $publication      TI.7 decision.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_publish( object $row, array $capability_flags, ?PublicationDecision $publication ): array {
		if ( ! $this->source_supports_mutate( $row ) ) {
			return $this->descriptor( self::ACTION_PUBLISH, false, ActionReasonCodes::MUTATION_UNSUPPORTED_TYPE );
		}
		if ( empty( $capability_flags['can_translate'] ) || empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_PUBLISH, false, ActionReasonCodes::CAPABILITY_DENIED );
		}
		if ( Store::PUBLISH_PUBLISHED === (string) ( $row->publish_status ?? '' ) ) {
			return $this->descriptor( self::ACTION_PUBLISH, false, ActionReasonCodes::PUBLICATION_ALREADY_ACTIVE );
		}
		if ( null === $publication ) {
			return $this->descriptor( self::ACTION_PUBLISH, false, ActionReasonCodes::PUBLICATION_INELIGIBLE );
		}
		if ( ! $publication->eligible ) {
			return $this->descriptor( self::ACTION_PUBLISH, false, ActionReasonCodes::PUBLICATION_INELIGIBLE );
		}

		return $this->descriptor( self::ACTION_PUBLISH, true, null );
	}

	/**
	 * Resolves open_source admission.
	 *
	 * @param array<string, mixed> $links            Links.
	 * @param array<string, mixed> $capability_flags Caps.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_open_source( array $links, array $capability_flags ): array {
		if ( empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_OPEN_SOURCE, false, ActionReasonCodes::SOURCE_ACCESS_DENIED );
		}
		if ( empty( $links['edit_link'] ) ) {
			return $this->descriptor( self::ACTION_OPEN_SOURCE, false, ActionReasonCodes::LINK_UNAVAILABLE );
		}

		return $this->descriptor( self::ACTION_OPEN_SOURCE, true, null );
	}

	/**
	 * Resolves open_frontend admission.
	 *
	 * @param array<string, mixed> $links Links.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_open_frontend( array $links ): array {
		if ( empty( $links['frontend_url'] ) && empty( $links['source_frontend_url'] ) ) {
			return $this->descriptor( self::ACTION_OPEN_FRONTEND, false, ActionReasonCodes::LINK_UNAVAILABLE );
		}

		return $this->descriptor( self::ACTION_OPEN_FRONTEND, true, null );
	}

	/**
	 * Resolves retranslate_stale admission (OTL.3 — sync retranslate path).
	 *
	 * @param object               $row              Row.
	 * @param array<string, mixed> $capability_flags Caps.
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function action_retranslate_stale( object $row, array $capability_flags ): array {
		if ( ! $this->source_supports_mutate( $row ) ) {
			return $this->descriptor( self::ACTION_RETRANSLATE_STALE, false, ActionReasonCodes::MUTATION_UNSUPPORTED_TYPE );
		}
		if ( empty( $capability_flags['can_translate'] ) || empty( $capability_flags['can_edit_source'] ) ) {
			return $this->descriptor( self::ACTION_RETRANSLATE_STALE, false, ActionReasonCodes::CAPABILITY_DENIED );
		}
		if ( empty( $row->is_stale ) ) {
			return $this->descriptor( self::ACTION_RETRANSLATE_STALE, false, ActionReasonCodes::STATE_INELIGIBLE );
		}

		return $this->descriptor( self::ACTION_RETRANSLATE_STALE, true, null );
	}

	/**
	 * Maps TI.6 JobsOperationAdmission into OTL allowed_actions (no local retry policy).
	 *
	 * @param array<string, mixed>      $capability_flags Caps.
	 * @param array<string, mixed>|null $jobs             Jobs subtree.
	 * @return list<array{id: string, allowed: bool, reason_code: string|null}>
	 */
	private function jobs_actions( array $capability_flags, ?array $jobs ): array {
		$can_view  = ! empty( $capability_flags['can_view_jobs'] );
		$open_jobs = $this->descriptor(
			self::ACTION_OPEN_JOBS,
			$can_view,
			$can_view ? null : ActionReasonCodes::CAPABILITY_DENIED
		);

		$has_job = is_array( $jobs )
			&& isset( $jobs['association'] )
			&& is_array( $jobs['association'] )
			&& isset( $jobs['association']['job']['job_id'] );

		$open_job = $this->descriptor(
			self::ACTION_OPEN_JOB,
			$can_view && $has_job,
			! $can_view
				? ActionReasonCodes::CAPABILITY_DENIED
				: ( $has_job ? null : ActionReasonCodes::STATE_INELIGIBLE )
		);

		$resume = $this->descriptor( self::ACTION_RESUME_JOB, false, ActionReasonCodes::STATE_INELIGIBLE );
		$retry  = $this->descriptor( self::ACTION_RETRY_FAILED_JOB, false, ActionReasonCodes::STATE_INELIGIBLE );

		$operations = is_array( $jobs['association']['operations'] ?? null )
			? $jobs['association']['operations']
			: array();

		foreach ( $operations as $op ) {
			if ( ! is_array( $op ) ) {
				continue;
			}
			$op_id   = (string) ( $op['operation_id'] ?? '' );
			$allowed = ! empty( $op['allowed'] );
			$reason  = isset( $op['reason_code'] ) ? (string) $op['reason_code'] : ActionReasonCodes::STATE_INELIGIBLE;
			if ( JobsOperationAdmission::OP_RESUME === $op_id ) {
				$resume = $this->descriptor(
					self::ACTION_RESUME_JOB,
					$allowed,
					$allowed ? null : ( '' !== $reason ? $reason : ActionReasonCodes::STATE_INELIGIBLE )
				);
			}
			if ( JobsOperationAdmission::OP_RETRY_FAILED === $op_id ) {
				$retry = $this->descriptor(
					self::ACTION_RETRY_FAILED_JOB,
					$allowed,
					$allowed ? null : ( '' !== $reason ? $reason : ActionReasonCodes::STATE_INELIGIBLE )
				);
			}
		}

		return array( $open_jobs, $open_job, $resume, $retry );
	}

	/**
	 * Builds one action descriptor.
	 *
	 * @param string      $id          Action id.
	 * @param bool        $allowed     Allowed.
	 * @param string|null $reason_code Reason when denied (or metadata).
	 * @return array{id: string, allowed: bool, reason_code: string|null}
	 */
	private function descriptor( string $id, bool $allowed, ?string $reason_code ): array {
		return array(
			'id'          => $id,
			'allowed'     => $allowed,
			'reason_code' => $allowed && ActionReasonCodes::DEFERRED_MILESTONE !== $reason_code ? null : $reason_code,
		);
	}

	/**
	 * Builds capability flags for the current user and optional post.
	 *
	 * @param int|null     $user_id User id (null = current).
	 * @param WP_Post|null $post Post when source is a post.
	 * @return array{can_translate: bool, can_review: bool, can_edit_source: bool, can_view_jobs: bool, can_run_jobs: bool, can_cancel_jobs: bool}
	 */
	public static function capability_flags( ?int $user_id = null, ?WP_Post $post = null ): array {
		$user_id = $user_id ?? get_current_user_id();

		$can_translate = user_can( $user_id, Plugin::CAPABILITY );
		$can_review    = user_can( $user_id, ReviewCapabilities::REVIEW_TRANSLATIONS );
		$can_edit      = true;
		if ( $post instanceof WP_Post ) {
			$can_edit = user_can( $user_id, 'edit_post', (int) $post->ID );
		}

		return array(
			'can_translate'   => $can_translate,
			'can_review'      => $can_review,
			'can_edit_source' => $can_edit,
			'can_view_jobs'   => user_can( $user_id, JobsCapabilities::VIEW_JOBS ),
			'can_run_jobs'    => user_can( $user_id, JobsCapabilities::RUN_JOBS ),
			'can_cancel_jobs' => user_can( $user_id, JobsCapabilities::CANCEL_JOBS ),
		);
	}
}
