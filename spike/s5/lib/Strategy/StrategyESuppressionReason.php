<?php
/**
 * Spike S5 — Strategy E suppression reason constants.
 *
 * Deterministic outcomes from StrategyERenderGate. Not loose ad hoc strings.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyESuppressionReason {

	public const ELIGIBLE             = 'eligible';

	public const NO_ROW               = 'no_translation_row';

	public const IGNORED              = 'ignored_orphaned';

	public const STALE_HASH           = 'stale_source_hash';

	public const BLOCK_TYPE_MISMATCH  = 'block_type_mismatch';

	public const STALE_FLAG           = 'stale_flag';

	public const DISPLACED            = 'displaced_at_key';

	public const AMBIGUOUS_REMATCH    = 'ambiguous_reconciliation';

	public const UNRESOLVED_REMATCH   = 'unresolved_rematch';

	public const EMPTY_TRANSLATION    = 'empty_translation';
}
