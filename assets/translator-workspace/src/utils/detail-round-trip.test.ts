import {
	identityRoundTrip,
	isRawRoundTripEqual,
	ROUND_TRIP_FIXTURES,
} from './detail-round-trip';

describe( 'detail-round-trip', () => {
	it( 'preserves fixtures through identity round-trip', () => {
		for ( const fixture of ROUND_TRIP_FIXTURES ) {
			expect( identityRoundTrip( fixture ) ).toBe( fixture );
		}
	} );

	it( 'compares draft and persisted without normalization', () => {
		expect( isRawRoundTripEqual( '  spaced  ', '  spaced  ' ) ).toBe( true );
		expect( isRawRoundTripEqual( 'a', 'b' ) ).toBe( false );
		expect(
			isRawRoundTripEqual(
				ROUND_TRIP_FIXTURES[ 3 ],
				ROUND_TRIP_FIXTURES[ 3 ]
			)
		).toBe( true );
	} );
} );
