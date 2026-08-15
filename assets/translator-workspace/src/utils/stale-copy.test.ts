/**
 * Unit tests for state-accurate stale operator copy (P2 A2).
 */

import {
	publishStatusFromSegment,
	staleOperatorCopy,
} from './stale-copy';

describe( 'staleOperatorCopy', () => {
	it( 'returns null when not stale', () => {
		expect(
			staleOperatorCopy( {
				isStale: false,
				publishStatus: 'published',
			} )
		).toBeNull();
	} );

	it( 'explains published + stale without absolute source-fallback claims', () => {
		const copy = staleOperatorCopy( {
			isStale: true,
			publishStatus: 'published',
		} );
		expect( copy ).not.toBeNull();
		expect( copy!.badge.toLowerCase() ).toContain( 'published' );
		expect( copy!.notice.toLowerCase() ).toContain( 'remains published' );
		expect( copy!.notice.toLowerCase() ).toContain( 'eligibility' );
		expect( copy!.notice.toLowerCase() ).not.toContain( 'shows source' );
	} );

	it( 'explains unpublished + stale distinctly', () => {
		const copy = staleOperatorCopy( {
			isStale: true,
			publishStatus: 'unpublished',
		} );
		expect( copy ).not.toBeNull();
		expect( copy!.badge.toLowerCase() ).toContain( 'not published' );
		expect( copy!.notice.toLowerCase() ).toContain( 'before publishing' );
	} );

	it( 'uses neutral copy when publish_status is unknown', () => {
		const copy = staleOperatorCopy( {
			isStale: true,
			publishStatus: '',
		} );
		expect( copy ).not.toBeNull();
		expect( copy!.notice.toLowerCase() ).toContain( 'check publication' );
	} );

	it( 'reads publish_status from segment meta', () => {
		expect(
			publishStatusFromSegment( {
				meta: { publish_status: 'published' },
			} )
		).toBe( 'published' );
	} );
} );
