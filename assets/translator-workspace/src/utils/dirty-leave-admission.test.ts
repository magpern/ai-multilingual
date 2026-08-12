import { canOpenConfirmDialog } from './dirty-leave-admission';

describe( 'dirty-leave-admission', () => {
	it( 'allows confirm when none pending', () => {
		expect( canOpenConfirmDialog( false ) ).toBe( true );
	} );

	it( 'blocks concurrent confirm while dialog pending', () => {
		expect( canOpenConfirmDialog( true ) ).toBe( false );
	} );
} );
