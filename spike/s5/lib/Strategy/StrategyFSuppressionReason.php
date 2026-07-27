<?php
/**
 * Spike S5 — Strategy F suppression reason constants.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyFSuppressionReason {

	public const ELIGIBLE            = 'eligible';

	public const MISSING_UUID        = 'missing_uuid';

	public const MALFORMED_UUID      = 'malformed_uuid';

	public const DUPLICATE_UUID      = 'duplicate_uuid';

	public const REGENERATED_UUID    = 'regenerated_uuid';

	public const UNKNOWN_UUID        = 'unknown_uuid';

	public const ORPHANED_ROW        = 'orphaned_row';

	public const BLOCK_TYPE_MISMATCH = 'block_type_mismatch';

	public const STALE_HASH          = 'stale_source_hash';

	public const EMPTY_TRANSLATION   = 'empty_translation';
}
