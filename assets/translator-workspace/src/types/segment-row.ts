import type { WorkspaceSegment } from './view-models';

export type SegmentRowState =
	| 'clean'
	| 'dirty'
	| 'saving'
	| 'saved'
	| 'error'
	| 'conflict';

export interface SegmentRow {
	segmentKey: string;
	server: WorkspaceSegment;
	draftText: string;
	rowState: SegmentRowState;
	errorMessage?: string;
	conflictServer?: WorkspaceSegment;
}

export interface BatchSaveResult {
	updated: WorkspaceSegment[];
	errors: Array<{
		segment_key: string;
		code?: string;
		message?: string;
		segments?: WorkspaceSegment[];
	}>;
	status: 'completed' | 'partial';
}
