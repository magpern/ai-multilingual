/**
 * Job item / outcome literacy helpers (P2 WP1–WP3).
 */

import { __ } from '@wordpress/i18n';

import type { TranslationJobItemSummary } from '../types/jobs';

/**
 * Operator-facing label for a job *item* status (not job lifecycle status).
 *
 * @param status Item status code (e.g. skipped_conflict).
 */
export function jobItemStatusLabel( status: string ): string {
	switch ( status ) {
		case 'queued':
			return __( 'Waiting', 'ai-multilingual' );
		case 'running':
			return __( 'Running', 'ai-multilingual' );
		case 'retry_wait':
			return __( 'Retry wait', 'ai-multilingual' );
		case 'completed':
			return __( 'Completed', 'ai-multilingual' );
		case 'failed':
			return __( 'Failed', 'ai-multilingual' );
		case 'stale_source':
			return __( 'Source moved during job', 'ai-multilingual' );
		case 'skipped_conflict':
			return __( 'Skipped — conflict protected', 'ai-multilingual' );
		case 'cancelled':
			return __( 'Cancelled', 'ai-multilingual' );
		default:
			return status;
	}
}

/**
 * Operator severity / attention class for an item status.
 *
 * @param status Item status code.
 */
export function jobItemAttentionKind(
	status: string
): 'info' | 'attention' | 'error' | 'success' {
	switch ( status ) {
		case 'skipped_conflict':
		case 'stale_source':
			return 'attention';
		case 'failed':
			return 'error';
		case 'completed':
			return 'success';
		default:
			return 'info';
	}
}

/**
 * Next-action guidance for attention item statuses (literacy only; actions
 * remain gated by capability + operations[] elsewhere).
 *
 * @param status Item status code.
 */
export function jobItemNextActionHint( status: string ): string {
	switch ( status ) {
		case 'skipped_conflict':
			return __(
				'A protected target conflict prevented overwrite. Open Operations or Translate to review/edit, or use confirmed Retranslate when allowed — Jobs will not silently overwrite.',
				'ai-multilingual'
			);
		case 'stale_source':
			return __(
				'The source changed while this job item was running. Create a fresh job after the source is stable.',
				'ai-multilingual'
			);
		case 'failed':
			return __(
				'Retry failed items when admitted, or inspect the provider/error details.',
				'ai-multilingual'
			);
		default:
			return '';
	}
}

/**
 * Items that warrant expanded operator attention (not only provider failures).
 *
 * @param items Job item summaries.
 */
export function attentionJobItems(
	items: TranslationJobItemSummary[] | undefined
): TranslationJobItemSummary[] {
	if ( ! items?.length ) {
		return [];
	}

	return items.filter( ( item ) =>
		[ 'failed', 'skipped_conflict', 'stale_source' ].includes( item.status )
	);
}

/**
 * Failure category label from JobsFailurePresenter categories.
 *
 * @param category Presenter category string.
 */
export function jobsFailureCategoryLabel( category: string ): string {
	switch ( category ) {
		case 'conflict':
			return __( 'Conflict protection', 'ai-multilingual' );
		case 'stale_source':
			return __( 'Source moved', 'ai-multilingual' );
		case 'provider':
			return __( 'Provider', 'ai-multilingual' );
		case 'budget':
			return __( 'Budget', 'ai-multilingual' );
		default:
			return category;
	}
}
