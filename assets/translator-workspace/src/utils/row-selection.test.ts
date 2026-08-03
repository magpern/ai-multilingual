import {
	allVisibleSelected,
	clearSelection,
	isRowSelectable,
	selectAllVisible,
	selectedDirtyRows,
	selectedEditableRows,
	toggleSelection,
} from './row-selection';
import type { SegmentRow } from '../types/segment-row';

function row( overrides: Partial< SegmentRow > = {} ): SegmentRow {
	const server = {
		segment_key: 'b:test:content',
		field_key: 'content',
		block_name: 'core/paragraph',
		uuid: '550e8400-e29b-41d4-a716-446655440000',
		segment_order: 10,
		source_text: 'Hello',
		source_hash: 'abc',
		translated_text: '',
		status: 'missing',
		is_stale: false,
		text_format: 'html',
		can_edit: true,
		meta: {},
		...( overrides.server ?? {} ),
	};

	return {
		segmentKey: overrides.segmentKey ?? server.segment_key,
		server,
		draftText: overrides.draftText ?? '',
		rowState: overrides.rowState ?? 'clean',
		...overrides,
		segmentKey: overrides.segmentKey ?? server.segment_key,
		server,
	};
}

describe( 'row-selection', () => {
	it( 'excludes read-only rows from selectable sets', () => {
		const rows = [
			row(),
			row( {
				segmentKey: 'b:locked:content',
				server: { can_edit: false, segment_key: 'b:locked:content' },
			} ),
		];

		expect( rows.filter( isRowSelectable ) ).toHaveLength( 1 );
	} );

	it( 'selects all visible editable rows without dropping hidden selections', () => {
		const visible = [
			row( { segmentKey: 'b:one:content', server: { segment_key: 'b:one:content' } } ),
			row( {
				segmentKey: 'b:locked:content',
				server: { can_edit: false, segment_key: 'b:locked:content' },
			} ),
		];
		const selected = selectAllVisible(
			new Set( [ 'b:hidden:content' ] ),
			visible
		);

		expect( selected.has( 'b:hidden:content' ) ).toBe( true );
		expect( selected.has( 'b:one:content' ) ).toBe( true );
		expect( selected.has( 'b:locked:content' ) ).toBe( false );
	} );

	it( 'returns only dirty rows among the current selection', () => {
		const rows = [
			row( {
				segmentKey: 'b:dirty:content',
				server: { segment_key: 'b:dirty:content', translated_text: '' },
				draftText: 'Hej',
				rowState: 'dirty',
			} ),
			row( {
				segmentKey: 'b:clean:content',
				server: { segment_key: 'b:clean:content', translated_text: 'Hej' },
				draftText: 'Hej',
			} ),
		];

		expect(
			selectedDirtyRows( rows, new Set( [ 'b:dirty:content', 'b:clean:content' ] ) )
		).toHaveLength( 1 );
	} );

	it( 'toggles individual row selection', () => {
		const selected = toggleSelection( new Set(), 'b:test:content', true );
		expect( selected.has( 'b:test:content' ) ).toBe( true );
		expect(
			toggleSelection( selected, 'b:test:content', false ).has( 'b:test:content' )
		).toBe( false );
	} );

	it( 'clears all selections', () => {
		expect( clearSelection().size ).toBe( 0 );
	} );

	it( 'detects when all visible editable rows are selected', () => {
		const visible = [
			row( { segmentKey: 'b:one:content', server: { segment_key: 'b:one:content' } } ),
		];
		expect( allVisibleSelected( visible, new Set( [ 'b:one:content' ] ) ) ).toBe(
			true
		);
		expect( allVisibleSelected( visible, new Set() ) ).toBe( false );
	} );
} );
