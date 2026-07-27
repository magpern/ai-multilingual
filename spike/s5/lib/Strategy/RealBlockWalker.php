<?php
/**
 * Spike S5, Strategy A-G harness — walks REAL parse_blocks() output the way a
 * production extractor would, producing the set of "eligible" (translatable)
 * blocks in document order. This is deliberately independent of OracleNode:
 * strategies represent what a real extractor sees, and a real extractor never
 * has access to the oracle's internal ids.
 *
 * Structural paths come from StructuralPathWalker (see that class for the
 * explicit path-semantics contract). This class applies eligibility filtering
 * only.
 *
 * Eligibility, stated explicitly as a simplification (not a claim about the
 * eventual production BlockRegistry's real policy, which is future work):
 *  - blockName === null (freeform/whitespace, Phase 0's confirmed finding) is
 *    never eligible — handled in StructuralPathWalker (never walked).
 *  - A block WITH innerBlocks (a container) is never itself eligible in this
 *    evaluation, even if it has its own separator content (e.g.
 *    quote-with-citation's trailing <cite>) — only leaves are.
 *  - Blocks whose name is in StructuralPathWalker::DYNAMIC_BLOCK_NAMES are
 *    never eligible — their saved innerHTML is not what renders.
 *  - A leaf whose innerHTML is empty (after trim) is never eligible —
 *    matches production Extractor::extract()'s existing behaviour.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class RealBlockWalker {

	/**
	 * @return array<int, array{path: string, block_name: string, text: string}>
	 *              Flat, document-order list of eligible blocks. `path` is the
	 *              structural index path from StructuralPathWalker over ALL
	 *              real (non-freeform) blocks at each level — containers and
	 *              dynamic blocks occupy positions but are not themselves
	 *              returned here.
	 */
	public static function walk_eligible( string $content ): array {
		$results = array();

		foreach ( StructuralPathWalker::walk_tree( $content ) as $block ) {
			if ( $block['is_dynamic'] ) {
				continue;
			}

			if ( $block['inner_blocks'] ) {
				continue;
			}

			if ( '' === trim( $block['text'] ) ) {
				continue;
			}

			$results[] = array(
				'path'       => $block['path'],
				'block_name' => $block['block_name'],
				'text'       => $block['text'],
			);
		}

		return $results;
	}
}
