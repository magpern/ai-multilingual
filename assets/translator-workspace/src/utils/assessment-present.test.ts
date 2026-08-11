import {
	assessmentCategoryLabelEnglish,
	assessmentFindingReasons,
	isTi5NeedsReviewCategory,
} from './assessment-present';

describe( 'assessment-present', () => {
	it( 'labels TI.5 needs_review distinctly from ADR pending', () => {
		expect( isTi5NeedsReviewCategory( 'needs_review' ) ).toBe( true );
		expect( isTi5NeedsReviewCategory( 'pending' ) ).toBe( false );
		expect( assessmentCategoryLabelEnglish( 'needs_review' ) ).toContain(
			'TI.5'
		);
		expect( assessmentCategoryLabelEnglish( 'pending' ) ).toBe( 'pending' );
	} );

	it( 'extracts finding reasons from assessment payload', () => {
		const reasons = assessmentFindingReasons( {
			errors: [
				{ code: 'qa_error', message: 'Missing glossary term' },
			],
			warnings: [ { finding_code: 'soft_warn', summary: 'Check tone' } ],
		} );
		expect( reasons ).toEqual( [
			'qa_error: Missing glossary term',
			'soft_warn: Check tone',
		] );
	} );
} );
