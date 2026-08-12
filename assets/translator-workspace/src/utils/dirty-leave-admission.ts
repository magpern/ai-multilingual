/**
 * Pure helpers for dirty-leave admission concurrency (OTL.6 A1).
 */

export function canOpenConfirmDialog( alreadyPending: boolean ): boolean {
	return ! alreadyPending;
}
