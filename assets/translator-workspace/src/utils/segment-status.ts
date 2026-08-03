import { __ } from '@wordpress/i18n';

import type { WorkspaceSegment } from '../types/view-models';

export type SegmentFilter =
	| 'all'
	| 'missing'
	| 'stale'
	| 'translated'
	| 'reviewed';

export function segmentStatusLabel( status: string ): string {
	switch ( status ) {
		case 'missing':
			return __( 'Missing', 'ai-multilingual' );
		case 'machine_translated':
			return __( 'Machine translated', 'ai-multilingual' );
		case 'manually_edited':
			return __( 'Edited', 'ai-multilingual' );
		case 'reviewed':
			return __( 'Reviewed', 'ai-multilingual' );
		case 'failed':
			return __( 'Failed', 'ai-multilingual' );
		case 'ignored':
			return __( 'Ignored', 'ai-multilingual' );
		default:
			return status;
	}
}

export function overallStateLabel( state: string ): string {
	switch ( state ) {
		case 'not_started':
			return __( 'Not started', 'ai-multilingual' );
		case 'in_progress':
			return __( 'In progress', 'ai-multilingual' );
		case 'complete':
			return __( 'Complete', 'ai-multilingual' );
		case 'stale':
			return __( 'Stale', 'ai-multilingual' );
		default:
			return state;
	}
}

export function postStatusLabel( status: string ): string {
	switch ( status ) {
		case 'publish':
			return __( 'Published', 'ai-multilingual' );
		case 'draft':
			return __( 'Draft', 'ai-multilingual' );
		case 'pending':
			return __( 'Pending review', 'ai-multilingual' );
		case 'private':
			return __( 'Private', 'ai-multilingual' );
		default:
			return status;
	}
}

export function postTypeLabel( postType: string ): string {
	switch ( postType ) {
		case 'page':
			return __( 'Page', 'ai-multilingual' );
		case 'post':
			return __( 'Post', 'ai-multilingual' );
		default:
			return postType;
	}
}

export function matchesSegmentFilter(
	segment: WorkspaceSegment,
	filter: SegmentFilter
): boolean {
	switch ( filter ) {
		case 'all':
			return true;
		case 'missing':
			return segment.status === 'missing';
		case 'stale':
			return segment.is_stale;
		case 'translated':
			return (
				segment.status !== 'missing' &&
				segment.status !== 'reviewed' &&
				segment.status !== 'ignored'
			);
		case 'reviewed':
			return segment.status === 'reviewed';
		default:
			return true;
	}
}

export function filterOptions(): Array< {
	value: SegmentFilter;
	label: string;
} > {
	return [
		{ value: 'all', label: __( 'All segments', 'ai-multilingual' ) },
		{ value: 'missing', label: __( 'Missing only', 'ai-multilingual' ) },
		{ value: 'stale', label: __( 'Stale only', 'ai-multilingual' ) },
		{ value: 'translated', label: __( 'Translated only', 'ai-multilingual' ) },
		{ value: 'reviewed', label: __( 'Reviewed only', 'ai-multilingual' ) },
	];
}
