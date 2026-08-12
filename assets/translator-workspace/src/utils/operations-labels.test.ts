/**
 * Unit tests for Operations presentation labels (OTL.6).
 */

import { bulkOutcomeLabel, publishStatusLabel } from './operations-labels';

describe( 'operations-labels', () => {
	it( 'humanizes publish_status', () => {
		expect( publishStatusLabel( 'published' ) ).toBeTruthy();
		expect( publishStatusLabel( 'unpublished' ) ).toBeTruthy();
		expect( publishStatusLabel( 'published' ) ).not.toBe( 'published' );
	} );

	it( 'humanizes enqueued without claiming translated', () => {
		const label = bulkOutcomeLabel( 'enqueued' );
		expect( label.toLowerCase() ).toContain( 'enqueued' );
		expect( label.toLowerCase() ).not.toContain( 'translated' );
		expect( label.toLowerCase() ).not.toContain( 'completed' );
	} );

	it( 'does not invent Eligible/Ready/Publishable labels', () => {
		expect( publishStatusLabel( 'unpublished' ).toLowerCase() ).not.toContain(
			'eligible'
		);
		expect( publishStatusLabel( 'unpublished' ).toLowerCase() ).not.toContain(
			'ready'
		);
		expect( publishStatusLabel( 'unpublished' ).toLowerCase() ).not.toContain(
			'publishable'
		);
	} );
} );
