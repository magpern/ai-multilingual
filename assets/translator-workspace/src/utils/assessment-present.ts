import { __ } from '@wordpress/i18n';

const CATEGORY_LABELS: Record< string, string > = {
	blocked: 'Blocked',
	needs_review: 'Needs review (TI.5)',
	review_recommended: 'Review recommended',
	structurally_clean: 'Structurally clean',
};

/**
 * Human label for a TI.5 overall_category value.
 * `needs_review` is TI.5 assessment vocabulary — distinct from ADR-0015 pending review.
 */
export function assessmentCategoryLabel( category: string ): string {
	if ( 'needs_review' === category ) {
		return __(
			'Needs review (TI.5 assessment)',
			'ai-multilingual'
		);
	}

	switch ( category ) {
		case 'blocked':
			return __( 'Blocked', 'ai-multilingual' );
		case 'review_recommended':
			return __( 'Review recommended', 'ai-multilingual' );
		case 'structurally_clean':
			return __( 'Structurally clean', 'ai-multilingual' );
		default:
			return category;
	}
}

/**
 * Whether the category is the TI.5 `needs_review` machine id (not ADR-0015 pending).
 */
export function isTi5NeedsReviewCategory( category: string ): boolean {
	return 'needs_review' === category;
}

/**
 * Extracts finding reason strings from TI.5 assessment payload.
 */
export function assessmentFindingReasons(
	assessment: Record< string, unknown > | null | undefined
): string[] {
	if ( ! assessment ) {
		return [];
	}

	const reasons: string[] = [];
	const buckets = [
		'hard_blockers',
		'errors',
		'warnings',
	] as const;

	for ( const bucket of buckets ) {
		const items = assessment[ bucket ];
		if ( ! Array.isArray( items ) ) {
			continue;
		}
		for ( const item of items ) {
			if ( item && 'object' === typeof item ) {
				const record = item as Record< string, unknown >;
				const code = String( record.code ?? record.finding_code ?? '' );
				const message = String( record.message ?? record.summary ?? '' );
				if ( code || message ) {
					reasons.push(
						code && message ? `${ code }: ${ message }` : code || message
					);
				}
			}
		}
	}

	return reasons;
}

/**
 * English fallback label map for tests (non-i18n).
 */
export function assessmentCategoryLabelEnglish( category: string ): string {
	return CATEGORY_LABELS[ category ] ?? category;
}
