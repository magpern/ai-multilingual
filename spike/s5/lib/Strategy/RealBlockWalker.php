<?php
/**
 * Spike S5, Strategy A-G harness — walks REAL parse_blocks() output the way a
 * production extractor would, producing the set of "eligible" (translatable)
 * blocks in document order. This is deliberately independent of OracleNode:
 * strategies represent what a real extractor sees, and a real extractor never
 * has access to the oracle's internal ids.
 *
 * Eligibility, stated explicitly as a simplification (not a claim about the
 * eventual production BlockRegistry's real policy, which is future work):
 *  - blockName === null (freeform/whitespace, Phase 0's confirmed finding) is
 *    never eligible.
 *  - A block WITH innerBlocks (a container) is never itself eligible in this
 *    evaluation, even if it has its own separator content (e.g.
 *    quote-with-citation's trailing <cite>) — only leaves are. A real
 *    BlockRegistry may choose to expose container-owned text too; that
 *    policy question is explicitly out of scope here (see
 *    OffsetExtractor's own docblock: "eligibility is a later
 *    registry/extraction-policy concern").
 *  - Blocks whose name is in DYNAMIC_BLOCK_NAMES are never eligible — their
 *    saved innerHTML is not what renders (WP_Block_Type::is_dynamic()),
 *    confirmed during Phase 0 planning.
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

	private const DYNAMIC_BLOCK_NAMES = array(
		'core/latest-posts',
		'core/block',
		'core/query',
		'core/post-title',
		'core/navigation',
		'core/template-part',
	);

	/**
	 * @return array<int, array{path: string, block_name: string, text: string}>
	 *              Flat, document-order list of eligible blocks. `path` is a
	 *              dot-joined structural index path counting ONLY eligible
	 *              descendants' ancestor positions among ALL real
	 *              (non-freeform) blocks at each level — available for
	 *              structural strategies (C onward); Strategy A ignores it
	 *              and uses a flat counter instead.
	 */
	public static function walk_eligible( string $content ): array {
		$blocks  = parse_blocks( $content );
		$results = array();

		self::walk( $blocks, '', $results );

		return $results;
	}

	/**
	 * @param array<int, array<string,mixed>>          $blocks
	 * @param array<int, array{path:string,block_name:string,text:string}> $results
	 */
	private static function walk( array $blocks, string $prefix, array &$results ): void {
		$real_index = 0;

		foreach ( $blocks as $block ) {
			if ( null === $block['blockName'] ) {
				continue; // Freeform/whitespace — never a real block, never counted.
			}

			$path = '' === $prefix ? (string) $real_index : $prefix . '.' . $real_index;
			++$real_index;

			if ( in_array( $block['blockName'], self::DYNAMIC_BLOCK_NAMES, true ) ) {
				continue; // Dynamic — saved innerHTML never renders.
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $path, $results );
				continue; // A container is never itself eligible (see class docblock).
			}

			$text = (string) $block['innerHTML'];

			if ( '' === trim( $text ) ) {
				continue; // Nothing to translate.
			}

			$results[] = array(
				'path'       => $path,
				'block_name' => (string) $block['blockName'],
				'text'       => $text,
			);
		}
	}
}
