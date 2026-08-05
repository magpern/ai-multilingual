import type { WorkspaceSegment } from '../types/view-models';
import {
	canDecideReview,
	canSubmitForReview,
	hasRejectionReason,
	normalizeRejectionReason,
	reviewStatusLabel,
	reviewerDisplayName,
} from './review-status';

function segment( overrides: Partial< WorkspaceSegment > = {} ): WorkspaceSegment {
	return {
		segment_key: 'b:test:content',
		field_key: 'content',
		block_name: 'core/paragraph',
		uuid: '550e8400-e29b-41d4-a716-446655440000',
		segment_order: 10,
		source_text: 'Hello',
		source_hash: 'abc123',
		translated_text: 'Hej',
		status: 'manually_edited',
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

describe( 'review-status', () => {
	it( 'maps review statuses to canonical labels', () => {
		expect( reviewStatusLabel( 'not_submitted' ) ).toBe( 'Not submitted' );
		expect( reviewStatusLabel( 'pending' ) ).toBe( 'Pending review' );
		expect( reviewStatusLabel( 'approved' ) ).toBe( 'Approved' );
		expect( reviewStatusLabel( 'rejected' ) ).toBe( 'Rejected' );
	} );

	describe( 'canSubmitForReview', () => {
		it( 'allows submit from not_submitted with editable, non-empty text', () => {
			expect( canSubmitForReview( segment() ) ).toBe( true );
		} );

		it( 'allows resubmit from rejected', () => {
			expect(
				canSubmitForReview( segment( { review_status: 'rejected' } ) )
			).toBe( true );
		} );

		it( 'blocks submit while pending', () => {
			expect(
				canSubmitForReview( segment( { review_status: 'pending' } ) )
			).toBe( false );
		} );

		it( 'blocks submit while approved (must be invalidated by an edit first)', () => {
			expect(
				canSubmitForReview( segment( { review_status: 'approved' } ) )
			).toBe( false );
		} );

		it( 'blocks submit for read-only segments', () => {
			expect(
				canSubmitForReview( segment( { can_edit: false } ) )
			).toBe( false );
		} );

		it( 'blocks submit for empty translated text', () => {
			expect(
				canSubmitForReview( segment( { translated_text: '   ' } ) )
			).toBe( false );
		} );

		it( 'blocks submit for missing/ignored/failed statuses', () => {
			expect(
				canSubmitForReview( segment( { status: 'missing' } ) )
			).toBe( false );
			expect(
				canSubmitForReview( segment( { status: 'ignored' } ) )
			).toBe( false );
			expect(
				canSubmitForReview( segment( { status: 'failed' } ) )
			).toBe( false );
		} );
	} );

	describe( 'canDecideReview', () => {
		it( 'allows approve/reject only from pending', () => {
			expect(
				canDecideReview( segment( { review_status: 'pending' } ) )
			).toBe( true );
			expect(
				canDecideReview( segment( { review_status: 'approved' } ) )
			).toBe( false );
			expect(
				canDecideReview( segment( { review_status: 'rejected' } ) )
			).toBe( false );
			expect(
				canDecideReview( segment( { review_status: 'not_submitted' } ) )
			).toBe( false );
		} );
	} );

	describe( 'hasRejectionReason', () => {
		it( 'is true only for rejected segments with a non-empty reason', () => {
			expect(
				hasRejectionReason(
					segment( {
						review_status: 'rejected',
						rejection_reason: 'Needs glossary terms',
					} )
				)
			).toBe( true );
			expect(
				hasRejectionReason(
					segment( { review_status: 'rejected', rejection_reason: '' } )
				)
			).toBe( false );
			expect(
				hasRejectionReason(
					segment( {
						review_status: 'approved',
						rejection_reason: 'stale reason',
					} )
				)
			).toBe( false );
		} );
	} );

	describe( 'normalizeRejectionReason', () => {
		it( 'trims and accepts a reason within 1–512 characters', () => {
			expect( normalizeRejectionReason( '  Needs work  ' ) ).toEqual( {
				value: 'Needs work',
				valid: true,
			} );
		} );

		it( 'rejects an empty or whitespace-only reason', () => {
			expect( normalizeRejectionReason( '' ).valid ).toBe( false );
			expect( normalizeRejectionReason( '   ' ).valid ).toBe( false );
		} );

		it( 'rejects a reason longer than 512 characters', () => {
			const tooLong = 'a'.repeat( 513 );
			expect( normalizeRejectionReason( tooLong ).valid ).toBe( false );
		} );

		it( 'accepts exactly 512 characters', () => {
			const maxLength = 'a'.repeat( 512 );
			expect( normalizeRejectionReason( maxLength ).valid ).toBe( true );
		} );
	} );

	describe( 'reviewerDisplayName', () => {
		it( 'formats a known user id', () => {
			expect( reviewerDisplayName( 7 ) ).toBe( '#7' );
		} );

		it( 'falls back for null/invalid ids', () => {
			expect( reviewerDisplayName( null ) ).toBe( '(unknown)' );
			expect( reviewerDisplayName( 0 ) ).toBe( '(unknown)' );
		} );
	} );
} );
