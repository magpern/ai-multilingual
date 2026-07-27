<?php
/**
 * Spike S5 — evaluates Strategy F: UUID injection, reconciliation, render gate.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

use AIMultilingual\Spike\S5\Oracle\OracleTree;

require_once __DIR__ . '/StrategyFUuidSync.php';
require_once __DIR__ . '/StrategyFContract.php';

final class StrategyFEvaluator {

	/**
	 * @return array{
	 *   metrics: array<string, int|float>,
	 *   gate_results: array<string, array{renders:bool,reason:string,source_fallback:bool}>,
	 *   before_content: string,
	 *   after_content: string,
	 *   before_injected_content: string,
	 *   after_injected_content: string,
	 *   inject_stats_before: array<string, mixed>,
	 *   inject_stats_after: array<string, mixed>,
	 *   before_segment_count: int,
	 *   after_segment_count: int,
	 *   parse_time_ms: array{before: float, after: float},
	 *   inject_time_ms: array{before: float, after: float},
	 *   reconciliation_time_ms: float,
	 *   render_gate_time_ms: float
	 * }
	 */
	public static function evaluate( OracleTree $tree, callable $apply_operation ): array {
		$before_raw = $tree->to_content();

		$t0 = hrtime( true );
		parse_blocks( $before_raw );
		$parse_before = ( hrtime( true ) - $t0 ) / 1e6;

		$t1           = hrtime( true );
		$before_prep  = StrategyF::prepare( $before_raw );
		StrategyFUuidSync::apply( $tree, $before_prep['content'] );
		$inject_before = ( hrtime( true ) - $t1 ) / 1e6;

		$before_segments = $before_prep['segments'];
		$key_to_origin   = self::build_key_to_leaf_id( $tree );

		$rows = array();
		foreach ( $before_segments as $key => $seg ) {
			$rows[ $key ] = array(
				'block_name'      => $seg['block_name'],
				'uuid'            => $seg['uuid'],
				'source_hash'     => ReconciliationSimulator::source_hash( $seg['text'] ),
				'translated_text' => 'TRANS:' . $seg['text'],
				'status'          => 'reviewed',
				'is_stale'        => 0,
				'error_code'      => '',
			);
		}

		$apply_operation( $tree );

		$after_raw = $tree->to_content();

		$t2 = hrtime( true );
		parse_blocks( $after_raw );
		$parse_after = ( hrtime( true ) - $t2 ) / 1e6;

		$t3          = hrtime( true );
		$after_prep  = StrategyF::prepare( $after_raw );
		StrategyFUuidSync::apply( $tree, $after_prep['content'] );
		$inject_after = ( hrtime( true ) - $t3 ) / 1e6;

		$after_segments = $after_prep['segments'];
		$key_to_new     = self::build_key_to_leaf_id( $tree );

		$t4   = hrtime( true );
		$sync = StrategyFReconciler::sync_source( $rows, $after_segments );
		$reconciliation_ms = ( hrtime( true ) - $t4 ) / 1e6;

		$reconciled = $sync['rows'];

		$duplicate_after = self::duplicate_uuid_flags( $after_prep['content'] );
		$regenerated     = $after_prep['inject_stats']['regenerated_uuids'] ?? array();
		if ( ! is_array( $regenerated ) ) {
			$regenerated = array();
		}

		$context = array(
			'duplicate_uuids'   => $duplicate_after,
			'regenerated_uuids' => $regenerated,
		);

		$t5           = hrtime( true );
		$gate_results = array();
		foreach ( $after_segments as $key => $seg ) {
			$row = $reconciled[ $key ] ?? null;
			$gate_results[ $key ] = StrategyFRenderGate::resolve( $key, $row, $seg, $context );
		}
		$render_gate_ms = ( hrtime( true ) - $t5 ) / 1e6;

		$metrics = self::empty_metrics();
		$metrics['rows_orphaned']              = $sync['stats']['orphaned'];
		$metrics['uuids_generated']            = (int) ( $before_prep['inject_stats']['uuids_generated'] ?? 0 )
			+ (int) ( $after_prep['inject_stats']['uuids_generated'] ?? 0 );
		$metrics['uuids_preserved']            = (int) ( $before_prep['inject_stats']['uuids_preserved'] ?? 0 )
			+ (int) ( $after_prep['inject_stats']['uuids_preserved'] ?? 0 );
		$metrics['uuids_regenerated']          = (int) ( $after_prep['inject_stats']['uuids_regenerated'] ?? 0 );
		$metrics['content_mutations']          = ( ( $before_prep['inject_stats']['content_changed'] ?? false ) ? 1 : 0 )
			+ ( ( $after_prep['inject_stats']['content_changed'] ?? false ) ? 1 : 0 );
		$metrics['serialized_byte_changes']    = (int) ( $before_prep['inject_stats']['bytes_added'] ?? 0 )
			+ (int) ( $after_prep['inject_stats']['bytes_added'] ?? 0 );

		foreach ( $gate_results as $key => $gate ) {
			if ( $gate['source_fallback'] ) {
				++$metrics['source_fallbacks'];
				self::count_suppression( $metrics, $gate['reason'] );
			}

			if ( ! $gate['renders'] ) {
				continue;
			}

			$origin_id  = $key_to_origin[ $key ] ?? null;
			$current_id = $key_to_new[ $key ] ?? null;

			if ( null !== $origin_id && null !== $current_id && $origin_id === $current_id ) {
				++$metrics['correctly_rendered'];
				++$metrics['successful_identity_preservation'];
				++$metrics['reviewed_translations_preserved'];
			} else {
				++$metrics['rendered_false_positive'];
				++$metrics['incorrect_continuity_decisions'];
			}
		}

		foreach ( $reconciled as $row ) {
			if ( ReconciliationSimulator::STATUS_IGNORED === ( $row['status'] ?? '' ) ) {
				++$metrics['rows_orphaned'];
				if ( 'reviewed' === ( $row['status'] ?? '' ) || ! empty( $row['translated_text'] ) ) {
					++$metrics['reviewed_translations_lost'];
				}
			}
		}

		foreach ( array_keys( $after_segments ) as $key ) {
			if ( ! isset( $reconciled[ $key ] ) ) {
				++$metrics['spurious_new'];
			}
		}

		// Rows restored: UUID reappears with same key after orphan.
		foreach ( $after_segments as $key => $seg ) {
			if ( isset( $rows[ $key ] ) && isset( $reconciled[ $key ] )
				&& ReconciliationSimulator::STATUS_IGNORED !== ( $reconciled[ $key ]['status'] ?? '' )
				&& ReconciliationSimulator::STATUS_IGNORED === ( $rows[ $key ]['status'] ?? '' ) ) {
				++$metrics['rows_restored'];
				++$metrics['reviewed_translations_preserved'];
			}
		}

		return array(
			'metrics'                  => $metrics,
			'gate_results'             => $gate_results,
			'before_content'           => $before_raw,
			'after_content'            => $after_raw,
			'before_injected_content'  => $before_prep['content'],
			'after_injected_content'   => $after_prep['content'],
			'inject_stats_before'      => $before_prep['inject_stats'],
			'inject_stats_after'       => $after_prep['inject_stats'],
			'before_segment_count'     => count( $before_segments ),
			'after_segment_count'      => count( $after_segments ),
			'parse_time_ms'            => array(
				'before' => round( $parse_before, 3 ),
				'after'  => round( $parse_after, 3 ),
			),
			'inject_time_ms'           => array(
				'before' => round( $inject_before, 3 ),
				'after'  => round( $inject_after, 3 ),
			),
			'reconciliation_time_ms'   => round( $reconciliation_ms, 3 ),
			'render_gate_time_ms'        => round( $render_gate_ms, 3 ),
		);
	}

	/** @return array<string, int|float> */
	private static function empty_metrics(): array {
		return array(
			'rendered_false_positive'           => 0,
			'correctly_rendered'                => 0,
			'source_fallbacks'                  => 0,
			'successful_identity_preservation'  => 0,
			'stale_suppressions'                => 0,
			'missing_uuid_suppressions'         => 0,
			'malformed_uuid_suppressions'       => 0,
			'duplicate_uuid_suppressions'       => 0,
			'unknown_uuid_suppressions'         => 0,
			'orphan_suppressions'               => 0,
			'uuids_generated'                   => 0,
			'uuids_preserved'                   => 0,
			'uuids_regenerated'                 => 0,
			'rows_orphaned'                     => 0,
			'rows_restored'                     => 0,
			'reviewed_translations_preserved'   => 0,
			'reviewed_translations_lost'        => 0,
			'content_mutations'                 => 0,
			'serialized_byte_changes'           => 0,
			'idempotence_failures'              => 0,
			'collisions'                        => 0,
			'incorrect_continuity_decisions'    => 0,
			'spurious_new'                      => 0,
		);
	}

	/** @param array<string, int|float> $metrics */
	private static function count_suppression( array &$metrics, string $reason ): void {
		switch ( $reason ) {
			case StrategyFSuppressionReason::STALE_HASH:
				++$metrics['stale_suppressions'];
				break;
			case StrategyFSuppressionReason::MISSING_UUID:
				++$metrics['missing_uuid_suppressions'];
				break;
			case StrategyFSuppressionReason::MALFORMED_UUID:
				++$metrics['malformed_uuid_suppressions'];
				break;
			case StrategyFSuppressionReason::DUPLICATE_UUID:
				++$metrics['duplicate_uuid_suppressions'];
				break;
			case StrategyFSuppressionReason::UNKNOWN_UUID:
			case StrategyFSuppressionReason::REGENERATED_UUID:
				++$metrics['unknown_uuid_suppressions'];
				break;
			case StrategyFSuppressionReason::ORPHANED_ROW:
				++$metrics['orphan_suppressions'];
				break;
		}
	}

	/**
	 * Map segment keys to oracle leaf ids via aimlBlockId stored on each leaf.
	 *
	 * @return array<string, int|null>
	 */
	private static function build_key_to_leaf_id( OracleTree $tree ): array {
		$map = array();

		foreach ( StrategyEvaluator::leaf_ids_in_document_order( $tree ) as $id ) {
			$node = $tree->find( $id );
			if ( null === $node || ! $node->is_leaf() ) {
				continue;
			}

			$uuid = (string) ( $node->attrs[ StrategyFContract::ATTR_NAME ] ?? '' );
			if ( '' === $uuid || ! StrategyFContract::is_valid_uuid( $uuid ) ) {
				continue;
			}

			$map[ StrategyFContract::segment_key( $uuid ) ] = $id;
		}

		return $map;
	}

	/** @return array<string, bool> */
	private static function duplicate_uuid_flags( string $content ): array {
		$flags = array();
		foreach ( UuidBlockWalker::count_uuids( $content ) as $uuid => $count ) {
			if ( $count > 1 ) {
				$flags[ $uuid ] = true;
			}
		}
		return $flags;
	}
}
