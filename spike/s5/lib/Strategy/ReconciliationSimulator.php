<?php
/**
 * Spike S5 — a faithful simulation of production `Store::sync_source()`'s
 * real algorithm (Store.php:412-486), so a strategy's key function can be
 * evaluated against exactly the reconciliation behaviour it would actually be
 * wired into, not an idealized stand-in for it.
 *
 * Mirrors precisely:
 *  - an empty row set does nothing (Store.php:424-426 — extraction alone
 *    never creates rows).
 *  - a row whose key is no longer present is marked ignored/orphaned, NEVER
 *    deleted (Store.php:434-454).
 *  - a row whose key persists but whose source hash changed is marked stale;
 *    `translated_text` and `status` are untouched (invariant I6,
 *    Store.php:461-478).
 *  - `translated_value()`'s invariant I7: a stale row still returns its
 *    translated text; only `ignored`/`missing` status withholds it
 *    (Store.php:281-295 — a plain-array translation of the same logic, not
 *    a call into production code).
 *
 * `source_hash()` here is a simplified stand-in for `Store::source_hash()` —
 * sufficient for comparative strategy evaluation (whitespace-collapsed
 * sha1), not a claim of exact parity with ADR-0006's full format-aware
 * normalization rules, which this spike does not need to reproduce to
 * demonstrate a strategy's false-positive behaviour.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class ReconciliationSimulator {

	public const STATUS_MISSING = 'missing';
	public const STATUS_IGNORED = 'ignored';

	/**
	 * @param array<string, array<string, mixed>> $rows         Keyed by segment key.
	 * @param array<string, array{block_name: string, text: string}> $new_segments Keyed by segment key.
	 * @return array<string, array<string, mixed>> Updated rows.
	 */
	public static function sync_source( array $rows, array $new_segments ): array {
		if ( array() === $rows ) {
			return $rows;
		}

		foreach ( $rows as $key => $row ) {
			if ( ! isset( $new_segments[ $key ] ) ) {
				if ( self::STATUS_IGNORED !== $row['status'] ) {
					$row['status']     = self::STATUS_IGNORED;
					$row['error_code'] = 'orphaned';
				}

				$rows[ $key ] = $row;
				continue;
			}

			$new_hash = self::source_hash( $new_segments[ $key ]['text'] );

			if ( $new_hash === $row['source_hash'] ) {
				continue;
			}

			$row['source_hash'] = $new_hash;
			$row['is_stale']    = 1;
			// translated_text and status are deliberately untouched here —
			// invariant I6.

			$rows[ $key ] = $row;
		}

		return $rows;
	}

	public static function source_hash( string $text ): string {
		return sha1( trim( (string) preg_replace( '/\s+/u', ' ', $text ) ) );
	}

	/**
	 * @param array<string, mixed> $row
	 */
	public static function translated_value( array $row ): ?string {
		if ( in_array( $row['status'], array( self::STATUS_IGNORED, self::STATUS_MISSING ), true ) ) {
			return null;
		}

		$value = (string) ( $row['translated_text'] ?? '' );

		return '' === $value ? null : $value;
	}
}
