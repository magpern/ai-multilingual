/**
 * URL sync helpers for Operations tab (OTL.1).
 */

import {
	sanitizeAttentionPreset,
	type AttentionPreset,
} from './operations-attention';

export interface OperationsUrlState {
	view: 'operations' | null;
	language: string;
	attention: AttentionPreset;
	pageNum: number;
	status: string;
	reviewStatus: string;
	publishStatus: string;
	isStale: string;
	sourceType: string;
	sourceId: string;
}

export function readOperationsUrlState(
	search: string = typeof window !== 'undefined' ? window.location.search : ''
): OperationsUrlState {
	const params = new URLSearchParams( search );
	const pageRaw = Number( params.get( 'page_num' ) ?? '1' );
	return {
		view: 'operations' === params.get( 'view' ) ? 'operations' : null,
		language: ( params.get( 'language' ) ?? '' ).trim(),
		attention: sanitizeAttentionPreset( params.get( 'attention' ) ),
		pageNum: Number.isFinite( pageRaw ) && pageRaw > 0 ? Math.floor( pageRaw ) : 1,
		status: ( params.get( 'status' ) ?? '' ).trim(),
		reviewStatus: ( params.get( 'review_status' ) ?? '' ).trim(),
		publishStatus: ( params.get( 'publish_status' ) ?? '' ).trim(),
		isStale: ( params.get( 'is_stale' ) ?? '' ).trim(),
		sourceType: ( params.get( 'source_type' ) ?? '' ).trim(),
		sourceId: ( params.get( 'source_id' ) ?? '' ).trim(),
	};
}

export function writeOperationsUrlState( state: {
	language: string;
	attention: AttentionPreset;
	pageNum: number;
	status?: string;
	reviewStatus?: string;
	publishStatus?: string;
	isStale?: string;
	sourceType?: string;
	sourceId?: string;
} ): void {
	if ( typeof window === 'undefined' ) {
		return;
	}
	const params = new URLSearchParams( window.location.search );
	params.set( 'page', 'aiml-translator' );
	params.set( 'view', 'operations' );
	if ( state.language ) {
		params.set( 'language', state.language );
	} else {
		params.delete( 'language' );
	}
	if ( state.attention && 'all' !== state.attention ) {
		params.set( 'attention', state.attention );
	} else {
		params.delete( 'attention' );
	}
	if ( state.pageNum > 1 ) {
		params.set( 'page_num', String( state.pageNum ) );
	} else {
		params.delete( 'page_num' );
	}

	const optional: Array< [ string, string | undefined ] > = [
		[ 'status', state.status ],
		[ 'review_status', state.reviewStatus ],
		[ 'publish_status', state.publishStatus ],
		[ 'is_stale', state.isStale ],
		[ 'source_type', state.sourceType ],
		[ 'source_id', state.sourceId ],
	];
	for ( const [ key, value ] of optional ) {
		if ( value ) {
			params.set( key, value );
		} else {
			params.delete( key );
		}
	}

	const next = `${ window.location.pathname }?${ params.toString() }`;
	window.history.replaceState( {}, '', next );
}

export function clearOperationsViewFromUrl(): void {
	if ( typeof window === 'undefined' ) {
		return;
	}
	const params = new URLSearchParams( window.location.search );
	params.delete( 'view' );
	params.delete( 'attention' );
	params.delete( 'page_num' );
	params.delete( 'status' );
	params.delete( 'review_status' );
	params.delete( 'publish_status' );
	params.delete( 'is_stale' );
	params.delete( 'source_type' );
	params.delete( 'source_id' );
	const qs = params.toString();
	window.history.replaceState(
		{},
		'',
		qs ? `${ window.location.pathname }?${ qs }` : window.location.pathname
	);
}
