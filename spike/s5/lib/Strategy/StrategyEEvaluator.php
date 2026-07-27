<?php
/**
 * Spike S5 — evaluates Strategy E using StrategyERenderGate for render decisions.
 *
 * Render eligibility lives in StrategyERenderGate; this class only orchestrates
 * extraction, reconciliation, gate resolution, and oracle metric aggregation.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

use AIMultilingual\Spike\S5\Oracle\OracleTree;

final class StrategyEEvaluator {

	/**
	 * @param callable $extract_fn function( string $content ): array<string, array{block_name:string,text:string,path?:string}>
	 * @return array{
	 *   metrics: array<string, int>,
	 *   rematch_map: array<string, string>,
	 *   gate_results: array<string, array{renders:bool,reason:string,source_fallback:bool}>,
	 *   before_content: string,
	 *   after_content: string,
	 *   before_segment_count: int,
	 *   after_segment_count: int,
	 *   extraction_time_ms: array{before: float, after: float},
	 *   reconciliation_time_ms: float,
	 *   render_gate_time_ms: float
	 * }
	 */
	public static function evaluate( OracleTree $tree, callable $apply_operation, callable $extract_fn ): array {
		$before_content = $tree->to_content();

		$t0              = hrtime( true );
		$before_segments = $extract_fn( $before_content );
		$extract_before  = ( hrtime( true ) - $t0 ) / 1e6;

		$before_leaf_ids  = StrategyEvaluator::leaf_ids_in_document_order( $tree );
		$key_to_origin_id = self::build_key_to_leaf_id( $before_content, $tree );

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
		$key_to_new_id  = self::build_key_to_leaf_id( $after_content, $tree );

		$before_text_by_id = self::text_by_id( $before_segments, $before_leaf_ids );

		$t2   = hrtime( true );
		$sync = StrategyEReconciler::sync_source( $rows, $after_segments );
		$reconciliation_ms = ( hrtime( true ) - $t2 ) / 1e6;

		$reconciled = $sync['rows'];
		$context    = array(
			'ambiguous_new_keys'  => $sync['ambiguous_new_keys'],
			'unresolved_new_keys' => $sync['unresolved_new_keys'],
		);

		$t3          = hrtime( true );
		$gate_results = array();
		foreach ( $after_segments as $key => $seg ) {
			$row = $reconciled[ $key ] ?? null;
			$gate_results[ $key ] = StrategyERenderGate::resolve( $key, $row, $seg, $context );
		}
		$render_gate_ms = ( hrtime( true ) - $t3 ) / 1e6;

		$metrics = array(
			'false_positive'              => 0,
			'rendered_false_positive'     => 0,
			'correct_reattach'            => 0,
			'correctly_rendered'          => 0,
			'source_fallbacks'            => 0,
			'stale_suppressions'          => 0,
			'orphan_suppressions'         => 0,
			'ambiguous_suppressions'      => 0,
			'failed_rematch_suppressions' => 0,
			'displaced_suppressions'      => 0,
			'stale_correct'               => 0,
			'stale_missed'                => 0,
			'orphaned'                    => 0,
			'spurious_new'                => 0,
			'successful_rematch'          => $sync['stats']['successful_rematch'],
			'failed_rematch'              => $sync['stats']['failed_rematch'],
			'ambiguous_rematch'           => $sync['stats']['ambiguous_rematch'],
			'incorrect_rematch'           => 0,
			'lost_reviewed_translations'  => 0,
			'restored_reviewed_translations' => 0,
		);

		foreach ( $gate_results as $key => $gate ) {
			$row = $reconciled[ $key ] ?? null;

			if ( $gate['source_fallback'] ) {
				++$metrics['source_fallbacks'];
				self::count_suppression( $metrics, $gate['reason'] );
			}

			if ( ! $gate['renders'] ) {
				continue;
			}

			$origin_key = array_search( $key, $sync['rematch_map'], true );
			if ( false !== $origin_key ) {
				$origin_id = $key_to_origin_id[ $origin_key ] ?? null;
				if ( null !== $origin_id && null !== ( $key_to_origin_id[ $origin_key ] ?? null ) ) {
					++$metrics['restored_reviewed_translations'];
				}
			} else {
				$origin_id = $key_to_origin_id[ $key ] ?? null;
			}

			$current_id = $key_to_new_id[ $key ] ?? null;

			if ( $current_id === $origin_id ) {
				++$metrics['correctly_rendered'];
				++$metrics['correct_reattach'];

				if ( null !== $row && ! empty( $row['is_stale'] ) ) {
					++$metrics['stale_missed'];
				}
			} else {
				++$metrics['false_positive'];
				++$metrics['rendered_false_positive'];

				if ( false !== $origin_key ) {
					++$metrics['incorrect_rematch'];
				}
			}
		}

		foreach ( $reconciled as $row ) {
			if ( ReconciliationSimulator::STATUS_IGNORED === ( $row['status'] ?? '' ) ) {
				++$metrics['orphaned'];
				++$metrics['lost_reviewed_translations'];
			}
		}

		foreach ( array_keys( $after_segments ) as $key ) {
			if ( ! isset( $reconciled[ $key ] ) ) {
				++$metrics['spurious_new'];
			}
		}

		foreach ( $after_segments as $key => $seg ) {
			$origin_id  = $key_to_origin_id[ $key ] ?? null;
			$current_id = $key_to_new_id[ $key ] ?? null;
			if ( null === $origin_id || null === $current_id ) {
				continue;
			}

			$origin_text = $before_text_by_id[ $origin_id ] ?? null;
			if ( $origin_text !== $seg['text'] && isset( $reconciled[ $key ] ) ) {
				$row = $reconciled[ $key ];
				if ( ! empty( $row['is_stale'] ) || 'displaced' === ( $row['error_code'] ?? '' ) ) {
					++$metrics['stale_correct'];
				}
			}
		}

		return array(
			'metrics'                => $metrics,
			'rematch_map'            => $sync['rematch_map'],
			'gate_results'           => $gate_results,
			'before_content'         => $before_content,
			'after_content'          => $after_content,
			'before_segment_count'   => count( $before_segments ),
			'after_segment_count'    => count( $after_segments ),
			'extraction_time_ms'     => array(
				'before' => round( $extract_before, 3 ),
				'after'  => round( $extract_after, 3 ),
			),
			'reconciliation_time_ms' => round( $reconciliation_ms, 3 ),
			'render_gate_time_ms'      => round( $render_gate_ms, 3 ),
		);
	}

	/** @param array<string, int> $metrics */
	private static function count_suppression( array &$metrics, string $reason ): void {
		switch ( $reason ) {
			case StrategyESuppressionReason::STALE_HASH:
			case StrategyESuppressionReason::STALE_FLAG:
				++$metrics['stale_suppressions'];
				break;
			case StrategyESuppressionReason::IGNORED:
				++$metrics['orphan_suppressions'];
				break;
			case StrategyESuppressionReason::AMBIGUOUS_REMATCH:
				++$metrics['ambiguous_suppressions'];
				break;
			case StrategyESuppressionReason::UNRESOLVED_REMATCH:
			case StrategyESuppressionReason::NO_ROW:
				++$metrics['failed_rematch_suppressions'];
				break;
			case StrategyESuppressionReason::DISPLACED:
				++$metrics['displaced_suppressions'];
				break;
		}
	}

	/**
	 * Map segment keys to oracle leaf ids by block_name + source_hash, skipping
	 * eligible parser blocks (e.g. empty column wrappers) that have no oracle leaf.
	 *
	 * @return array<string, int|null>
	 */
	private static function build_key_to_leaf_id( string $content, OracleTree $tree ): array {
		$leaf_pool = array();
		foreach ( StrategyEvaluator::leaf_ids_in_document_order( $tree ) as $id ) {
			$node = $tree->find( $id );
			if ( null === $node || ! $node->is_leaf() ) {
				continue;
			}
			$leaf_pool[] = array(
				'id'         => $id,
				'block_name' => (string) $node->block_name,
				'hash'       => ReconciliationSimulator::source_hash( (string) $node->prefix . (string) $node->text . (string) $node->suffix ),
			);
		}

		$used = array();
		$map  = array();

		foreach ( RealBlockWalker::walk_eligible( $content ) as $block ) {
			$key  = StrategyE::segment_key( $block['path'], $block['block_name'] );
			$hash = ReconciliationSimulator::source_hash( $block['text'] );

			foreach ( $leaf_pool as $index => $leaf ) {
				if ( isset( $used[ $index ] ) ) {
					continue;
				}
				if ( $leaf['block_name'] !== $block['block_name'] ) {
					continue;
				}
				if ( $leaf['hash'] !== $hash ) {
					continue;
				}
				$map[ $key ]     = $leaf['id'];
				$used[ $index ] = true;
				break;
			}
		}

		return $map;
	}

	/**
	 * @param array<string, array{block_name:string,text:string}> $segments
	 * @param int[] $leaf_ids
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
	 * @param int[] $leaf_ids
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
