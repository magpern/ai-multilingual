<?php
/**
 * Spike S5 — Strategy E reconciliation: Strategy D + displaced-row rematch pool.
 *
 * Extends Strategy D by preserving original source_hash on path-reuse displacement
 * (key survives, content changes) and allowing displaced rows to enter the exact-
 * hash rematch candidate pool. Option B from the Strategy E spec — suppression
 * plus displaced-row participation; rendering never precedes reconciliation.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyEReconciler {

	/**
	 * @param array<string, array<string, mixed>> $rows
	 * @param array<string, array{block_name: string, text: string}> $new_segments
	 * @return array{
	 *   rows: array<string, array<string, mixed>>,
	 *   stats: array{
	 *     successful_rematch: int,
	 *     failed_rematch: int,
	 *     ambiguous_rematch: int,
	 *     displaced_rows: int
	 *   },
	 *   rematch_map: array<string, string>,
	 *   ambiguous_new_keys: array<string, bool>,
	 *   unresolved_new_keys: array<string, bool>
	 * }
	 */
	public static function sync_source( array $rows, array $new_segments ): array {
		$stats = array(
			'successful_rematch' => 0,
			'failed_rematch'     => 0,
			'ambiguous_rematch'  => 0,
			'displaced_rows'     => 0,
		);
		$rematch_map          = array();
		$ambiguous_new_keys   = array();
		$unresolved_new_keys  = array();

		if ( array() === $rows ) {
			return self::result( $rows, $stats, $rematch_map, $ambiguous_new_keys, $unresolved_new_keys );
		}

		$displaced_keys = array();

		foreach ( $rows as $key => $row ) {
			if ( ! isset( $new_segments[ $key ] ) ) {
				if ( ReconciliationSimulator::STATUS_IGNORED !== $row['status'] ) {
					$row['status']     = ReconciliationSimulator::STATUS_IGNORED;
					$row['error_code'] = 'orphaned';
				}
				$rows[ $key ] = $row;
				continue;
			}

			$new_hash = ReconciliationSimulator::source_hash( $new_segments[ $key ]['text'] );

			if ( $new_hash === $row['source_hash'] ) {
				if ( 'displaced' === ( $row['error_code'] ?? '' ) ) {
					$row['error_code'] = '';
					$row['is_stale']   = 0;
				}
				$rows[ $key ] = $row;
				continue;
			}

			$row['is_stale']   = 1;
			$row['error_code'] = 'displaced';
			$rows[ $key ]      = $row;
			$displaced_keys[]  = $key;
			++$stats['displaced_rows'];
		}

		$orphan_keys = array();
		foreach ( $rows as $key => $row ) {
			if ( ! isset( $new_segments[ $key ] ) ) {
				$orphan_keys[] = $key;
			}
		}

		$new_keys = array();
		foreach ( array_keys( $new_segments ) as $key ) {
			if ( ! isset( $rows[ $key ] ) || in_array( $key, $displaced_keys, true ) ) {
				$new_keys[] = $key;
			}
		}

		$candidate_sources = array_merge( $orphan_keys, $displaced_keys );

		if ( array() === $candidate_sources || array() === $new_keys ) {
			foreach ( $new_keys as $new_key ) {
				if ( ! isset( $rows[ $new_key ] ) ) {
					++$stats['failed_rematch'];
					$unresolved_new_keys[ $new_key ] = true;
				}
			}

			return self::result( $rows, $stats, $rematch_map, $ambiguous_new_keys, $unresolved_new_keys );
		}

		/** @var array<string, list<string>> $sources_for_new */
		$sources_for_new = array();
		/** @var array<string, list<string>> $news_for_source */
		$news_for_source = array();

		foreach ( $new_keys as $new_key ) {
			if ( isset( $rows[ $new_key ] ) && ! in_array( $new_key, $displaced_keys, true ) ) {
				continue;
			}

			$seg        = $new_segments[ $new_key ];
			$block_name = $seg['block_name'];
			$hash       = ReconciliationSimulator::source_hash( $seg['text'] );

			foreach ( $candidate_sources as $source_key ) {
				$row = $rows[ $source_key ];

				if ( ( $row['block_name'] ?? '' ) !== $block_name ) {
					continue;
				}

				if ( ( $row['source_hash'] ?? '' ) !== $hash ) {
					continue;
				}

				$sources_for_new[ $new_key ][] = $source_key;
				$news_for_source[ $source_key ][] = $new_key;
			}
		}

		$claimed_sources = array();
		$claimed_new     = array();

		foreach ( $new_keys as $new_key ) {
			if ( isset( $claimed_new[ $new_key ] ) ) {
				continue;
			}

			if ( isset( $rows[ $new_key ] ) && ! in_array( $new_key, $displaced_keys, true ) ) {
				continue;
			}

			$candidate_sources_for_new = $sources_for_new[ $new_key ] ?? array();

			if ( array() === $candidate_sources_for_new ) {
				if ( ! isset( $rows[ $new_key ] ) ) {
					++$stats['failed_rematch'];
					$unresolved_new_keys[ $new_key ] = true;
				}
				continue;
			}

			if ( count( $candidate_sources_for_new ) > 1 ) {
				++$stats['ambiguous_rematch'];
				$ambiguous_new_keys[ $new_key ] = true;
				continue;
			}

			$source_key     = $candidate_sources_for_new[0];
			$candidate_news = $news_for_source[ $source_key ] ?? array();

			if ( count( $candidate_news ) > 1 ) {
				++$stats['ambiguous_rematch'];
				$ambiguous_new_keys[ $new_key ] = true;
				continue;
			}

			if ( isset( $claimed_sources[ $source_key ] ) ) {
				++$stats['ambiguous_rematch'];
				$ambiguous_new_keys[ $new_key ] = true;
				continue;
			}

			$row = $rows[ $source_key ];
			unset( $rows[ $source_key ] );

			$row['status']     = 'reviewed';
			$row['error_code'] = '';
			$row['is_stale']   = 0;

			$rows[ $new_key ]               = $row;
			$rematch_map[ $source_key ]     = $new_key;
			$claimed_sources[ $source_key ] = true;
			$claimed_new[ $new_key ]        = true;
			++$stats['successful_rematch'];
		}

		return self::result( $rows, $stats, $rematch_map, $ambiguous_new_keys, $unresolved_new_keys );
	}

	/**
	 * @param array<string, array<string, mixed>> $rows
	 * @param array<string, int> $stats
	 * @param array<string, string> $rematch_map
	 * @param array<string, bool> $ambiguous_new_keys
	 * @param array<string, bool> $unresolved_new_keys
	 * @return array{
	 *   rows: array<string, array<string, mixed>>,
	 *   stats: array<string, int>,
	 *   rematch_map: array<string, string>,
	 *   ambiguous_new_keys: array<string, bool>,
	 *   unresolved_new_keys: array<string, bool>
	 * }
	 */
	private static function result(
		array $rows,
		array $stats,
		array $rematch_map,
		array $ambiguous_new_keys,
		array $unresolved_new_keys
	): array {
		return array(
			'rows'                => $rows,
			'stats'               => $stats,
			'rematch_map'         => $rematch_map,
			'ambiguous_new_keys'  => $ambiguous_new_keys,
			'unresolved_new_keys' => $unresolved_new_keys,
		);
	}
}
