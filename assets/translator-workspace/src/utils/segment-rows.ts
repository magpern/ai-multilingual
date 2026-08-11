import type { BatchSaveResult, ReviewBatchResult } from '../types/segment-row';
import type { SegmentRow, SegmentRowState } from '../types/segment-row';
import type { WorkspaceSegment } from '../types/view-models';

export function createRowsFromSegments(
	segments: WorkspaceSegment[]
): SegmentRow[] {
	return segments.map( ( segment ) => ( {
		segmentKey: segment.segment_key,
		server: segment,
		draftText: segment.translated_text,
		rowState: 'clean' as SegmentRowState,
	} ) );
}

export function isRowDirty( row: SegmentRow ): boolean {
	return row.draftText !== row.server.translated_text;
}

export function countDirtyRows( rows: SegmentRow[] ): number {
	return rows.filter( isRowDirty ).length;
}

export function updateDraftText(
	rows: SegmentRow[],
	segmentKey: string,
	draftText: string
): SegmentRow[] {
	return rows.map( ( row ) => {
		if ( row.segmentKey !== segmentKey ) {
			return row;
		}

		const dirty = draftText !== row.server.translated_text;

		return {
			...row,
			draftText,
			rowState:
				row.rowState === 'conflict'
					? 'conflict'
					: dirty
						? 'dirty'
						: 'clean',
			errorMessage: undefined,
		};
	} );
}

export function markRowState(
	rows: SegmentRow[],
	segmentKey: string,
	rowState: SegmentRowState,
	patch: Partial< SegmentRow > = {}
): SegmentRow[] {
	return rows.map( ( row ) =>
		row.segmentKey === segmentKey ? { ...row, rowState, ...patch } : row
	);
}

export function applySaveSuccess(
	rows: SegmentRow[],
	segmentKey: string,
	server: WorkspaceSegment
): SegmentRow[] {
	return rows.map( ( row ) =>
		row.segmentKey === segmentKey
			? {
					segmentKey,
					server,
					draftText: server.translated_text,
					rowState: 'saved',
					errorMessage: undefined,
					conflictServer: undefined,
			  }
			: row
	);
}

export function applyConflict(
	rows: SegmentRow[],
	segmentKey: string,
	conflictServer: WorkspaceSegment | undefined,
	preservedDraft: string,
	message: string
): SegmentRow[] {
	return rows.map( ( row ) => {
		if ( row.segmentKey !== segmentKey ) {
			return row;
		}

		return {
			...row,
			draftText: preservedDraft,
			rowState: 'conflict',
			conflictServer,
			errorMessage: message,
		};
	} );
}

export function applyReloadFromServer(
	rows: SegmentRow[],
	segmentKey: string,
	server: WorkspaceSegment,
	preserveDraft: boolean
): SegmentRow[] {
	return rows.map( ( row ) => {
		if ( row.segmentKey !== segmentKey ) {
			return row;
		}

		const draftText = preserveDraft ? row.draftText : server.translated_text;
		const dirty = draftText !== server.translated_text;

		return {
			segmentKey,
			server,
			draftText,
			rowState: dirty ? 'dirty' : 'clean',
			errorMessage: undefined,
			conflictServer: undefined,
		};
	} );
}

export function mergeSegmentsIntoRows(
	rows: SegmentRow[],
	segments: WorkspaceSegment[]
): SegmentRow[] {
	const byKey = new Map(
		segments.map( ( segment ) => [ segment.segment_key, segment ] )
	);

	return rows.map( ( row ) => {
		const server = byKey.get( row.segmentKey );
		if ( ! server ) {
			return row;
		}

		if ( row.rowState === 'conflict' ) {
			return {
				...row,
				conflictServer: server,
			};
		}

		const dirty = row.draftText !== server.translated_text;

		return {
			...row,
			server,
			rowState: dirty ? 'dirty' : 'clean',
		};
	} );
}

export function dirtyRowsInOrder( rows: SegmentRow[] ): SegmentRow[] {
	return [ ...rows ]
		.filter( isRowDirty )
		.sort(
			( left, right ) =>
				left.server.segment_order - right.server.segment_order ||
				left.segmentKey.localeCompare( right.segmentKey )
		);
}

export function applyBatchSaveResults(
	currentRows: SegmentRow[],
	result: BatchSaveResult,
	sourceRows: SegmentRow[]
): SegmentRow[] {
	let nextRows = mergeSegmentsIntoRows( currentRows, result.updated );

	for ( const item of result.errors ) {
		const preserved = sourceRows.find(
			( row ) => row.segmentKey === item.segment_key
		);
		if ( ! preserved ) {
			continue;
		}

		if ( item.code === 'aiml_source_hash_mismatch' ) {
			const refreshed = item.segments?.find(
				( segment ) => segment.segment_key === item.segment_key
			);
			nextRows = applyConflict(
				nextRows,
				item.segment_key,
				refreshed,
				preserved.draftText,
				item.message || 'The source text changed since this segment was loaded.'
			);
			continue;
		}

		if ( item.code === 'aiml_translation_hash_mismatch' ) {
			const refreshed = item.segments?.find(
				( segment ) => segment.segment_key === item.segment_key
			);
			nextRows = applyConflict(
				nextRows,
				item.segment_key,
				refreshed,
				preserved.draftText,
				item.message ||
					'The saved translation changed since this segment was loaded.'
			);
			continue;
		}

		nextRows = nextRows.map( ( row ) =>
			row.segmentKey === item.segment_key
				? {
						...row,
						rowState: 'error' as SegmentRowState,
						errorMessage:
							item.message ||
							'The translation could not be saved. Please try again.',
				  }
				: row
		);
	}

	return nextRows;
}

/**
 * Merges a review batch's per-item results (submit/approve/reject) into
 * the current rows: successes replace the server row (draft text is
 * untouched by review decisions), failures surface an inline error message
 * — including re-attaching fresh QA context for `aiml_qa_blocked` approvals
 * (per-item result DTO — ADR-0015 §11.1, no silent skips).
 */
export function applyReviewBatchResults(
	currentRows: SegmentRow[],
	result: ReviewBatchResult
): SegmentRow[] {
	let nextRows = mergeSegmentsIntoRows( currentRows, result.updated );

	for ( const item of result.errors ) {
		nextRows = nextRows.map( ( row ) => {
			if ( row.segmentKey !== item.segment_key ) {
				return row;
			}

			const server = item.qa
				? { ...row.server, meta: { ...row.server.meta, qa: item.qa } }
				: row.server;

			return {
				...row,
				server,
				rowState: 'error' as SegmentRowState,
				errorMessage:
					item.message ||
					'The review action could not be completed. Please try again.',
			};
		} );
	}

	return nextRows;
}
