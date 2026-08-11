import {
	clearOperationsViewFromUrl,
	readOperationsUrlState,
	writeOperationsUrlState,
} from './operations-url';

describe( 'operations-url translation_id', () => {
	it( 'reads translation_id from query string', () => {
		const state = readOperationsUrlState(
			'?page=aiml-translator&view=operations&language=sv&translation_id=42'
		);
		expect( state.translationId ).toBe( 42 );
	} );

	it( 'returns null for missing or invalid translation_id', () => {
		expect(
			readOperationsUrlState( '?view=operations' ).translationId
		).toBeNull();
		expect(
			readOperationsUrlState( '?translation_id=0' ).translationId
		).toBeNull();
		expect(
			readOperationsUrlState( '?translation_id=abc' ).translationId
		).toBeNull();
	} );

	it( 'writes translation_id when inspector is open', () => {
		const replaceState = jest.fn();
		Object.defineProperty( window, 'history', {
			value: { replaceState },
			writable: true,
		} );
		Object.defineProperty( window, 'location', {
			value: {
				pathname: '/wp-admin/admin.php',
				search: '?page=aiml-translator&view=operations&language=sv',
			},
			writable: true,
		} );

		writeOperationsUrlState( {
			language: 'sv',
			attention: 'all',
			pageNum: 1,
			translationId: 99,
		} );

		expect( replaceState ).toHaveBeenCalled();
		const url = String( replaceState.mock.calls[ 0 ][ 2 ] );
		expect( url ).toContain( 'translation_id=99' );
	} );

	it( 'clearOperationsViewFromUrl removes translation_id', () => {
		const replaceState = jest.fn();
		Object.defineProperty( window, 'history', {
			value: { replaceState },
			writable: true,
		} );
		Object.defineProperty( window, 'location', {
			value: {
				pathname: '/wp-admin/admin.php',
				search:
					'?page=aiml-translator&view=operations&translation_id=5&attention=stale',
			},
			writable: true,
		} );

		clearOperationsViewFromUrl();

		expect( replaceState ).toHaveBeenCalled();
		const url = String( replaceState.mock.calls[ 0 ][ 2 ] );
		expect( url ).not.toContain( 'translation_id=' );
		expect( url ).not.toContain( 'view=operations' );
	} );
} );

describe( 'operations-url basics', () => {
	it( 'reads view=operations and page_num', () => {
		const state = readOperationsUrlState(
			'?page=aiml-translator&view=operations&language=sv&attention=stale&page_num=3'
		);
		expect( state.view ).toBe( 'operations' );
		expect( state.language ).toBe( 'sv' );
		expect( state.attention ).toBe( 'stale' );
		expect( state.pageNum ).toBe( 3 );
	} );

	it( 'flags reserved needs_review from URL without applying it', () => {
		const state = readOperationsUrlState(
			'?view=operations&attention=needs_review'
		);
		expect( state.attention ).toBe( 'all' );
		expect( state.invalidReservedAttention ).toBe( true );
	} );
} );
