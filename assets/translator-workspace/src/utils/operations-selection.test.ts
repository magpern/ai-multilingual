/**
 * Unit tests for Operations selection helpers (OTL.5).
 */

import {
	OPERATIONS_SELECTION_LIMIT,
	allVisibleSelected,
	applyBulkResultToSelection,
	canAttemptPublish,
	clearSelection,
	deselectAllVisible,
	dirtyBlocksBulk,
	selectAllVisible,
	toggleSelection,
} from './operations-selection';

describe( 'operations-selection', () => {
	it( 'toggles translation_id selection', () => {
		let selected = clearSelection();
		selected = toggleSelection( selected, 11, true );
		expect( selected.has( 11 ) ).toBe( true );
		selected = toggleSelection( selected, 11, false );
		expect( selected.has( 11 ) ).toBe( false );
	} );

	it( 'enforces hard cap of 50', () => {
		let selected = clearSelection();
		for ( let i = 1; i <= OPERATIONS_SELECTION_LIMIT; i++ ) {
			selected = toggleSelection( selected, i, true );
		}
		expect( selected.size ).toBe( 50 );
		selected = toggleSelection( selected, 999, true );
		expect( selected.has( 999 ) ).toBe( false );
		expect( selected.size ).toBe( 50 );
	} );

	it( 'select-all is current-page scoped and respects cap', () => {
		let selected = clearSelection();
		selected = selectAllVisible( selected, [ 1, 2, 3 ] );
		expect( allVisibleSelected( selected, [ 1, 2, 3 ] ) ).toBe( true );
		selected = deselectAllVisible( selected, [ 2 ] );
		expect( selected.has( 1 ) ).toBe( true );
		expect( selected.has( 2 ) ).toBe( false );
	} );

	it( 'A3 removes terminal outcomes and retains actionable failures', () => {
		let selected = new Set( [ 1, 2, 3, 4, 5 ] );
		selected = applyBulkResultToSelection( selected, [
			{ translation_id: 1, outcome: 'published' },
			{ translation_id: 2, outcome: 'noop' },
			{ translation_id: 3, outcome: 'enqueued' },
			{ translation_id: 4, outcome: 'blocked' },
			{ translation_id: 5, outcome: 'skipped' },
		] );
		expect( [ ...selected ].sort() ).toEqual( [ 4, 5 ] );
	} );

	it( 'A6 dirty intersection blocks only when D is selected', () => {
		expect( dirtyBlocksBulk( true, 5, new Set( [ 5, 6 ] ) ) ).toBe( true );
		expect( dirtyBlocksBulk( true, 5, new Set( [ 6 ] ) ) ).toBe( false );
		expect( dirtyBlocksBulk( false, 5, new Set( [ 5 ] ) ) ).toBe( false );
	} );

	it( 'publish attemptability is invitation-only', () => {
		expect( canAttemptPublish( { selectedCount: 2, canTranslate: true } ) ).toBe(
			true
		);
		expect( canAttemptPublish( { selectedCount: 0, canTranslate: true } ) ).toBe(
			false
		);
	} );
} );
