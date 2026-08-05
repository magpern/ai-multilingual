import {
	matchesSegmentFilter,
	overallStateLabel,
	segmentStatusLabel,
} from './segment-status';
import type { WorkspaceSegment } from '../types/view-models';

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

describe( 'segment-status', () => {
	it( 'maps store statuses to canonical labels', () => {
		expect( segmentStatusLabel( 'missing' ) ).toBe( 'Missing' );
		expect( segmentStatusLabel( 'machine_translated' ) ).toBe(
			'Machine translated'
		);
		expect( segmentStatusLabel( 'manually_edited' ) ).toBe( 'Edited' );
		expect( segmentStatusLabel( 'reviewed' ) ).toBe( 'Reviewed' );
	} );

	it( 'filters missing segments only', () => {
		expect( matchesSegmentFilter( segment(), 'missing' ) ).toBe( true );
		expect(
			matchesSegmentFilter(
				segment( { status: 'manually_edited', translated_text: 'Hej' } ),
				'missing'
			)
		).toBe( false );
	} );

	it( 'filters stale segments only', () => {
		expect(
			matchesSegmentFilter( segment( { is_stale: true } ), 'stale' )
		).toBe( true );
		expect( matchesSegmentFilter( segment(), 'stale' ) ).toBe( false );
	} );

	it( 'filters translated segments excluding reviewed and missing', () => {
		expect(
			matchesSegmentFilter(
				segment( { status: 'manually_edited', translated_text: 'Hej' } ),
				'translated'
			)
		).toBe( true );
		expect(
			matchesSegmentFilter( segment( { status: 'reviewed' } ), 'translated' )
		).toBe( false );
		expect( matchesSegmentFilter( segment(), 'translated' ) ).toBe( false );
	} );

	it( 'filters reviewed segments only', () => {
		expect(
			matchesSegmentFilter( segment( { status: 'reviewed' } ), 'reviewed' )
		).toBe( true );
		expect(
			matchesSegmentFilter(
				segment( { status: 'manually_edited', translated_text: 'Hej' } ),
				'reviewed'
			)
		).toBe( false );
	} );

	it( 'maps overall page states to readable labels', () => {
		expect( overallStateLabel( 'not_started' ) ).toBe( 'Not started' );
		expect( overallStateLabel( 'in_progress' ) ).toBe( 'In progress' );
		expect( overallStateLabel( 'complete' ) ).toBe( 'Complete' );
		expect( overallStateLabel( 'stale' ) ).toBe( 'Stale' );
	} );
} );
