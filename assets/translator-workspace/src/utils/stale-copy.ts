/**
 * State-accurate stale operator copy (P2 A2).
 *
 * Derive messaging from publication state. Do not invent absolute visitor /
 * overlay outcomes — publication is necessary but not always sufficient, and
 * with the gate off unpublished rows may still be overlay-eligible.
 */

import { __ } from '@wordpress/i18n';

export type StalePublishStatus = 'published' | 'unpublished' | string;

export interface StaleOperatorCopyArgs {
	isStale: boolean;
	publishStatus: StalePublishStatus | null | undefined;
}

export interface StaleOperatorCopy {
	/** Short badge / chip text. */
	badge: string;
	/** Longer notice explaining why + publication consequence. */
	notice: string;
}

/**
 * Operator-facing stale copy for Store is_stale rows.
 *
 * @param args.isStale        Store is_stale flag.
 * @param args.publishStatus  Current publish_status (published vs unpublished).
 */
export function staleOperatorCopy(
	args: StaleOperatorCopyArgs
): StaleOperatorCopy | null {
	if ( ! args.isStale ) {
		return null;
	}

	const publishStatus = ( args.publishStatus ?? '' ).trim();

	if ( 'published' === publishStatus ) {
		return {
			badge: __( 'Stale — still published', 'ai-multilingual' ),
			notice: __(
				'This translation is stale because the source changed. It remains published until you edit or retranslate. Visitor display still depends on overlay and route eligibility.',
				'ai-multilingual'
			),
		};
	}

	if ( 'unpublished' === publishStatus ) {
		return {
			badge: __( 'Stale — not published', 'ai-multilingual' ),
			notice: __(
				'This translation is stale because the source changed. It is currently unpublished — edit or retranslate before publishing. Overlay eligibility may still depend on publication gate settings.',
				'ai-multilingual'
			),
		};
	}

	return {
		badge: __( 'Stale — source changed', 'ai-multilingual' ),
		notice: __(
			'This translation is stale because the source changed. Check publication status before deciding the next action.',
			'ai-multilingual'
		),
	};
}

/**
 * Resolve publish_status from a Workspace segment payload (top-level or meta).
 *
 * @param segment Workspace segment-like object.
 */
export function publishStatusFromSegment( segment: {
	publish_status?: string;
	meta?: { publish_status?: unknown; [ key: string ]: unknown };
} ): string {
	const top = ( segment.publish_status ?? '' ).trim();
	if ( top ) {
		return top;
	}
	const fromMeta = segment.meta?.publish_status;
	return 'string' === typeof fromMeta ? fromMeta.trim() : '';
}
