<?php
/**
 * Localized URL route history persistence.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Database\Schema;
use WP_Error;

/**
 * Repository for aiml_route_history.
 */
final class RouteHistoryRepository {

	/**
	 * Inserts a historical path ownership row.
	 *
	 * @param HistoryRecord $record History payload.
	 * @return object|WP_Error
	 */
	public function insert( HistoryRecord $record ) {
		global $wpdb;

		$hash  = PathHash::from_canonical( $record->historical_path );
		$path  = $record->historical_path->to_string();
		$now   = current_time( 'mysql', true );
		$table = Schema::route_history();

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- UNHEX binding for BINARY(32) hash.
		$ok = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO {$table}
				(language_id, historical_path, historical_path_hash,
				 source_type, source_id, source_subtype, created_at)
				VALUES (%d, %s, UNHEX(%s), %s, %d, %s, %s)",
				$record->language_id,
				$path,
				$hash->hex(),
				$record->source_type,
				$record->source_id,
				$record->source_subtype,
				$now
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $ok ) {
			return new WP_Error( 'history_insert_failed', 'Failed to insert route history.' );
		}

		return $this->find_by_historical_path( $record->language_id, $record->historical_path );
	}

	/**
	 * Finds history by canonical historical path.
	 *
	 * @param int           $language_id Language id.
	 * @param CanonicalPath $path        Canonical historical path.
	 */
	public function find_by_historical_path( int $language_id, CanonicalPath $path ): ?object {
		global $wpdb;

		$hash  = PathHash::from_canonical( $path );
		$table = Schema::route_history();

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE language_id = %d AND historical_path_hash = UNHEX(%s)
				LIMIT 1",
				$language_id,
				$hash->hex()
			)
		);

		if ( null === $row ) {
			return null;
		}

		$stored = (string) ( $row->historical_path ?? '' );
		if ( $stored !== $path->to_string() ) {
			return null;
		}

		return $row;
	}

	/**
	 * Lists history rows for a source object in a language (newest first).
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @param int    $language_id Language id.
	 * @param int    $limit       Max rows.
	 * @return object[]
	 */
	public function find_by_source( string $source_type, int $source_id, int $language_id, int $limit = 5 ): array {
		global $wpdb;

		$table = Schema::route_history();
		$limit = max( 1, min( 100, $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- limit is bounded int.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE source_type = %s AND source_id = %d AND language_id = %d
				ORDER BY history_id DESC
				LIMIT %d",
				$source_type,
				$source_id,
				$language_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Deletes oldest history rows beyond a retention limit.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @param int    $language_id Language id.
	 * @param int    $max_rows    Maximum rows to retain.
	 */
	public function delete_oldest_beyond( string $source_type, int $source_id, int $language_id, int $max_rows ): int {
		global $wpdb;

		$table    = Schema::route_history();
		$max_rows = max( 0, $max_rows );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT history_id FROM {$table}
				WHERE source_type = %s AND source_id = %d AND language_id = %d
				ORDER BY history_id DESC",
				$source_type,
				$source_id,
				$language_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $ids ) || count( $ids ) <= $max_rows ) {
			return 0;
		}

		$to_delete = array_slice( $ids, $max_rows );
		if ( array() === $to_delete ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $to_delete as $history_id ) {
			$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array( 'history_id' => (int) $history_id ),
				array( '%d' )
			);
			if ( false !== $result ) {
				$deleted += (int) $result;
			}
		}

		return $deleted;
	}
}
