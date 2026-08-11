/**
 * Operations list selection helpers (OTL.5).
 *
 * Identity is translation_id. Cap is 50. Select-all is current-page only.
 */

export const OPERATIONS_SELECTION_LIMIT = 50;

export type BulkItemOutcome =
	| 'published'
	| 'unpublished'
	| 'noop'
	| 'enqueued'
	| 'skipped'
	| 'blocked'
	| 'conflict'
	| 'unauthorized'
	| 'failed'
	| string;

/** Terminal non-remediation outcomes removed from selection after bulk (A3). */
export const TERMINAL_NON_REMEDIATION_OUTCOMES: ReadonlySet< string > = new Set( [
	'published',
	'unpublished',
	'noop',
	'enqueued',
] );

/**
 * Actionable outcomes retained in selection after bulk (A3).
 * `skipped` (e.g. TI.7 ineligible publish) remains selected for remediation.
 */
export const ACTIONABLE_FAILURE_OUTCOMES: ReadonlySet< string > = new Set( [
	'skipped',
	'blocked',
	'conflict',
	'unauthorized',
	'failed',
] );

export function clearSelection(): Set< number > {
	return new Set();
}

export function toggleSelection(
	selected: Set< number >,
	translationId: number,
	checked: boolean
): Set< number > {
	const next = new Set( selected );
	if ( checked ) {
		if ( next.size >= OPERATIONS_SELECTION_LIMIT && ! next.has( translationId ) ) {
			return next;
		}
		next.add( translationId );
	} else {
		next.delete( translationId );
	}
	return next;
}

export function selectAllVisible(
	selected: Set< number >,
	visibleIds: number[]
): Set< number > {
	const next = new Set( selected );
	for ( const id of visibleIds ) {
		if ( next.size >= OPERATIONS_SELECTION_LIMIT && ! next.has( id ) ) {
			break;
		}
		next.add( id );
	}
	return next;
}

export function deselectAllVisible(
	selected: Set< number >,
	visibleIds: number[]
): Set< number > {
	const next = new Set( selected );
	for ( const id of visibleIds ) {
		next.delete( id );
	}
	return next;
}

export function allVisibleSelected(
	selected: Set< number >,
	visibleIds: number[]
): boolean {
	if ( 0 === visibleIds.length ) {
		return false;
	}
	return visibleIds.every( ( id ) => selected.has( id ) );
}

/**
 * A3 — remove terminal successes; retain actionable failures.
 */
export function applyBulkResultToSelection(
	selected: Set< number >,
	items: Array< { translation_id: number; outcome: string } >
): Set< number > {
	const next = new Set( selected );
	for ( const item of items ) {
		const id = item.translation_id;
		const outcome = item.outcome;
		if ( TERMINAL_NON_REMEDIATION_OUTCOMES.has( outcome ) ) {
			next.delete( id );
		} else if ( ACTIONABLE_FAILURE_OUTCOMES.has( outcome ) ) {
			next.add( id );
		}
	}
	return next;
}

/**
 * A6 — dirty intersection: block when dirty open id is in selection.
 */
export function dirtyBlocksBulk(
	dirty: boolean,
	dirtyTranslationId: number | null,
	selected: Set< number >
): boolean {
	if ( ! dirty || null === dirtyTranslationId ) {
		return false;
	}
	return selected.has( dirtyTranslationId );
}

/**
 * Invitation-only attemptability (A2) — not TI.7 eligibility.
 */
export function canAttemptPublish( args: {
	selectedCount: number;
	canTranslate: boolean;
} ): boolean {
	return args.selectedCount > 0 && args.canTranslate;
}

export function canAttemptUnpublish( args: {
	selectedCount: number;
	canTranslate: boolean;
} ): boolean {
	return args.selectedCount > 0 && args.canTranslate;
}

export function canAttemptEnqueueRetranslate( args: {
	selectedCount: number;
	canTranslate: boolean;
} ): boolean {
	return args.selectedCount > 0 && args.canTranslate;
}
