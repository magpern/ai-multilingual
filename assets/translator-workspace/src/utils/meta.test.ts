import {
	aggregateQaSummary,
	qaFromSegment,
	suggestionsFromSegment,
} from './meta';
import type { WorkspaceSegment } from '../types/view-models';

function segment(
	overrides: Partial< WorkspaceSegment > = {}
): WorkspaceSegment {
	return {
		segment_key: 'b:test:content',
		field_key: 'content',
		block_name: 'core/paragraph',
		uuid: '550e8400-e29b-41d4-a716-446655440000',
		segment_order: 1,
		source_text: 'Hello',
		source_hash: 'abc',
		translated_text: 'Hej',
		status: 'manually_edited',
		is_stale: false,
		text_format: 'html',
		can_edit: true,
		meta: {},
		...overrides,
	};
}

describe( 'meta helpers', () => {
	it( 'reads suggestions from segment meta', () => {
		const list = suggestionsFromSegment(
			segment( {
				meta: {
					suggestions: [
						{
							provider_id: 'tm',
							target_text: 'Hej',
							confidence: 100,
							rank_tier: 1,
							metadata: { match_type: 'exact' },
						},
					],
				},
			} )
		);

		expect( list ).toHaveLength( 1 );
		expect( list[ 0 ].provider_id ).toBe( 'tm' );
	} );

	it( 'aggregates qa summaries across segments', () => {
		const summary = aggregateQaSummary( [
			segment( {
				meta: {
					qa: {
						issues: [],
						summary: { errors: 1, warnings: 2, info: 0 },
					},
				},
			} ),
			segment( {
				meta: {
					qa: {
						issues: [],
						summary: { errors: 0, warnings: 1, info: 3 },
					},
				},
			} ),
		] );

		expect( summary ).toEqual( {
			errors: 1,
			warnings: 3,
			info: 3,
		} );
	} );

	it( 'defaults qa when meta missing', () => {
		expect( qaFromSegment( segment() ).summary.errors ).toBe( 0 );
	} );
} );
