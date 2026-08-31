import {
	blockedReasonLabel,
	chunkCountForSelection,
	coverageSummaryLabel,
	matchesCoverageFilter,
	routeOutcomeLabel,
} from './site-translate';

describe( 'site-translate utils', () => {
	it( 'labels zero eligible as no extractable work', () => {
		expect(
			coverageSummaryLabel( {
				eligible_total: 0,
				missing: 0,
				translated: 0,
				unpublished: 0,
				published: 0,
				stale: 0,
				blocked_or_unsupported: [ 'zero_eligible' ],
				translation_complete: false,
				no_extractable_work: true,
			} )
		).toBe( 'No extractable work' );
	} );

	it( 'matches needs_translation when missing or stale', () => {
		const coverage = {
			eligible_total: 3,
			missing: 1,
			translated: 2,
			unpublished: 2,
			published: 0,
			stale: 0,
			blocked_or_unsupported: [],
			translation_complete: false,
			no_extractable_work: false,
		};
		expect( matchesCoverageFilter( coverage, 'needs_translation' ) ).toBe(
			true
		);
		expect(
			matchesCoverageFilter( coverage, 'translation_complete' )
		).toBe( false );
	} );

	it( 'counts chunks at 50 posts per bulk', () => {
		expect( chunkCountForSelection( 50 ) ).toBe( 1 );
		expect( chunkCountForSelection( 51 ) ).toBe( 2 );
	} );

	it( 'renders blocked and route outcome labels', () => {
		expect( blockedReasonLabel( 'body_elementor' ) ).toContain( 'Elementor' );
		expect( routeOutcomeLabel( 'title_stale' ) ).toContain( 'stale' );
	} );
} );
