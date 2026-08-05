import { __ } from '@wordpress/i18n';

import type { ReviewMetadata } from '../types/view-models';
import { reviewerDisplayName } from '../utils/review-status';

interface ReviewMetaSummaryProps {
	review: ReviewMetadata;
}

/**
 * Read-only approved/rejected/pending metadata (ADR-0015 §24) — never an
 * editable field, and never shown for `not_submitted`.
 */
export default function ReviewMetaSummary( { review }: ReviewMetaSummaryProps ) {
	if ( 'not_submitted' === review.review_status ) {
		return null;
	}

	return (
		<dl
			className="aiml-review-meta"
			aria-label={ __( 'Review metadata', 'ai-multilingual' ) }
		>
			{ 'pending' === review.review_status && (
				<>
					<dt>{ __( 'Submitted by', 'ai-multilingual' ) }</dt>
					<dd>{ reviewerDisplayName( review.review_submitted_by ) }</dd>
					<dt>{ __( 'Submitted at', 'ai-multilingual' ) }</dt>
					<dd>{ review.review_submitted_at ?? '—' }</dd>
				</>
			) }
			{ 'approved' === review.review_status && (
				<>
					<dt>{ __( 'Approved by', 'ai-multilingual' ) }</dt>
					<dd>{ reviewerDisplayName( review.reviewed_by ) }</dd>
					<dt>{ __( 'Approved at', 'ai-multilingual' ) }</dt>
					<dd>{ review.reviewed_at ?? '—' }</dd>
				</>
			) }
			{ 'rejected' === review.review_status && (
				<>
					<dt>{ __( 'Rejected by', 'ai-multilingual' ) }</dt>
					<dd>{ reviewerDisplayName( review.rejected_by ) }</dd>
					<dt>{ __( 'Rejected at', 'ai-multilingual' ) }</dt>
					<dd>{ review.rejected_at ?? '—' }</dd>
					<dt>{ __( 'Rejection reason', 'ai-multilingual' ) }</dt>
					<dd className="aiml-review-reason">
						{ review.rejection_reason }
					</dd>
				</>
			) }
		</dl>
	);
}
