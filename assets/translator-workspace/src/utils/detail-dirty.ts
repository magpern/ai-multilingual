import { __ } from '@wordpress/i18n';

/**
 * True when the operator draft differs from the last persisted target text.
 */
export function isDetailDirty(
	draft: string,
	persisted: string | null | undefined
): boolean {
	return draft !== ( persisted ?? '' );
}

/**
 * Banner copy when QA/assessment/review evidence reflects persisted text, not draft.
 */
export function dirtyEvidenceHonestyMessage(): string {
	return __(
		'Quality checks, assessment, review state, and publication explain describe the last saved translation — not your unsaved draft. Save to refresh evidence.',
		'ai-multilingual'
	);
}

/**
 * Short inline hint near the target editor when dirty.
 */
export function dirtyEditorHint(): string {
	return __(
		'You have unsaved changes.',
		'ai-multilingual'
	);
}

/**
 * Confirm copy before closing or navigating away with a dirty draft.
 */
export function dirtyLeaveConfirmMessage(): string {
	return __(
		'You have unsaved changes. Discard them and leave?',
		'ai-multilingual'
	);
}
