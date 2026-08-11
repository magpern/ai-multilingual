import { canShowPublicationActions } from './detail-publication-gate';

describe( 'detail-publication-gate', () => {
	const allowed = [
		{ id: 'publish', allowed: true, reason_code: '' },
		{ id: 'unpublish', allowed: true, reason_code: '' },
		{ id: 'retranslate_stale', allowed: true, reason_code: '' },
	];

	it( 'denies all publication actions when dirty', () => {
		expect(
			canShowPublicationActions( {
				dirty: true,
				sourceType: 'post',
				allowedActions: allowed,
			} )
		).toEqual( { publish: false, unpublish: false, retranslate: false } );
	} );

	it( 'denies all publication actions for non-post sources', () => {
		expect(
			canShowPublicationActions( {
				dirty: false,
				sourceType: 'term',
				allowedActions: allowed,
			} )
		).toEqual( { publish: false, unpublish: false, retranslate: false } );
	} );

	it( 'allows actions when clean, post-backed, and admitted', () => {
		expect(
			canShowPublicationActions( {
				dirty: false,
				sourceType: 'post',
				allowedActions: allowed,
			} )
		).toEqual( { publish: true, unpublish: true, retranslate: true } );
	} );

	it( 'respects individual admission flags', () => {
		expect(
			canShowPublicationActions( {
				dirty: false,
				sourceType: 'post',
				allowedActions: [
					{ id: 'publish', allowed: true, reason_code: '' },
					{ id: 'unpublish', allowed: false, reason_code: 'not_published' },
					{ id: 'retranslate_stale', allowed: false, reason_code: 'not_stale' },
				],
			} )
		).toEqual( { publish: true, unpublish: false, retranslate: false } );
	} );
} );
