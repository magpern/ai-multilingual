import {
	dirtyEditorHint,
	dirtyEvidenceHonestyMessage,
	dirtyLeaveConfirmMessage,
	isDetailDirty,
} from './detail-dirty';

describe( 'detail-dirty', () => {
	it( 'detects dirty when draft differs from persisted', () => {
		expect( isDetailDirty( 'draft', 'saved' ) ).toBe( true );
		expect( isDetailDirty( 'same', 'same' ) ).toBe( false );
		expect( isDetailDirty( '', undefined ) ).toBe( false );
		expect( isDetailDirty( 'x', null ) ).toBe( true );
	} );

	it( 'returns honesty copy for evidence banners', () => {
		expect( dirtyEvidenceHonestyMessage() ).toMatch( /last saved/i );
		expect( dirtyEditorHint() ).toMatch( /unsaved/i );
		expect( dirtyLeaveConfirmMessage() ).toMatch( /unsaved/i );
	} );
} );
