import { canShowReviewActions } from './detail-review-gate';

describe( 'detail-review-gate', () => {
	const allowed = [
		{ id: 'submit_for_review', allowed: true, reason_code: '' },
		{ id: 'approve', allowed: true, reason_code: '' },
		{ id: 'reject', allowed: true, reason_code: '' },
	];

	it( 'denies all review actions when dirty', () => {
		expect(
			canShowReviewActions( {
				dirty: true,
				sourceType: 'post',
				allowedActions: allowed,
				canTranslate: true,
				canReview: true,
			} )
		).toEqual( { submit: false, approve: false, reject: false } );
	} );

	it( 'denies all review actions for non-post sources', () => {
		expect(
			canShowReviewActions( {
				dirty: false,
				sourceType: 'term',
				allowedActions: allowed,
				canTranslate: true,
				canReview: true,
			} )
		).toEqual( { submit: false, approve: false, reject: false } );
	} );

	it( 'allows actions when clean, post-backed, and admitted', () => {
		expect(
			canShowReviewActions( {
				dirty: false,
				sourceType: 'post',
				allowedActions: allowed,
				canTranslate: true,
				canReview: true,
			} )
		).toEqual( { submit: true, approve: true, reject: true } );
	} );

	it( 'respects capability flags', () => {
		expect(
			canShowReviewActions( {
				dirty: false,
				sourceType: 'post',
				allowedActions: allowed,
				canTranslate: false,
				canReview: false,
			} )
		).toEqual( { submit: false, approve: false, reject: false } );
	} );
} );
