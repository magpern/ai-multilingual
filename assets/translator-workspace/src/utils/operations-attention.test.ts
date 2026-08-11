import {
	ATTENTION_REASON_IDS,
	isAttentionPreset,
	sanitizeAttentionPreset,
} from './operations-attention';
import { readOperationsUrlState } from './operations-url';

describe( 'operations-attention', () => {
	it( 'never treats needs_review as a valid attention preset', () => {
		expect( isAttentionPreset( 'needs_review' ) ).toBe( false );
		expect( sanitizeAttentionPreset( 'needs_review' ) ).toBe( 'all' );
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

describe( 'operations-url', () => {
	it( 'reads view=operations and page_num', () => {
		const state = readOperationsUrlState(
			'?page=aiml-translator&view=operations&language=sv&attention=stale&page_num=3'
		);
		expect( state.view ).toBe( 'operations' );
		expect( state.language ).toBe( 'sv' );
		expect( state.attention ).toBe( 'stale' );
		expect( state.pageNum ).toBe( 3 );
	} );

	it( 'falls back invalid attention including needs_review', () => {
		const state = readOperationsUrlState(
			'?view=operations&attention=needs_review'
		);
		expect( state.attention ).toBe( 'all' );
	} );
} );
