import type { ReviewBatchResult } from '../types/segment-row';
import type { WorkspaceSegment } from '../types/view-models';
import {
	applyConflict,
	applyReloadFromServer,
	applyReviewBatchResults,
	applySaveSuccess,
	countDirtyRows,
	createRowsFromSegments,
	dirtyRowsInOrder,
	isRowDirty,
	mergeSegmentsIntoRows,
	updateDraftText,
} from './segment-rows';

function segment( overrides: Partial< WorkspaceSegment > = {} ): WorkspaceSegment {
	return {
		segment_key: 'b:test:content',
		field_key: 'content',
		block_name: 'core/paragraph',
		uuid: '550e8400-e29b-41d4-a716-446655440000',
		segment_order: 10,
		source_text: 'Hello',
		source_hash: 'abc123',
		translated_text: '',
		status: 'missing',
		is_stale: false,
		text_format: 'html',
		can_edit: true,
		meta: {},
		review_status: 'not_submitted',
		submitted_translation_hash: '',
		review_submitted_by: null,
		review_submitted_at: null,
		reviewed_by: null,
		reviewed_at: null,
		rejection_reason: '',
		rejected_by: null,
		rejected_at: null,
		...overrides,
	};
}

describe( 'segment-rows', () => {
	it( 'tracks dirty state when draft diverges from server', () => {
		const rows = createRowsFromSegments( [ segment() ] );
		expect( isRowDirty( rows[ 0 ] ) ).toBe( false );
		expect( countDirtyRows( rows ) ).toBe( 0 );

		const dirty = updateDraftText( rows, 'b:test:content', 'Hej' );
		expect( isRowDirty( dirty[ 0 ] ) ).toBe( true );
		expect( dirty[ 0 ].rowState ).toBe( 'dirty' );
		expect( countDirtyRows( dirty ) ).toBe( 1 );
	} );

	it( 'applies successful save and clears dirty state', () => {
		const rows = updateDraftText(
			createRowsFromSegments( [ segment() ] ),
			'b:test:content',
			'Hej'
		);
		const saved = applySaveSuccess(
			rows,
			'b:test:content',
			segment( { translated_text: 'Hej', status: 'manually_edited' } )
		);

		expect( saved[ 0 ].rowState ).toBe( 'saved' );
		expect( saved[ 0 ].draftText ).toBe( 'Hej' );
		expect( isRowDirty( saved[ 0 ] ) ).toBe( false );
	} );

	it( 'preserves unsaved text on conflict and exposes refreshed server row', () => {
		const rows = updateDraftText(
			createRowsFromSegments( [ segment() ] ),
			'b:test:content',
			'Hej'
		);
		const refreshed = segment( {
			source_text: 'Hello updated',
			source_hash: 'newhash',
		} );
		const conflicted = applyConflict(
			rows,
			'b:test:content',
			refreshed,
			'Hej',
			'Source changed'
		);

		expect( conflicted[ 0 ].rowState ).toBe( 'conflict' );
		expect( conflicted[ 0 ].draftText ).toBe( 'Hej' );
		expect( conflicted[ 0 ].conflictServer?.source_text ).toBe(
			'Hello updated'
		);
	} );

	it( 'reloads server source without discarding draft when requested', () => {
		const conflicted = applyConflict(
			updateDraftText(
				createRowsFromSegments( [ segment() ] ),
				'b:test:content',
				'Hej'
			),
			'b:test:content',
			segment( { source_text: 'Hello updated', source_hash: 'newhash' } ),
			'Hej',
			'Source changed'
		);
		const reloaded = applyReloadFromServer(
			conflicted,
			'b:test:content',
			conflicted[ 0 ].conflictServer as WorkspaceSegment,
			true
		);

		expect( reloaded[ 0 ].rowState ).toBe( 'dirty' );
		expect( reloaded[ 0 ].draftText ).toBe( 'Hej' );
		expect( reloaded[ 0 ].server.source_hash ).toBe( 'newhash' );
	} );

	it( 'orders dirty rows by segment_order for batch save', () => {
		const rows = createRowsFromSegments( [
			segment( { segment_key: 'b:two:content', segment_order: 20 } ),
			segment( { segment_key: 'b:one:content', segment_order: 10 } ),
		] );
		const dirty = dirtyRowsInOrder(
			updateDraftText(
				updateDraftText( rows, 'b:two:content', 'Two' ),
				'b:one:content',
				'One'
			)
		);

		expect( dirty.map( ( row ) => row.segmentKey ) ).toEqual( [
			'b:one:content',
			'b:two:content',
		] );
	} );

	it( 'keeps failed batch rows dirty while updating successful ones', () => {
		const rows = updateDraftText(
			createRowsFromSegments( [
				segment( { segment_key: 'b:one:content', segment_order: 10 } ),
				segment( {
					segment_key: 'b:two:content',
					segment_order: 20,
					translated_text: 'Old two',
				} ),
			] ),
			'b:one:content',
			'One'
		);
		const edited = updateDraftText( rows, 'b:two:content', 'Two' );
		const merged = mergeSegmentsIntoRows( edited, [
			segment( {
				segment_key: 'b:one:content',
				segment_order: 10,
				translated_text: 'One',
				status: 'manually_edited',
			} ),
		] );

		expect( merged[ 0 ].rowState ).toBe( 'clean' );
		expect( merged[ 1 ].rowState ).toBe( 'dirty' );
		expect( isRowDirty( merged[ 1 ] ) ).toBe( true );
	} );

	it( 'leaves read-only rows untouched by draft updates when not editable', () => {
		const rows = createRowsFromSegments( [
			segment( { can_edit: false, translated_text: 'Locked' } ),
		] );
		expect( rows[ 0 ].server.can_edit ).toBe( false );
	} );

	describe( 'applyReviewBatchResults', () => {
		it( 'applies successful review decisions and clears row errors', () => {
			const rows = createRowsFromSegments( [
				segment( { segment_key: 'b:one:content' } ),
			] );
			const result: ReviewBatchResult = {
				status: 'completed',
				updated: [
					segment( {
						segment_key: 'b:one:content',
						review_status: 'approved',
						reviewed_by: 4,
					} ),
				],
				errors: [],
			};

			const next = applyReviewBatchResults( rows, result );
			expect( next[ 0 ].server.review_status ).toBe( 'approved' );
			expect( next[ 0 ].rowState ).toBe( 'clean' );
		} );

		it( 'surfaces a per-item error without dropping other rows (no silent skip)', () => {
			const rows = createRowsFromSegments( [
				segment( { segment_key: 'b:one:content' } ),
				segment( { segment_key: 'b:two:content' } ),
			] );
			const result: ReviewBatchResult = {
				status: 'partial',
				updated: [
					segment( {
						segment_key: 'b:one:content',
						review_status: 'approved',
					} ),
				],
				errors: [
					{
						segment_key: 'b:two:content',
						code: 'aiml_review_conflict',
						message: 'The submitted translation changed.',
					},
				],
			};

			const next = applyReviewBatchResults( rows, result );
			expect( next[ 0 ].server.review_status ).toBe( 'approved' );
			expect( next[ 1 ].rowState ).toBe( 'error' );
			expect( next[ 1 ].errorMessage ).toBe(
				'The submitted translation changed.'
			);
		} );

		it( 'reattaches fresh QA context on an aiml_qa_blocked batch approval failure', () => {
			const rows = createRowsFromSegments( [
				segment( { segment_key: 'b:one:content' } ),
			] );
			const result: ReviewBatchResult = {
				status: 'partial',
				updated: [],
				errors: [
					{
						segment_key: 'b:one:content',
						code: 'aiml_qa_blocked',
						message: 'Translation failed quality checks.',
						qa: {
							issues: [
								{
									code: 'placeholder_mismatch',
									severity: 'error',
									message: 'Placeholder missing.',
									details: {},
								},
							],
							summary: { errors: 1, warnings: 0, info: 0 },
						},
					},
				],
			};

			const next = applyReviewBatchResults( rows, result );
			expect( next[ 0 ].rowState ).toBe( 'error' );
			expect( next[ 0 ].server.meta.qa ).toEqual( result.errors[ 0 ].qa );
		} );
	} );
} );
