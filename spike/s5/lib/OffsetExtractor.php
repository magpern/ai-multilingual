<?php
/**
 * Spike S5 prototype — locates every innerContent string chunk by its
 * verbatim byte offset in the original post_content string.
 *
 * IMPORTANT SCOPE NOTE: this locates source RANGES, not translatable
 * SEGMENTS. Every chunk `parse_blocks()` reports — including a bare
 * whitespace gap between two sibling blocks, a separator's empty wrapper, or
 * any other structural filler — comes back from `locate_chunks()`. Which of
 * those ranges are actually eligible for translation (by block type, by
 * field, by whether the block is dynamic) is a policy decision that belongs
 * to a later registry/extraction component, not to this low-level offset
 * finder. This class answers only "where, exactly, is this byte range",
 * never "should this be offered to a translator".
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 * Exists to prove (or disprove) that assembly can be done by byte-splicing
 * rather than by calling serialize_block(), per the Phase 0 evidence that
 * serialize_blocks(parse_blocks($c)) is not reliably byte-identical to $c.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5;

/**
 * Walks a parsed block tree and finds where each string innerContent chunk
 * actually sits in the original document, using a monotonically advancing
 * cursor so that repeated identical chunks (e.g. two paragraphs that both say
 * "Read more") resolve to their own distinct occurrence rather than all
 * collapsing onto the first match.
 *
 * innerContent is a flat list of string fragments interleaved with null
 * markers at the positions where inner blocks sit. Descending into a child
 * only after finishing all of a block's own string chunks would search for
 * that child's chunks starting from a cursor already past them whenever the
 * child has a later sibling chunk in the same parent — so children must be
 * visited in place, at the null marker, not after their parent's own chunks.
 *
 * Confirmed finding (see OffsetExtractionTest): whitespace between two
 * sibling blocks that is not inside either one's own delimiters — e.g. the
 * newline between "<!-- /wp:paragraph -->" and the following
 * "<!-- wp:paragraph -->" — is parsed by core as its own top-level
 * `blockName === null` ("freeform") block, and therefore surfaces here as its
 * own chunk. Such a chunk is a source range/separator, never a translatable
 * segment — it is deliberately left in this class's output rather than
 * filtered, because filtering is exactly the eligibility policy described
 * above and does not belong here. A real extractor must apply that policy
 * (via a registry of translatable block types, keyed at minimum on
 * `block_name`) before ever offering a chunk to a translator.
 *
 * Not `final`: FailClosedTest subclasses it to drive the protected walk with
 * a deliberately-violated cursor invariant, proving the defensive guard
 * fires.
 */
class OffsetExtractor {

	/**
	 * Locates every non-empty string innerContent chunk of every block.
	 *
	 * @param string $content Canonical post_content.
	 * @return array<int, array{path: string, block_name: string|null, offset: int, length: int, text: string}>
	 * @throws \RuntimeException When a chunk the parser reported cannot be
	 *                            found in the document at or after the cursor.
	 *                            That would mean the "verbatim substring"
	 *                            assumption this whole approach rests on does
	 *                            not hold for this document.
	 */
	public function locate_chunks( string $content ): array {
		$blocks  = parse_blocks( $content );
		$cursor  = 0;
		$results = array();

		foreach ( $blocks as $index => $block ) {
			$this->walk_block( $block, $content, $cursor, (string) $index, $results );
		}

		return $results;
	}

	/**
	 * @param array<string, mixed>             $block   One parsed block.
	 * @param string                           $content Canonical post_content.
	 * @param int                              $cursor  Search cursor, advanced by reference.
	 * @param string                           $path    Structural path of this block.
	 * @param array<int, array<string, mixed>> $results Accumulator, by reference.
	 */
	protected function walk_block( array $block, string $content, int &$cursor, string $path, array &$results ): void {
		$block_name   = $block['blockName'] ?? null;
		$inner_blocks = (array) ( $block['innerBlocks'] ?? array() );
		$child_cursor = 0;

		foreach ( (array) ( $block['innerContent'] ?? array() ) as $chunk_index => $chunk ) {
			if ( null === $chunk ) {
				// This position belongs to the next inner block in document
				// order, not to any string chunk of the current block.
				if ( isset( $inner_blocks[ $child_cursor ] ) ) {
					$this->walk_block(
						$inner_blocks[ $child_cursor ],
						$content,
						$cursor,
						$path . '.' . $child_cursor,
						$results
					);
				}

				++$child_cursor;
				continue;
			}

			$chunk = (string) $chunk;

			if ( '' === $chunk ) {
				continue;
			}

			$offset = strpos( $content, $chunk, $cursor );

			if ( false === $offset ) {
				throw new \RuntimeException(
					sprintf(
						'Chunk at path %s.innerContent:%d not found in the document at or after offset %d. ' .
						'The verbatim-substring assumption does not hold for this input.',
						$path,
						$chunk_index,
						$cursor
					)
				);
			}

			$results[] = array(
				'path'       => $path . '.innerContent:' . $chunk_index,
				'block_name' => $block_name,
				'offset'     => $offset,
				'length'     => strlen( $chunk ),
				'text'       => $chunk,
			);

			$cursor = $offset + strlen( $chunk );
		}
	}
}
