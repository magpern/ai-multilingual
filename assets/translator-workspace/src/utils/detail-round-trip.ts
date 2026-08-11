/**
 * Fixtures proving the detail editor must not transform draft text client-side.
 */
export const ROUND_TRIP_FIXTURES = [
	'  leading and trailing spaces  ',
	'line one\nline two\r\nline three',
	'Tabs\tstay\tintact',
	'Entities &amp; should not be rewritten',
	'<strong>raw markup</strong> preserved',
	'Unicode: café — 日本語 — emoji 🌍',
	'',
] as const;

/**
 * Identity helper — the only permitted client-side text handling for saves.
 */
export function identityRoundTrip( text: string ): string {
	return text;
}

/**
 * Returns true when draft equals persisted without any normalization.
 */
export function isRawRoundTripEqual(
	draft: string,
	persisted: string | null | undefined
): boolean {
	return identityRoundTrip( draft ) === identityRoundTrip( persisted ?? '' );
}
