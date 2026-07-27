<?php
/**
 * Spike S5 — Strategy E: C identity + D reconciliation + render suppression gate.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyE {

	public const NAME = 'E';

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
