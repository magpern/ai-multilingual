<?php
/**
 * Spike S5 — Strategy D: Strategy C identity + exact source_hash reconciliation.
 *
 * Per docs/plans/AI_MULTILINGUAL_PLANNING.md § Strategies:
 *
 *   Identity: b:<structural_path>:<block_name>:content  (same as Strategy C)
 *   Reconciliation: exact source_hash rematch only — no fuzzy matching,
 *   confidence scoring, UUIDs, registry, or rendering suppression.
 *
 * Extraction delegates to Strategy C. Reconciliation is StrategyDReconciler.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyD {

	public const NAME = 'D';

	public static function segment_key( string $structural_path, string $block_name ): string {
		return StrategyC::segment_key( $structural_path, $block_name );
	}

	/**
	 * @return array<string, array{block_name: string, text: string, path: string}>
	 */
	public static function extract( string $content ): array {
		return StrategyC::extract( $content );
	}
}
