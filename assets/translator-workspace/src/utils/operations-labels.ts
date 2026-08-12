/**
 * Human-readable axis labels for Operations (OTL.6 presentation only).
 */

import { __ } from '@wordpress/i18n';

export function publishStatusLabel( status: string ): string {
	switch ( status ) {
		case 'published':
			return __( 'Published', 'ai-multilingual' );
		case 'unpublished':
			return __( 'Unpublished', 'ai-multilingual' );
		default:
			return status || __( 'Unknown', 'ai-multilingual' );
	}
}

export function bulkOutcomeLabel( outcome: string ): string {
	switch ( outcome ) {
		case 'published':
			return __( 'Published', 'ai-multilingual' );
		case 'unpublished':
			return __( 'Unpublished', 'ai-multilingual' );
		case 'noop':
			return __( 'No change', 'ai-multilingual' );
		case 'enqueued':
			return __( 'Enqueued for Jobs', 'ai-multilingual' );
		case 'skipped':
			return __( 'Skipped', 'ai-multilingual' );
		case 'blocked':
			return __( 'Blocked', 'ai-multilingual' );
		case 'conflict':
			return __( 'Conflict', 'ai-multilingual' );
		case 'unauthorized':
			return __( 'Not permitted', 'ai-multilingual' );
		case 'failed':
			return __( 'Failed', 'ai-multilingual' );
		default:
			return outcome;
	}
}
