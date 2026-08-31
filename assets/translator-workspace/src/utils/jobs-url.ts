/**
 * URL sync helpers for Jobs tab deep links (OTL.4).
 * Shape: view=jobs&job_id=&item_id=
 */

export interface JobsUrlState {
	view: 'jobs' | null;
	jobId: number | null;
	itemId: number | null;
	batchId: string | null;
}

function parsePositiveInt( raw: string | null ): number | null {
	const value = Number( raw ?? '0' );
	return Number.isFinite( value ) && value > 0 ? Math.floor( value ) : null;
}

export function readJobsUrlState(
	search: string = typeof window !== 'undefined' ? window.location.search : ''
): JobsUrlState {
	const params = new URLSearchParams( search );
	return {
		view: 'jobs' === params.get( 'view' ) ? 'jobs' : null,
		jobId: parsePositiveInt( params.get( 'job_id' ) ),
		itemId: parsePositiveInt( params.get( 'item_id' ) ),
		batchId: params.get( 'batch_id' ) || null,
	};
}

export function writeJobsUrlState( state: {
	jobId?: number | null;
	itemId?: number | null;
	batchId?: string | null;
} ): void {
	if ( typeof window === 'undefined' ) {
		return;
	}

	const params = new URLSearchParams( window.location.search );
	params.set( 'page', 'aiml-translator' );
	params.set( 'view', 'jobs' );

	if ( state.jobId && state.jobId > 0 ) {
		params.set( 'job_id', String( state.jobId ) );
	} else {
		params.delete( 'job_id' );
	}

	if ( state.itemId && state.itemId > 0 ) {
		params.set( 'item_id', String( state.itemId ) );
	} else {
		params.delete( 'item_id' );
	}

	if ( state.batchId && state.batchId.trim() ) {
		params.set( 'batch_id', state.batchId.trim() );
	} else {
		params.delete( 'batch_id' );
	}

	const next = `${ window.location.pathname }?${ params.toString() }`;
	window.history.replaceState( {}, '', next );
}

export function clearJobsViewFromUrl(): void {
	if ( typeof window === 'undefined' ) {
		return;
	}

	const params = new URLSearchParams( window.location.search );
	if ( 'jobs' === params.get( 'view' ) ) {
		params.delete( 'view' );
	}
	params.delete( 'job_id' );
	params.delete( 'item_id' );
	params.delete( 'batch_id' );
	const qs = params.toString();
	window.history.replaceState(
		{},
		'',
		qs ? `${ window.location.pathname }?${ qs }` : window.location.pathname
	);
}
