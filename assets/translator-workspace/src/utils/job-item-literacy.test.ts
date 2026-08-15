/**
 * Unit tests for job item literacy helpers (P2).
 */

import {
	attentionJobItems,
	jobItemNextActionHint,
	jobItemStatusLabel,
} from './job-item-literacy';

describe( 'job item literacy', () => {
	it( 'labels skipped_conflict without hiding protection', () => {
		const label = jobItemStatusLabel( 'skipped_conflict' );
		expect( label.toLowerCase() ).toContain( 'conflict' );
		expect( label ).not.toBe( 'skipped_conflict' );
	} );

	it( 'labels stale_source as source moved during job', () => {
		expect( jobItemStatusLabel( 'stale_source' ).toLowerCase() ).toContain(
			'source'
		);
	} );

	it( 'provides conflict next-action hint without silent overwrite', () => {
		const hint = jobItemNextActionHint( 'skipped_conflict' );
		expect( hint.toLowerCase() ).toContain( 'overwrite' );
		expect( hint.toLowerCase() ).not.toContain( 'force overwrite' );
	} );

	it( 'collects attention items including skips and stale', () => {
		const items = attentionJobItems( [
			{
				item_id: 1,
				job_id: 1,
				segment_key: 'a',
				status: 'completed',
				result_code: '',
				attempt_count: 1,
				last_error_code: '',
				last_error_class: '',
				last_error_message: '',
				created_at: '',
				updated_at: '',
				started_at: null,
				finished_at: null,
			},
			{
				item_id: 2,
				job_id: 1,
				segment_key: 'b',
				status: 'skipped_conflict',
				result_code: 'skipped_conflict',
				attempt_count: 1,
				last_error_code: 'skipped_conflict',
				last_error_class: '',
				last_error_message: 'conflict',
				created_at: '',
				updated_at: '',
				started_at: null,
				finished_at: null,
			},
			{
				item_id: 3,
				job_id: 1,
				segment_key: 'c',
				status: 'stale_source',
				result_code: 'stale_source',
				attempt_count: 1,
				last_error_code: 'stale_source',
				last_error_class: '',
				last_error_message: 'moved',
				created_at: '',
				updated_at: '',
				started_at: null,
				finished_at: null,
			},
		] );
		expect( items.map( ( item ) => item.item_id ) ).toEqual( [ 2, 3 ] );
	} );
} );
