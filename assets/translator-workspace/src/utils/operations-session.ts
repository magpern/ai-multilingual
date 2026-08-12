/**
 * Session-only Operations navigation snapshot (OTL.6 A2).
 *
 * Module memory only — not durable browser storage, not DB, not draft text.
 * Selection and bulk results are intentionally not stored here.
 */

import type { AttentionPreset } from './operations-attention';

export interface OperationsNavSnapshot {
	language: string;
	attention: AttentionPreset;
	pageNum: number;
	status: string;
	reviewStatus: string;
	publishStatus: string;
	isStale: string;
	sourceType: string;
	sourceId: string;
	translationId: number | null;
}

let snapshot: OperationsNavSnapshot | null = null;

export function stashOperationsSession( next: OperationsNavSnapshot ): void {
	snapshot = { ...next };
}

export function peekOperationsSession(): OperationsNavSnapshot | null {
	return snapshot ? { ...snapshot } : null;
}

export function clearOperationsSession(): void {
	snapshot = null;
}
