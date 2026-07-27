<?php
/**
 * Spike S5 — Strategy B: content fingerprint, no reconciliation.
 *
 * Per docs/plans/AI_MULTILINGUAL_PLANNING.md § Strategies:
 *
 *   Key shape: b:<block_name>:<sha1(norm)>
 *   Example:   b:core/paragraph:a1b2c3...
 *
 * `<sha1(norm)>` uses ReconciliationSimulator::source_hash() — the harness
 * stand-in for production `Store::source_hash()` / ADR-0006 normalization.
 * Recomputed fresh every time; no structural path, no attrs_fingerprint, no
 * positional component, no reconciliation memory.
 *
 * When two eligible blocks share the same block_name AND identical normalized
 * text, PHP array key collision leaves only the last occurrence. That
 * collapse is a Strategy B failure mode and is not repaired here.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyB {

	public const NAME = 'B';

	public static function segment_key( string $block_name, string $text ): string {
		return 'b:' . $block_name . ':' . ReconciliationSimulator::source_hash( $text );
	}

	/**
	 * @return array<string, array{block_name: string, text: string}> Keyed by
	 *              `b:<block_name>:<sha1(norm)>`.
	 */
	public static function extract( string $content ): array {
		$eligible = RealBlockWalker::walk_eligible( $content );
		$segments = array();

		foreach ( $eligible as $block ) {
			$key = self::segment_key( $block['block_name'], $block['text'] );
			$segments[ $key ] = array(
				'block_name' => $block['block_name'],
				'text'       => $block['text'],
			);
		}

		return $segments;
	}
}
