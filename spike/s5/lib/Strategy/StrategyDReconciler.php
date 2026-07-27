<?php
/**
 * Spike S5 — Strategy D reconciliation: Strategy C keys + exact source_hash rematch.
 *
 * Algorithm (per approved plan, no fuzzy matching):
 *
 * 1. Generate Strategy C keys from the current document ($new_segments).
 * 2. Compare against previous translation state ($rows).
 * 3. Existing key present in both: preserve; mark stale if source_hash changed.
 * 4. Disappeared Strategy C key: mark ignored/orphaned; enter candidate pool.
 * 5. New Strategy C key: attempt rematch by exact source_hash + block_name.
 * 6. Rematch permitted only when mapping is unique (1:1). Never guess.
 * 7. Successful rematch rewrites the Strategy C key (row moves old → new).
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyDReconciler {

	/**
	 * @param array<string, array<string, mixed>> $rows
	 * @param array<string, array{block_name: string, text: string}> $new_segments
	 * @return array{
	 *   rows: array<string, array<string, mixed>>,
	 *   stats: array{
	 *     successful_rematch: int,
	 *     failed_rematch: int,
	 *     ambiguous_rematch: int
	 *   },
	 *   rematch_map: array<string, string>
	 * }
	 */
	public static function sync_source( array $rows, array $new_segments ): array {
		$stats = array(
			'successful_rematch' => 0,
			'failed_rematch'     => 0,
			'ambiguous_rematch'  => 0,
		);
		$rematch_map = array();

		if ( array() === $rows ) {
			return array(
				'rows'        => $rows,
				'stats'       => $stats,
				'rematch_map' => $rematch_map,
			);
		}

		// Phase 1: direct key matches and orphan marking.
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
				continue;
			}

			$row['source_hash'] = $new_hash;
			$row['is_stale']    = 1;
			$rows[ $key ]       = $row;
		}

		$orphan_keys = array();
		foreach ( $rows as $key => $row ) {
			if ( ! isset( $new_segments[ $key ] ) ) {
				$orphan_keys[] = $key;
			}
		}

		$new_keys = array();
		foreach ( array_keys( $new_segments ) as $key ) {
			if ( ! isset( $rows[ $key ] ) ) {
				$new_keys[] = $key;
			}
		}

		if ( array() === $orphan_keys || array() === $new_keys ) {
			$stats['failed_rematch'] = count( $new_keys );

			return array(
				'rows'        => $rows,
				'stats'       => $stats,
				'rematch_map' => $rematch_map,
			);
		}

		// Phase 2: build candidate edges (exact source_hash + block_name).
		/** @var array<string, list<string>> */
		$orphans_for_new = array();
		/** @var array<string, list<string>> */
		$news_for_orphan = array();

		foreach ( $new_keys as $new_key ) {
			$seg        = $new_segments[ $new_key ];
			$block_name = $seg['block_name'];
			$hash       = ReconciliationSimulator::source_hash( $seg['text'] );

			foreach ( $orphan_keys as $orphan_key ) {
				$row = $rows[ $orphan_key ];

				if ( ( $row['block_name'] ?? '' ) !== $block_name ) {
					continue;
				}

				if ( ( $row['source_hash'] ?? '' ) !== $hash ) {
					continue;
				}

				$orphans_for_new[ $new_key ][] = $orphan_key;
				$news_for_orphan[ $orphan_key ][] = $new_key;
			}
		}

		$claimed_orphans = array();
		$claimed_new     = array();

		foreach ( $new_keys as $new_key ) {
			if ( isset( $claimed_new[ $new_key ] ) ) {
				continue;
			}

			$candidate_orphans = $orphans_for_new[ $new_key ] ?? array();

			if ( array() === $candidate_orphans ) {
				++$stats['failed_rematch'];
				continue;
			}

			if ( count( $candidate_orphans ) > 1 ) {
				++$stats['ambiguous_rematch'];
				continue;
			}

			$orphan_key       = $candidate_orphans[0];
			$candidate_news   = $news_for_orphan[ $orphan_key ] ?? array();

			if ( count( $candidate_news ) > 1 ) {
				++$stats['ambiguous_rematch'];
				continue;
			}

			if ( isset( $claimed_orphans[ $orphan_key ] ) ) {
				++$stats['ambiguous_rematch'];
				continue;
			}

			$row = $rows[ $orphan_key ];
			unset( $rows[ $orphan_key ] );

			$row['status']     = 'reviewed';
			$row['error_code'] = '';
			$row['is_stale']   = 0;

			$rows[ $new_key ]              = $row;
			$rematch_map[ $orphan_key ]    = $new_key;
			$claimed_orphans[ $orphan_key ] = true;
			$claimed_new[ $new_key ]        = true;
			++$stats['successful_rematch'];
		}

		return array(
			'rows'        => $rows,
			'stats'       => $stats,
			'rematch_map' => $rematch_map,
		);
	}
}
