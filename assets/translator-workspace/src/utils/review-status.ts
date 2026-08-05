import { __ } from '@wordpress/i18n';

import type { ReviewMetadata, WorkspaceSegment } from '../types/view-models';

export type ReviewQueueFilter = 'pending' | 'approved' | 'rejected' | 'not_submitted' | 'all';

const UNSUBMITTABLE_STATUSES = new Set( [ 'missing', 'ignored', 'failed' ] );

export const REASON_MIN_LENGTH = 1;
export const REASON_MAX_LENGTH = 512;

export function reviewStatusLabel( status: string ): string {
	switch ( status ) {
		case 'not_submitted':
			return __( 'Not submitted', 'ai-multilingual' );
		case 'pending':
			return __( 'Pending review', 'ai-multilingual' );
		case 'approved':
			return __( 'Approved', 'ai-multilingual' );
		case 'rejected':
			return __( 'Rejected', 'ai-multilingual' );
		default:
			return status;
	}
}

export function reviewQueueFilterOptions(): Array< {
	value: ReviewQueueFilter;
	label: string;
} > {
	return [
		{ value: 'pending', label: __( 'Pending review', 'ai-multilingual' ) },
		{ value: 'approved', label: __( 'Approved', 'ai-multilingual' ) },
		{ value: 'rejected', label: __( 'Rejected', 'ai-multilingual' ) },
		{ value: 'not_submitted', label: __( 'Not submitted', 'ai-multilingual' ) },
		{ value: 'all', label: __( 'All review states', 'ai-multilingual' ) },
	];
}

/**
 * A translator may submit (or resubmit after correction) only from
 * `not_submitted` or `rejected`, and only when the segment is editable and
 * has eligible translated text (ADR-0015 §4.1).
 */
export function canSubmitForReview( segment: WorkspaceSegment ): boolean {
	if ( ! segment.can_edit ) {
		return false;
	}

	if ( UNSUBMITTABLE_STATUSES.has( segment.status ) ) {
		return false;
	}

	if ( '' === segment.translated_text.trim() ) {
		return false;
	}

	return (
		'not_submitted' === segment.review_status ||
		'rejected' === segment.review_status
	);
}

/**
 * Approve/reject are legal only from `pending` (ADR-0015 §4.1).
 */
export function canDecideReview( review: ReviewMetadata ): boolean {
	return 'pending' === review.review_status;
}

export function hasRejectionReason( review: ReviewMetadata ): boolean {
	return 'rejected' === review.review_status && '' !== review.rejection_reason.trim();
}

/**
 * Trims and validates a rejection reason against the 1–512 char contract
 * (REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md §24). Returns the trimmed reason
 * plus a validity flag so callers can gate submission without a round trip.
 */
export function normalizeRejectionReason( reason: string ): {
	value: string;
	valid: boolean;
} {
	const trimmed = reason.trim();
	return {
		value: trimmed,
		valid:
			trimmed.length >= REASON_MIN_LENGTH && trimmed.length <= REASON_MAX_LENGTH,
	};
}

export function reviewerDisplayName( userId: number | null ): string {
	if ( null === userId || userId <= 0 ) {
		return __( '(unknown)', 'ai-multilingual' );
	}

	return `#${ userId }`;
}
