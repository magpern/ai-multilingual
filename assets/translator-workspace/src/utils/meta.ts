import type {
	NormalizedSuggestion,
	QAIssue,
	SegmentQA,
	WorkspaceSegment,
} from '../types/view-models';

export const GLOSSARY_TERM_ISSUE_CODE = 'glossary_term_missing';

export function suggestionsFromSegment(
	segment: WorkspaceSegment
): NormalizedSuggestion[] {
	const raw = segment.meta?.suggestions;
	return Array.isArray( raw ) ? raw : [];
}

export function qaFromSegment( segment: WorkspaceSegment ): SegmentQA {
	const raw = segment.meta?.qa;
	if ( ! raw || typeof raw !== 'object' ) {
		return {
			issues: [],
			summary: { errors: 0, warnings: 0, info: 0 },
		};
	}

	const qa = raw as SegmentQA;
	return {
		issues: Array.isArray( qa.issues ) ? qa.issues : [],
		summary: {
			errors: Number( qa.summary?.errors ?? 0 ),
			warnings: Number( qa.summary?.warnings ?? 0 ),
			info: Number( qa.summary?.info ?? 0 ),
		},
	};
}

/**
 * Extracts read-only glossary terminology context from QA issues
 * (`glossary_term_missing` is warning-only and never blocks approval — the
 * Review UI must never mutate glossary data, only display it).
 */
export function glossaryIssuesFromQa( qa: SegmentQA ): QAIssue[] {
	return qa.issues.filter(
		( issue ) => GLOSSARY_TERM_ISSUE_CODE === issue.code
	);
}

export function aggregateQaSummary( segments: WorkspaceSegment[] ): {
	errors: number;
	warnings: number;
	info: number;
} {
	return segments.reduce(
		( totals, segment ) => {
			const summary = qaFromSegment( segment ).summary;
			return {
				errors: totals.errors + summary.errors,
				warnings: totals.warnings + summary.warnings,
				info: totals.info + summary.info,
			};
		},
		{ errors: 0, warnings: 0, info: 0 }
	);
}
