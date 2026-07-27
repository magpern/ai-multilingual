<?php
/**
 * Strategy F block tree traversal.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Depth-first traversal over {@see parse_blocks()} trees.
 *
 * Preserves sibling order and nested structure. Freeform chunks (`blockName`
 * null) are skipped but do not interrupt traversal of real blocks.
 */
final class BlockTreeWalker {

	/**
	 * Visits every real block in depth-first pre-order.
	 *
	 * @param array<int, array<string, mixed>> $blocks   Parsed block tree.
	 * @param callable                         $callback Receives each block by reference.
	 */
	public function walk( array &$blocks, callable $callback ): void {
		foreach ( $blocks as &$block ) {
			if ( null === ( $block['blockName'] ?? null ) ) {
				continue;
			}

			$callback( $block );

			$inner = $block['innerBlocks'] ?? array();
			if ( is_array( $inner ) && array() !== $inner ) {
				$this->walk( $block['innerBlocks'], $callback );
			}
		}
		unset( $block );
	}

	/**
	 * Returns real blocks in depth-first pre-order without mutating the tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed block tree.
	 * @return list<array<string, mixed>>
	 */
	public function collect( array $blocks ): array {
		$collected = array();

		$this->walk(
			$blocks,
			static function ( array $block ) use ( &$collected ): void {
				$collected[] = $block;
			}
		);

		return $collected;
	}
}
