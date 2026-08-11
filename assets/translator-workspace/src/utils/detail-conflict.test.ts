import {
	detailConflictMessage,
	detailConflictStatusMessage,
} from './detail-conflict';

describe( 'detail-conflict', () => {
	it( 'maps source conflicts to source copy', () => {
		expect( detailConflictMessage( 'source' ) ).toMatch( /source text/i );
		expect( detailConflictStatusMessage( 'source' ) ).toMatch( /source text/i );
	} );

	it( 'maps translation conflicts to translation copy', () => {
		expect( detailConflictMessage( 'translation' ) ).toMatch(
			/saved translation/i
		);
		expect( detailConflictStatusMessage( 'translation' ) ).toMatch(
			/saved translation/i
		);
	} );
} );
