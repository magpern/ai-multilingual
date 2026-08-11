/**
 * OTL.1 operational attention vocabulary (collision-free).
 *
 * Machine ID `needs_review` is reserved for TI.5 AssessmentCategory only.
 */

import { __ } from '@wordpress/i18n';

export type AttentionPreset =
	| 'all'
	| 'stale'
	| 'review_pending'
	| 'review_rejected'
	| 'unpublished'
	| 'translation_failed';

export type AttentionReason = Exclude< AttentionPreset, 'all' >;

export const ATTENTION_REASON_IDS: AttentionReason[] = [
	'stale',
	'review_pending',
	'review_rejected',
	'unpublished',
	'translation_failed',
];

export function isAttentionReason( value: string ): value is AttentionReason {
	return ( ATTENTION_REASON_IDS as string[] ).includes( value );
}

export function isAttentionPreset( value: string ): value is AttentionPreset {
	return 'all' === value || isAttentionReason( value );
}

/** Parse attention from URL; reserved TI.5 `needs_review` is invalid (not remapped silently). */
export function parseAttentionFromUrl(
	value: string | null | undefined
): { attention: AttentionPreset; invalidReserved: boolean } {
	const key = ( value ?? '' ).trim();
	if ( '' === key || 'all' === key ) {
		return { attention: 'all', invalidReserved: false };
	}
	if ( 'needs_review' === key ) {
		return { attention: 'all', invalidReserved: true };
	}
	if ( ! isAttentionPreset( key ) ) {
		return { attention: 'all', invalidReserved: false };
	}
	return { attention: key, invalidReserved: false };
}

/** @deprecated Prefer parseAttentionFromUrl for URL hydration. */
export function sanitizeAttentionPreset( value: string | null | undefined ): AttentionPreset {
	return parseAttentionFromUrl( value ).attention;
}

export function attentionReasonLabel( reason: string ): string {
	switch ( reason ) {
		case 'stale':
			return __( 'Stale', 'ai-multilingual' );
		case 'review_pending':
			return __( 'Pending review', 'ai-multilingual' );
		case 'review_rejected':
			return __( 'Rejected', 'ai-multilingual' );
		case 'unpublished':
			return __( 'Unpublished', 'ai-multilingual' );
		case 'translation_failed':
			return __( 'Translation failed', 'ai-multilingual' );
		default:
			return reason;
	}
}

export function attentionFilterOptions(): Array< {
	value: AttentionPreset;
	label: string;
} > {
	return [
		{ value: 'all', label: __( 'All', 'ai-multilingual' ) },
		{ value: 'stale', label: __( 'Stale', 'ai-multilingual' ) },
		{
			value: 'review_pending',
			label: __( 'Pending review', 'ai-multilingual' ),
		},
		{ value: 'review_rejected', label: __( 'Rejected', 'ai-multilingual' ) },
		{ value: 'unpublished', label: __( 'Unpublished', 'ai-multilingual' ) },
		{
			value: 'translation_failed',
			label: __( 'Translation failed', 'ai-multilingual' ),
		},
	];
}

export interface AttentionCounts {
	total: number;
	stale: number;
	review_pending: number;
	review_rejected: number;
	unpublished: number;
	translation_failed: number;
}

export const EMPTY_ATTENTION_COUNTS: AttentionCounts = {
	total: 0,
	stale: 0,
	review_pending: 0,
	review_rejected: 0,
	unpublished: 0,
	translation_failed: 0,
};
