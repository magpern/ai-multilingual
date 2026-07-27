<?php
/**
 * Spike S5 — evaluates a strategy's key function against the ground-truth
 * Oracle for one operation applied to one tree.
 *
 * The oracle tree IS the ground truth: `is_leaf()` nodes correspond 1:1, in
 * document order, with the real content's ELIGIBLE blocks (RealBlockWalker
 * uses the identical eligibility rule — leaves only), so the i-th eligible
 * real block always corresponds to the i-th oracle leaf id in document
 * order, both before and after any operation. That correspondence is what
 * makes it possible to say, precisely, whether a strategy's key-based
 * reattachment landed on the SAME logical block or a different one — a real
 * extractor never has this; only the evaluator does.
 *
 * Metrics per the accepted plan: false_positive, rendered_false_positive,
 * correct_reattach, stale_correct, stale_missed, orphaned, spurious_new.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

use AIMultilingual\Spike\S5\Oracle\OracleNode;
use AIMultilingual\Spike\S5\Oracle\OracleTree;

final class StrategyEvaluator {

	/**
	 * @param OracleTree $tree            Mutated in place by $apply_operation.
	 * @param callable   $apply_operation function( OracleTree $tree ): void
	 * @param callable   $extract_fn      function( string $content ): array<string, array{block_name:string,text:string}>
	 * @return array{
	 *   metrics: array{false_positive:int, rendered_false_positive:int, correct_reattach:int, stale_correct:int, stale_missed:int, orphaned:int, spurious_new:int},
	 *   before_content: string, after_content: string,
	 *   before_segment_count: int, after_segment_count: int,
	 *   extraction_time_ms: array{before: float, after: float},
	 *   reconciliation_time_ms: float
	 * }
	 */
	public static function evaluate( OracleTree $tree, callable $apply_operation, callable $extract_fn ): array {
		$before_content = $tree->to_content();

		$t0 = hrtime( true );
		$before_segments = $extract_fn( $before_content );
		$extract_before_ms = ( hrtime( true ) - $t0 ) / 1e6;

		$before_leaf_ids = self::leaf_ids_in_document_order( $tree );
		$key_to_origin_id = self::zip_keys_to_ids( $before_segments, $before_leaf_ids );

		$rows = array();

		foreach ( $before_segments as $key => $seg ) {
			$rows[ $key ] = array(
				'source_hash'     => ReconciliationSimulator::source_hash( $seg['text'] ),
				'translated_text' => 'TRANS:' . $seg['text'],
				'status'          => 'reviewed',
				'is_stale'        => 0,
				'error_code'      => '',
			);
		}

		$apply_operation( $tree );

		$after_content = $tree->to_content();

		$t1 = hrtime( true );
		$after_segments = $extract_fn( $after_content );
		$extract_after_ms = ( hrtime( true ) - $t1 ) / 1e6;

		$after_leaf_ids = self::leaf_ids_in_document_order( $tree );
		$key_to_new_id  = self::zip_keys_to_ids( $after_segments, $after_leaf_ids );

		// Ground truth for stale_missed: did the real TEXT at this id
		// actually change, independent of what any hash function detects?
		$before_text_by_id = self::text_by_id( $before_segments, $before_leaf_ids );
		$after_text_by_id  = self::text_by_id( $after_segments, $after_leaf_ids );

		$t2         = hrtime( true );
		$reconciled = ReconciliationSimulator::sync_source( $rows, $after_segments );
		$reconciliation_ms = ( hrtime( true ) - $t2 ) / 1e6;

		$metrics = array(
			'false_positive'          => 0,
			'rendered_false_positive' => 0,
			'correct_reattach'        => 0,
			'stale_correct'           => 0,
			'stale_missed'            => 0,
			'orphaned'                => 0,
			'spurious_new'            => 0,
		);

		foreach ( $reconciled as $key => $row ) {
			if ( ReconciliationSimulator::STATUS_IGNORED === $row['status'] ) {
				$metrics['orphaned']++;
				continue;
			}

			$origin_id = $key_to_origin_id[ $key ] ?? null;
			$current_id = $key_to_new_id[ $key ] ?? null;
			$rendered   = null !== ReconciliationSimulator::translated_value( $row );
			$is_stale   = (bool) $row['is_stale'];

			if ( $current_id === $origin_id ) {
				// Same logical block. Is a real text change correctly flagged?
				$text_actually_changed = ( $before_text_by_id[ $origin_id ] ?? null ) !== ( $after_text_by_id[ $current_id ] ?? null );

				if ( $text_actually_changed && ! $is_stale ) {
					$metrics['stale_missed']++;
				} elseif ( $is_stale ) {
					$metrics['stale_correct']++;
				} else {
					$metrics['correct_reattach']++;
				}
			} else {
				// Different logical block bound to this key — wrong content.
				$metrics['false_positive']++;

				if ( $rendered ) {
					$metrics['rendered_false_positive']++;
				}
			}
		}

		foreach ( array_keys( $after_segments ) as $key ) {
			if ( ! isset( $rows[ $key ] ) ) {
				$metrics['spurious_new']++;
			}
		}

		return array(
			'metrics'                 => $metrics,
			'before_content'          => $before_content,
			'after_content'           => $after_content,
			'before_segment_count'    => count( $before_segments ),
			'after_segment_count'     => count( $after_segments ),
			'extraction_time_ms'      => array(
				'before' => round( $extract_before_ms, 3 ),
				'after'  => round( $extract_after_ms, 3 ),
			),
			'reconciliation_time_ms'  => round( $reconciliation_ms, 3 ),
		);
	}

	/**
	 * @return int[] Oracle leaf ids, document order.
	 */
	public static function leaf_ids_in_document_order( OracleTree $tree ): array {
		$ids = array();
		self::collect_leaf_ids( $tree->roots(), $ids );

		return $ids;
	}

	/**
	 * @param OracleNode[] $nodes
	 * @param int[]        $ids
	 */
	private static function collect_leaf_ids( array $nodes, array &$ids ): void {
		foreach ( $nodes as $node ) {
			if ( $node->is_leaf() ) {
				$ids[] = $node->id;
			} else {
				self::collect_leaf_ids( $node->children, $ids );
			}
		}
	}

	/**
	 * @param array<string, array{block_name:string,text:string}> $segments
	 * @param int[]                                                $leaf_ids
	 * @return array<string, int|null>
	 */
	private static function zip_keys_to_ids( array $segments, array $leaf_ids ): array {
		$map = array();
		$i   = 0;

		foreach ( array_keys( $segments ) as $key ) {
			$map[ $key ] = $leaf_ids[ $i ] ?? null;
			++$i;
		}

		return $map;
	}

	/**
	 * @param array<string, array{block_name:string,text:string}> $segments
	 * @param int[]                                                $leaf_ids
	 * @return array<int, string>
	 */
	private static function text_by_id( array $segments, array $leaf_ids ): array {
		$map = array();
		$i   = 0;

		foreach ( $segments as $seg ) {
			if ( isset( $leaf_ids[ $i ] ) ) {
				$map[ $leaf_ids[ $i ] ] = $seg['text'];
			}
			++$i;
		}

		return $map;
	}
}
