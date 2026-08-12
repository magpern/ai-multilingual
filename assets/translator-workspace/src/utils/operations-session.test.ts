/**
 * Unit tests for Operations session snapshot (OTL.6 A2).
 */

import {
	clearOperationsSession,
	peekOperationsSession,
	stashOperationsSession,
} from './operations-session';

describe( 'operations-session', () => {
	afterEach( () => {
		clearOperationsSession();
	} );

	it( 'stashes and peeks a copy of nav state', () => {
		expect( peekOperationsSession() ).toBeNull();
		stashOperationsSession( {
			language: 'sv',
			attention: 'needs_review',
			pageNum: 2,
			status: '',
			reviewStatus: '',
			publishStatus: 'unpublished',
			isStale: '1',
			sourceType: '',
			sourceId: '',
			translationId: 42,
		} );
		const peek = peekOperationsSession();
		expect( peek?.language ).toBe( 'sv' );
		expect( peek?.translationId ).toBe( 42 );
		expect( peek?.pageNum ).toBe( 2 );
	} );

	it( 'clears session', () => {
		stashOperationsSession( {
			language: 'sv',
			attention: 'all',
			pageNum: 1,
			status: '',
			reviewStatus: '',
			publishStatus: '',
			isStale: '',
			sourceType: '',
			sourceId: '',
			translationId: null,
		} );
		clearOperationsSession();
		expect( peekOperationsSession() ).toBeNull();
	} );
} );
