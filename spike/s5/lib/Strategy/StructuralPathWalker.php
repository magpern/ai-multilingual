<?php
/**
 * Spike S5 — structural path assignment over REAL parse_blocks() trees.
 *
 * Used by RealBlockWalker (eligibility filtering) and Strategy C (segment keys).
 * Path semantics are fixed here and tested independently in StructuralPathTest
 * before any strategy evaluation trusts them.
 *
 * ## Path semantics (explicit — not inferred)
 *
 * 1. **Traversal order:** depth-first pre-order within each `innerBlocks` array,
 *    matching `parse_blocks()` document order at every level.
 *
 * 2. **Index origin:** 0-based sibling index at each tree level.
 *
 * 3. **Freeform chunks (`blockName === null`):** invisible. They do NOT consume
 *    a sibling index and do NOT appear in any path. (Phase 0 confirmed: whitespace
 *    between siblings is parsed as its own freeform block.)
 *
 * 4. **Real blocks (`blockName !== null`):** each consumes exactly one sibling
 *    index at its parent's level. Path = dot-joined indices from root, e.g. `1.2.0`.
 *
 * 5. **Container blocks (non-empty `innerBlocks`):** consume an index AND descend
 *    into children. The container's own path is a prefix for descendants. Containers
 *    are included in `walk_tree()` output but are NOT eligible segments in this
 *    spike's simplified policy (see RealBlockWalker).
 *
 * 6. **Dynamic blocks (DYNAMIC_BLOCK_NAMES):** consume an index (they occupy a
 *    real slot in the parsed tree) but do NOT descend — saved innerHTML is not
 *    authoritative. Listed in output with `is_dynamic: true`.
 *
 * 7. **Empty containers (`innerBlocks` present but count 0):** still consume an
 *    index; no children to descend into.
 *
 * 8. **Leaf blocks:** consume an index; `inner_blocks: false`.
 *
 * Eligibility (which leaves become translatable segments) is NOT decided here —
 * only structural addressing. RealBlockWalker applies the spike's eligibility
 * rules on top of these paths.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StructuralPathWalker {

	public const DYNAMIC_BLOCK_NAMES = array(
		'core/latest-posts',
		'core/block',
		'core/query',
		'core/post-title',
		'core/navigation',
		'core/template-part',
	);

	/**
	 * @return array<int, array{
	 *   path: string,
	 *   block_name: string,
	 *   text: string,
	 *   inner_blocks: bool,
	 *   is_dynamic: bool
	 * }> Every real (non-freeform) block in document order.
	 */
	public static function walk_tree( string $content ): array {
		$blocks  = parse_blocks( $content );
		$results = array();

		self::walk( $blocks, '', $results );

		return $results;
	}

	/**
	 * @param array<int, array<string,mixed>> $blocks
	 * @param array<int, array{path:string,block_name:string,text:string,inner_blocks:bool,is_dynamic:bool}> $results
	 */
	private static function walk( array $blocks, string $prefix, array &$results ): void {
		$real_index = 0;

		foreach ( $blocks as $block ) {
			if ( null === $block['blockName'] ) {
				continue;
			}

			$path = '' === $prefix ? (string) $real_index : $prefix . '.' . $real_index;
			++$real_index;

			$block_name  = (string) $block['blockName'];
			$is_dynamic  = in_array( $block_name, self::DYNAMIC_BLOCK_NAMES, true );
			$has_children = ! empty( $block['innerBlocks'] );

			$results[] = array(
				'path'         => $path,
				'block_name'   => $block_name,
				'text'         => (string) $block['innerHTML'],
				'inner_blocks' => $has_children,
				'is_dynamic'   => $is_dynamic,
			);

			if ( $is_dynamic ) {
				continue;
			}

			if ( $has_children ) {
				self::walk( $block['innerBlocks'], $path, $results );
			}
		}
	}
}
