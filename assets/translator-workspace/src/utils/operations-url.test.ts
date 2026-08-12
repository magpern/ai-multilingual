/**
 * Unit tests for Operations URL clear including language (OTL.6 A2).
 */

import {
	clearOperationsViewFromUrl,
	readOperationsUrlState,
} from './operations-url';

describe( 'operations-url clear', () => {
	const original = window.location;

	beforeEach( () => {
		// jsdom location is limited; use history API with a full search string.
		window.history.replaceState(
			{},
			'',
			'/wp-admin/admin.php?page=aiml-translator&view=operations&language=sv&attention=needs_review&page_num=2&translation_id=9&status=translated'
		);
	} );

	afterAll( () => {
		window.history.replaceState( {}, '', original.pathname + original.search );
	} );

	it( 'clears Ops keys including language', () => {
		clearOperationsViewFromUrl();
		const params = new URLSearchParams( window.location.search );
		expect( params.get( 'view' ) ).toBeNull();
		expect( params.get( 'language' ) ).toBeNull();
		expect( params.get( 'attention' ) ).toBeNull();
		expect( params.get( 'page_num' ) ).toBeNull();
		expect( params.get( 'translation_id' ) ).toBeNull();
		expect( params.get( 'status' ) ).toBeNull();
		expect( params.get( 'page' ) ).toBe( 'aiml-translator' );
		const state = readOperationsUrlState( window.location.search );
		expect( state.view ).toBeNull();
		expect( state.language ).toBe( '' );
	} );
} );
