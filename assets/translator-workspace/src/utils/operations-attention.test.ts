import {
	ATTENTION_REASON_IDS,
	isAttentionPreset,
	parseAttentionFromUrl,
	sanitizeAttentionPreset,
} from './operations-attention';

describe( 'operations-attention', () => {
	it( 'never treats needs_review as a valid attention preset', () => {
		expect( isAttentionPreset( 'needs_review' ) ).toBe( false );
		expect( sanitizeAttentionPreset( 'needs_review' ) ).toBe( 'all' );
		expect( parseAttentionFromUrl( 'needs_review' ).invalidReserved ).toBe(
			true
		);
		expect( ATTENTION_REASON_IDS ).not.toContain( 'needs_review' );
	} );

	it( 'accepts frozen reason IDs', () => {
		expect( ATTENTION_REASON_IDS ).toEqual( [
			'stale',
			'review_pending',
			'review_rejected',
			'unpublished',
			'translation_failed',
		] );
		expect( sanitizeAttentionPreset( 'review_pending' ) ).toBe(
			'review_pending'
		);
	} );
} );
