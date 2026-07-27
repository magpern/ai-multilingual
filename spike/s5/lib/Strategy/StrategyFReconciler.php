<?php
/**
 * Spike S5 — Strategy F reconciliation: UUID direct match only.
 *
 * No structural path, no source_hash rematch, no fuzzy matching.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class StrategyFReconciler {

	/**
	 * @param array<string, array<string, mixed>> $rows
	 * @param array<string, array{uuid: string, block_name: string, text: string}> $new_segments
	 * @return array{
	 *   rows: array<string, array<string, mixed>>,
	 *   stats: array{orphaned: int, stale_marked: int, preserved: int, restored: int}
	 * }
	 */
	public static function sync_source( array $rows, array $new_segments ): array {
		$stats = array(
			'orphaned'     => 0,
			'stale_marked' => 0,
			'preserved'    => 0,
			'restored'     => 0,
		);

		if ( array() === $rows ) {
			return array( 'rows' => $rows, 'stats' => $stats );
		}

		foreach ( $rows as $key => $row ) {
			if ( ! isset( $new_segments[ $key ] ) ) {
				if ( ReconciliationSimulator::STATUS_IGNORED !== ( $row['status'] ?? '' ) ) {
					$row['status']     = ReconciliationSimulator::STATUS_IGNORED;
					$row['error_code'] = 'orphaned';
					++$stats['orphaned'];
				}
				$rows[ $key ] = $row;
				continue;
			}

			if ( ReconciliationSimulator::STATUS_IGNORED === ( $row['status'] ?? '' ) ) {
				$row['status']     = 'reviewed';
				$row['error_code'] = '';
				++$stats['restored'];
			}

			$new_hash = ReconciliationSimulator::source_hash( $new_segments[ $key ]['text'] );

			if ( $new_hash === ( $row['source_hash'] ?? '' ) ) {
				++$stats['preserved'];
				$rows[ $key ] = $row;
				continue;
			}

			$row['source_hash'] = $new_hash;
			$row['is_stale']    = 1;
			++$stats['stale_marked'];
			$rows[ $key ] = $row;
		}

		return array( 'rows' => $rows, 'stats' => $stats );
	}
}
