<?php
/**
 * Stable TI.7 publication reason codes (ADR-0020).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Publication;

/**
 * Machine-readable publication eligibility / result reasons.
 */
final class PublicationReasonCodes {

	public const AUTOMATION_DISABLED           = 'automation_disabled';
	public const ASSESSMENT_BLOCKED            = 'assessment_blocked';
	public const ASSESSMENT_NEEDS_REVIEW       = 'assessment_needs_review';
	public const ASSESSMENT_REVIEW_RECOMMENDED = 'assessment_review_recommended';
	public const EVIDENCE_INCOMPLETE           = 'evidence_incomplete';
	public const HUMAN_APPROVAL_REQUIRED       = 'human_approval_required';
	public const SOURCE_NOT_PUBLIC             = 'source_not_public';
	public const PROVENANCE_NOT_ALLOWED        = 'provenance_not_allowed';
	public const REVIEW_REJECTED               = 'review_rejected';
	public const TRANSLATION_STALE             = 'translation_stale';
	public const PUBLICATION_ALREADY_ACTIVE    = 'publication_already_active';
	public const SEGMENT_MISSING               = 'segment_missing';
	public const STRUCTURALLY_CLEAN_REQUIRED   = 'structurally_clean_required';
	public const ELIGIBLE                      = 'eligible';
	public const PUBLISHED                     = 'published';
	public const UNPUBLISHED                   = 'unpublished';
	public const EXPECTED_STATUS_MISMATCH      = 'expected_status_mismatch';
}
