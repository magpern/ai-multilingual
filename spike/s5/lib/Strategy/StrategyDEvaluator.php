<?php
/**
 * Spike S5 — evaluates Strategy D (C identity + exact source_hash rematch).
 *
 * Same oracle ground-truth machinery as StrategyEvaluator, but wires
 * StrategyDReconciler and records rematch statistics.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

use AIMultilingual\Spike\S5\Oracle\OracleTree;

final class StrategyDEvaluator {

	/**
	 * @param OracleTree $tree
	 * @param callable   $apply_operation function( OracleTree $tree ): void
	 * @param callable   $extract_fn      function( string $content ): array<string, array{block_name:string,text:string,path?:string}>
	 * @return array{
	 *   metrics: array{
	 *     false_positive:int, rendered_false_positive:int, correct_reattach:int,
	 *     stale_correct:int, stale_missed:int, orphaned:int, spurious_new:int,
	 *     successful_rematch:int, failed_rematch:int, ambiguous_rematch:int,
	 *     incorrect_rematch:int
	 *   },
	 *   rematch_map: array<string, string>,
	 *   before_content: string, after_content: string,
	 *   before_segment_count: int, after_segment_count: int,
	 *   extraction_time_ms: array{before: float, after: float},
	 *   reconciliation_time_ms: float, rematch_time_ms: float
	 * }
	 */
	public static function evaluate( OracleTree $tree, callable $apply_operation, callable $extract_fn ): array {
		$before_content = $tree->to_content();

		$t0              = hrtime( true );
		$before_segments = $extract_fn( $before_content );
		$extract_before  = ( hrtime( true ) - $t0 ) / 1e6;

		$before_leaf_ids  = StrategyEvaluator::leaf_ids_in_document_order( $tree );
		$key_to_origin_id = self::zip_keys_to_ids( $before_segments, $before_leaf_ids );

		$rows = array();
		foreach ( $before_segments as $key => $seg ) {
			$rows[ $key ] = array(
				'block_name'      => $seg['block_name'],
				'source_hash'     => ReconciliationSimulator::source_hash( $seg['text'] ),
				'translated_text' => 'TRANS:' . $seg['text'],
				'status'          => 'reviewed',
				'is_stale'        => 0,
				'error_code'      => '',
			);
		}

		$apply_operation( $tree );

		$after_content = $tree->to_content();

		$t1             = hrtime( true );
		$after_segments = $extract_fn( $after_content );
		$extract_after  = ( hrtime( true ) - $t1 ) / 1e6;

		$after_leaf_ids = StrategyEvaluator::leaf_ids_in_document_order( $tree );
		$key_to_new_id  = self::zip_keys_to_ids( $after_segments, $after_leaf_ids );

		$before_text_by_id = self::text_by_id( $before_segments, $before_leaf_ids );
		$after_text_by_id  = self::text_by_id( $after_segments, $after_leaf_ids );

		$t2 = hrtime( true );
		$sync = StrategyDReconciler::sync_source( $rows, $after_segments );
		$reconciliation_ms = ( hrtime( true ) - $t2 ) / 1e6;

		$reconciled  = $sync['rows'];
		$rematch_map = $sync['rematch_map'];
		$rematch_stats = $sync['stats'];

		$metrics = array(
			'false_positive'          => 0,
			'rendered_false_positive' => 0,
			'correct_reattach'        => 0,
			'stale_correct'           => 0,
			'stale_missed'            => 0,
			'orphaned'                => 0,
			'spurious_new'            => 0,
			'successful_rematch'      => $rematch_stats['successful_rematch'],
			'failed_rematch'          => $rematch_stats['failed_rematch'],
			'ambiguous_rematch'       => $rematch_stats['ambiguous_rematch'],
			'incorrect_rematch'       => 0,
		);

		foreach ( $reconciled as $key => $row ) {
			if ( ReconciliationSimulator::STATUS_IGNORED === $row['status'] ) {
				++$metrics['orphaned'];
				continue;
			}

			$origin_key = array_search( $key, $rematch_map, true );
			if ( false !== $origin_key ) {
				$origin_id    = $key_to_origin_id[ $origin_key ] ?? null;
				$was_rematched = true;
			} else {
				$origin_id     = $key_to_origin_id[ $key ] ?? null;
				$was_rematched = false;
			}

			$current_id = $key_to_new_id[ $key ] ?? null;
			$rendered   = null !== ReconciliationSimulator::translated_value( $row );
			$is_stale   = (bool) $row['is_stale'];

			if ( $current_id === $origin_id ) {
				$text_changed = ( $before_text_by_id[ $origin_id ] ?? null ) !== ( $after_text_by_id[ $current_id ] ?? null );

				if ( $text_changed && ! $is_stale ) {
					++$metrics['stale_missed'];
				} elseif ( $is_stale ) {
					++$metrics['stale_correct'];
				} else {
					++$metrics['correct_reattach'];
				}
			} else {
				++$metrics['false_positive'];

				if ( $was_rematched ) {
					++$metrics['incorrect_rematch'];
				}

				if ( $rendered ) {
					++$metrics['rendered_false_positive'];
				}
			}
		}

		foreach ( array_keys( $after_segments ) as $key ) {
			if ( ! isset( $reconciled[ $key ] ) ) {
				++$metrics['spurious_new'];
			}
		}

		return array(
			'metrics'                => $metrics,
			'rematch_map'            => $rematch_map,
			'before_content'         => $before_content,
			'after_content'          => $after_content,
			'before_segment_count'   => count( $before_segments ),
			'after_segment_count'    => count( $after_segments ),
			'extraction_time_ms'     => array(
				'before' => round( $extract_before, 3 ),
				'after'  => round( $extract_after, 3 ),
			),
			'reconciliation_time_ms' => round( $reconciliation_ms, 3 ),
			'rematch_time_ms'        => round( $reconciliation_ms, 3 ),
		);
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
