<?php
/**
 * Review Workflow audit event name catalog (ADR-0015 §12).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

/**
 * Frozen, stable review audit event identifiers.
 */
final class ReviewAuditEvents {

	public const SUBMITTED           = 'review_submitted';
	public const APPROVED            = 'review_approved';
	public const REJECTED            = 'review_rejected';
	public const RESUBMITTED         = 'review_resubmitted';
	public const INVALIDATED_BY_EDIT = 'review_invalidated_by_edit';
	public const BATCH_COMPLETED     = 'review_batch_completed';

	/**
	 * Returns all registered audit event names.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::SUBMITTED,
			self::APPROVED,
			self::REJECTED,
			self::RESUBMITTED,
			self::INVALIDATED_BY_EDIT,
			self::BATCH_COMPLETED,
		);
	}
}
