<?php
/**
 * Bounded batch iteration for review actions (ADR-0015 §11.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

use AIMultilingual\Workspace\WorkspaceQAException;
use AIMultilingual\Workspace\WorkspaceService;
use WP_Error;
use WP_Post;

/**
 * Owns bulk iteration and partial-result aggregation for review actions only.
 *
 * One shared `$action` applies to every item in the batch (submit, approve, or
 * reject), matching the capability check already performed once per request
 * by the REST permission callback. Each item is still validated and applied
 * independently — no all-or-nothing transaction, no silent skip.
 */
final class ReviewBatchCoordinator {

	public const BATCH_LIMIT = 50;

	public const ACTION_SUBMIT  = 'submit';
	public const ACTION_APPROVE = 'approve';
	public const ACTION_REJECT  = 'reject';

	/**
	 * Legal batch actions.
	 *
	 * @return list<string>
	 */
	public static function actions(): array {
		return array( self::ACTION_SUBMIT, self::ACTION_APPROVE, self::ACTION_REJECT );
	}

	/**
	 * Injected dependency.
	 *
	 * @var WorkspaceService
	 */
	private WorkspaceService $workspace;

	/**
	 * Audit logger for the batch-completed summary event (ADR-0015 §12).
	 *
	 * @var ReviewAuditLogger
	 */
	private ReviewAuditLogger $audit;

	/**
	 * Builds the collaborator.
	 *
	 * @param WorkspaceService       $workspace Workspace command facade.
	 * @param ReviewAuditLogger|null $audit     Audit logger.
	 */
	public function __construct( WorkspaceService $workspace, ?ReviewAuditLogger $audit = null ) {
		$this->workspace = $workspace;
		$this->audit     = $audit ?? new ReviewAuditLogger();
	}

	/**
	 * Applies one review action to multiple segments with per-item results.
	 *
	 * @param WP_Post                          $post          Canonical post.
	 * @param int                              $language_id   Target language id.
	 * @param string                           $action        One of self::actions().
	 * @param array<int, array<string, mixed>> $items         Per-item payloads.
	 * @param int                              $user_id       Acting user id.
	 * @param string                           $shared_reason Fallback reject reason when an item omits one.
	 * @return array<string, mixed>|WP_Error
	 */
	public function run_batch(
		WP_Post $post,
		int $language_id,
		string $action,
		array $items,
		int $user_id,
		string $shared_reason = ''
	) {
		if ( ! in_array( $action, self::actions(), true ) ) {
			return new WP_Error(
				'aiml_invalid_review_action',
				__( 'Unknown batch review action.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( count( $items ) > self::BATCH_LIMIT ) {
			return new WP_Error(
				'aiml_batch_too_large',
				sprintf(
					/* translators: %d: maximum batch size */
					__( 'Batch size exceeds the limit of %d segments.', 'ai-multilingual' ),
					self::BATCH_LIMIT
				),
				array( 'status' => 422 )
			);
		}

		$succeeded = array();
		$failed    = array();

		foreach ( $items as $item ) {
			$item = is_array( $item ) ? $item : array();
			$key  = (string) ( $item['segment_key'] ?? '' );

			if ( '' === $key ) {
				$failed[] = array(
					'segment_key' => '',
					'code'        => 'aiml_invalid_segment',
					'message'     => __( 'Segment key is required.', 'ai-multilingual' ),
				);
				continue;
			}

			$expected_review_status = isset( $item['expected_review_status'] ) && '' !== $item['expected_review_status']
				? (string) $item['expected_review_status']
				: null;
			$submitted_hash         = isset( $item['submitted_translation_hash'] ) && '' !== $item['submitted_translation_hash']
				? (string) $item['submitted_translation_hash']
				: null;

			try {
				$dto = $this->apply(
					$action,
					$post,
					$language_id,
					$key,
					$user_id,
					$expected_review_status,
					$submitted_hash,
					(string) ( $item['reason'] ?? $shared_reason )
				);
			} catch ( ReviewWorkflowException $exception ) {
				$failed[] = array(
					'segment_key' => $key,
					'code'        => $exception->get_error_code(),
					'message'     => $exception->getMessage(),
					'context'     => $exception->get_context(),
				);
				continue;
			} catch ( WorkspaceQAException $qa_exception ) {
				$failed[] = array(
					'segment_key' => $key,
					'code'        => 'aiml_qa_blocked',
					'message'     => $qa_exception->getMessage(),
					'qa'          => $qa_exception->qa()->to_array(),
				);
				continue;
			} catch ( ReviewQAUnavailableException $qa_unavailable ) {
				$failed[] = array(
					'segment_key' => $key,
					'code'        => 'aiml_review_qa_unavailable',
					'message'     => $qa_unavailable->getMessage(),
				);
				continue;
			} catch ( \InvalidArgumentException $exception ) {
				$failed[] = array(
					'segment_key' => $key,
					'code'        => 'aiml_invalid_segment',
					'message'     => $exception->getMessage(),
				);
				continue;
			}

			$succeeded[] = $dto;
		}

		if ( array() === $failed ) {
			$status = 'completed';
		} elseif ( array() === $succeeded ) {
			$status = 'failed';
		} else {
			$status = 'partial';
		}

		$this->audit->log(
			ReviewAuditEvents::BATCH_COMPLETED,
			array(
				'post_id'         => (int) $post->ID,
				'language_id'     => $language_id,
				'user_id'         => $user_id,
				'action'          => $action,
				'total_count'     => count( $items ),
				'succeeded_count' => count( $succeeded ),
				'failed_count'    => count( $failed ),
				'batch_status'    => $status,
				'source_surface'  => 'workspace_batch',
			)
		);

		return array(
			'status'   => $status,
			'segments' => $succeeded,
			'errors'   => $failed,
		);
	}

	/**
	 * Dispatches one item to the matching WorkspaceService review method.
	 *
	 * @param string      $action                 One of self::actions().
	 * @param WP_Post     $post                   Canonical post.
	 * @param int         $language_id            Target language id.
	 * @param string      $segment_key            Segment key.
	 * @param int         $user_id                Acting user id.
	 * @param string|null $expected_review_status Optional optimistic review_status.
	 * @param string|null $submitted_hash         Optional client submitted hash.
	 * @param string      $reason                 Rejection reason (reject only).
	 * @return array<string, mixed>
	 *
	 * @throws ReviewWorkflowException When the transition is illegal or conflicts.
	 * @throws WorkspaceQAException When QA blocks an approval.
	 * @throws ReviewQAUnavailableException When QA cannot be evaluated on approval.
	 */
	private function apply(
		string $action,
		WP_Post $post,
		int $language_id,
		string $segment_key,
		int $user_id,
		?string $expected_review_status,
		?string $submitted_hash,
		string $reason
	): array {
		switch ( $action ) {
			case self::ACTION_APPROVE:
				return $this->workspace->approve_review(
					$post,
					$language_id,
					$segment_key,
					$user_id,
					$expected_review_status,
					$submitted_hash
				);

			case self::ACTION_REJECT:
				return $this->workspace->reject_review(
					$post,
					$language_id,
					$segment_key,
					$user_id,
					$reason,
					$expected_review_status,
					$submitted_hash
				);

			case self::ACTION_SUBMIT:
			default:
				return $this->workspace->submit_review(
					$post,
					$language_id,
					$segment_key,
					$user_id,
					$expected_review_status
				);
		}
	}
}
