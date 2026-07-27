<?php
/**
 * Spike S5 prototype — deterministically builds a large document by repeating
 * a fixed cycle of template blocks, for the 100/500/1000-block performance
 * runs.
 *
 * NOT AUTHENTIC CORPUS DATA, and must never be labelled as such. This class's
 * own tests (ScaleDocumentGeneratorTest) feed it a tiny, synthetic, hand-built
 * template set SOLELY to prove the generator's mechanics — determinism,
 * correct id assignment per repetition, valid real markup at scale. Once the
 * editor-authored corpus lands (Phase 1b), the SAME generator is invoked again
 * with those authentic blocks as the template cycle to build the final
 * 100/500/1000-block documents used for the performance measurements in the
 * spike report. The two invocations must be kept visibly distinct in the
 * report: this file proves the machine works, it does not produce evidence
 * about real content.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

final class ScaleDocumentGenerator {

	/**
	 * Repeats $templates, in order, as many whole cycles as needed to reach
	 * or exceed $target_block_count total nodes (containers and leaves both
	 * counted, matching how a document's "block count" is meant informally).
	 * Never truncates mid-cycle — a partial repetition of a template would
	 * not be a valid, complete block, so the final count may modestly exceed
	 * the target; callers must read the actual count off the returned tree
	 * rather than assume it hit the target exactly. No silent rounding: the
	 * achieved count is exactly what actual_block_count() reports.
	 *
	 * Fully deterministic: no randomness, no wall clock. The same
	 * $templates + $ids (freshly constructed) + $target_block_count +
	 * $text_variant always produces byte-identical output.
	 *
	 * @param OracleNode[] $templates    One cycle of template root nodes to repeat. Not mutated.
	 * @param IdGenerator  $ids          Fresh id source; every repetition's clone gets entirely new ids.
	 * @param int          $target_block_count Minimum total node count to reach.
	 * @param callable     $text_variant (string $original_text, int $cycle_index): string —
	 *                                   pure function distinguishing each repetition's text
	 *                                   from the others, so repeated cycles are not byte-identical.
	 */
	public static function generate( array $templates, IdGenerator $ids, int $target_block_count, callable $text_variant ): OracleTree {
		if ( array() === $templates ) {
			throw new \RuntimeException( 'At least one template is required.' );
		}

		$roots = array();
		$count = 0;
		$cycle = 0;

		while ( $count < $target_block_count ) {
			foreach ( $templates as $template ) {
				$clone = $template->clone_with_fresh_ids( $ids );
				self::apply_text_variant( $clone, $cycle, $text_variant );

				$roots[] = $clone;
				$count  += self::count_nodes( $clone );
				++$cycle;
			}
		}

		return new OracleTree( $roots );
	}

	public static function actual_block_count( OracleTree $tree ): int {
		$total = 0;

		foreach ( $tree->roots() as $root ) {
			$total += self::count_nodes( $root );
		}

		return $total;
	}

	private static function count_nodes( OracleNode $node ): int {
		$total = 1;

		foreach ( $node->children as $child ) {
			$total += self::count_nodes( $child );
		}

		return $total;
	}

	private static function apply_text_variant( OracleNode $node, int $cycle, callable $text_variant ): void {
		if ( $node->is_leaf() ) {
			$node->text = $text_variant( (string) $node->text, $cycle );
		}

		foreach ( $node->children as $child ) {
			self::apply_text_variant( $child, $cycle, $text_variant );
		}
	}
}
