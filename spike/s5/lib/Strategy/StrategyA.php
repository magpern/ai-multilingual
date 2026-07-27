<?php
/**
 * Spike S5 — Strategy A: positional index, no reconciliation.
 *
 * The baseline strategy, per the accepted plan: exists to demonstrate the
 * wrong-content failure concretely, so the ADR rejects it on evidence.
 *
 * Key shape: `block:N`, N a flat, document-order counter over every
 * ELIGIBLE block in the whole document (see RealBlockWalker), ignoring
 * nesting entirely — the simplest, most naive positional scheme possible.
 * Recomputed fresh from current content every time; there is no state, no
 * memory, no attempt to track anything across a change. Whatever the
 * production sync_source() algorithm does with these keys (match by string
 * equality) is the full extent of "reconciliation" this strategy gets.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyA {

	public const NAME = 'A';

	/**
	 * @return array<string, array{block_name: string, text: string}> Keyed by
	 *              segment key (`block:N`), in document order.
	 */
	public static function extract( string $content ): array {
		$eligible = RealBlockWalker::walk_eligible( $content );
		$segments = array();

		foreach ( $eligible as $i => $block ) {
			$segments[ "block:$i" ] = array(
				'block_name' => $block['block_name'],
				'text'       => $block['text'],
			);
		}

		return $segments;
	}
}
