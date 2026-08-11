import { __ } from '@wordpress/i18n';

export type DetailConflictKind = 'source' | 'translation';

/**
 * Operator-facing message for optimistic concurrency conflicts.
 */
export function detailConflictMessage( kind: DetailConflictKind ): string {
	if ( 'translation' === kind ) {
		return __(
			'The saved translation changed since you loaded this detail. Your draft was kept — refresh or compare before saving again.',
			'ai-multilingual'
		);
	}

	return __(
		'The source text changed since you loaded this detail. Your draft was kept — refresh or compare before saving again.',
		'ai-multilingual'
	);
}

/**
 * Short aria-live status after a conflict response.
 */
export function detailConflictStatusMessage( kind: DetailConflictKind ): string {
	if ( 'translation' === kind ) {
		return __(
			'Save conflict: the saved translation changed elsewhere.',
			'ai-multilingual'
		);
	}

	return __(
		'Save conflict: the source text changed elsewhere.',
		'ai-multilingual'
	);
}
