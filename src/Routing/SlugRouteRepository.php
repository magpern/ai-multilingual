<?php
/**
 * Localized URL route persistence.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Database\Schema;
use WP_Error;

/**
 * Repository for aiml_slug_routes. Derives path hashes internally (R2/R3).
 */
final class SlugRouteRepository {

	/**
	 * Persists a route record (insert or update by object/language).
	 *
	 * @param RouteRecord $record Route payload; hashes derived from canonical paths.
	 * @return object|WP_Error
	 */
	public function save( RouteRecord $record ) {
		global $wpdb;

		$source_hash    = PathHash::from_canonical( $record->source_path );
		$localized_hash = PathHash::from_canonical( $record->localized_path );
		$source_path    = $record->source_path->to_string();
		$localized_path = $record->localized_path->to_string();
		$now            = current_time( 'mysql', true );
		$existing       = $this->find_by_object( $record->source_type, $record->source_id, $record->language_id );

		if ( null !== $existing ) {
			return $this->update_row(
				(int) $existing->route_id,
				$record,
				$source_path,
				$localized_path,
				$source_hash,
				$localized_hash,
				$now
			);
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier only.
		$ok = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'INSERT INTO ' . Schema::slug_routes() . '
				(language_id, source_type, source_id, source_subtype,
				 source_path, source_path_hash, localized_path, localized_path_hash,
				 localized_slug, route_namespace, slug_origin, route_status, activated_at,
				 created_at, updated_at)
				VALUES (%d, %s, %d, %s, %s, UNHEX(%s), %s, UNHEX(%s), %s, %s, %s, %s, %s, %s, %s)',
				$record->language_id,
				$record->source_type,
				$record->source_id,
				$record->source_subtype,
				$source_path,
				$source_hash->hex(),
				$localized_path,
				$localized_hash->hex(),
				$record->localized_slug,
				$record->route_namespace,
				$record->slug_origin,
				$record->route_status,
				$record->activated_at,
				$now,
				$now
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $ok ) {
			return new WP_Error( 'route_insert_failed', 'Failed to insert slug route.' );
		}

		return $this->find_by_object( $record->source_type, $record->source_id, $record->language_id );
	}

	/**
	 * Finds the current route for an object in a language.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @param int    $language_id Language id.
	 */
	public function find_by_object( string $source_type, int $source_id, int $language_id ): ?object {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier only.
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::slug_routes() . '
				WHERE source_type = %s AND source_id = %d AND language_id = %d
				LIMIT 1',
				$source_type,
				$source_id,
				$language_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $row ) {
			return null;
		}

		return $row;
	}

	/**
	 * Finds a route by localized canonical path.
	 *
	 * @param int           $language_id Language id.
	 * @param CanonicalPath $path        Canonical localized path.
	 */
	public function find_by_localized_path( int $language_id, CanonicalPath $path ): ?object {
		return $this->find_by_path_hash_column( $language_id, $path, 'localized_path_hash', 'localized_path' );
	}

	/**
	 * Finds an **active** route by localized canonical path.
	 *
	 * @param int           $language_id Language id.
	 * @param CanonicalPath $path        Canonical localized path.
	 */
	public function find_active_by_localized_path( int $language_id, CanonicalPath $path ): ?object {
		$row = $this->find_by_localized_path( $language_id, $path );
		if ( null === $row || 'active' !== (string) ( $row->route_status ?? '' ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Finds a route by source canonical path.
	 *
	 * @param int           $language_id Language id.
	 * @param CanonicalPath $path        Canonical source path.
	 */
	public function find_by_source_path( int $language_id, CanonicalPath $path ): ?object {
		return $this->find_by_path_hash_column( $language_id, $path, 'source_path_hash', 'source_path' );
	}

	/**
	 * Finds an **active** route by source canonical path.
	 *
	 * @param int           $language_id Language id.
	 * @param CanonicalPath $path        Canonical source path.
	 */
	public function find_active_by_source_path( int $language_id, CanonicalPath $path ): ?object {
		$row = $this->find_by_source_path( $language_id, $path );
		if ( null === $row || 'active' !== (string) ( $row->route_status ?? '' ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Deletes a route by object/language.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @param int    $language_id Language id.
	 */
	public function delete_by_object( string $source_type, int $source_id, int $language_id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::slug_routes(),
			array(
				'source_type' => $source_type,
				'source_id'   => $source_id,
				'language_id' => $language_id,
			),
			array( '%s', '%d', '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Locks the current route row for an object/language (FOR UPDATE).
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @param int    $language_id Language id.
	 */
	public function lock_by_object( string $source_type, int $source_id, int $language_id ): ?object {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier only.
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::slug_routes() . '
				WHERE source_type = %s AND source_id = %d AND language_id = %d
				LIMIT 1 FOR UPDATE',
				$source_type,
				$source_id,
				$language_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return null === $row ? null : $row;
	}

	/**
	 * Language ids that have a prepared route for the source object.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @return array<int, int>
	 */
	public function list_language_ids_for_source( string $source_type, int $source_id ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier only.
		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT language_id FROM ' . Schema::slug_routes() . '
				WHERE source_type = %s AND source_id = %d',
				$source_type,
				$source_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( 'intval', $rows );
	}

	/**
	 * Deletes all routes for a source object.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 */
	public function delete_by_source( string $source_type, int $source_id ): int {
		global $wpdb;

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::slug_routes(),
			array(
				'source_type' => $source_type,
				'source_id'   => $source_id,
			),
			array( '%s', '%d' )
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Sets route_status for an object/language.
	 *
	 * @param string $source_type  Source type.
	 * @param int    $source_id    Source id.
	 * @param int    $language_id  Language id.
	 * @param string $route_status New status.
	 */
	public function set_status( string $source_type, int $source_id, int $language_id, string $route_status ): bool {
		global $wpdb;

		$ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::slug_routes(),
			array(
				'route_status' => $route_status,
				'updated_at'   => current_time( 'mysql', true ),
			),
			array(
				'source_type' => $source_type,
				'source_id'   => $source_id,
				'language_id' => $language_id,
			),
			array( '%s', '%s' ),
			array( '%s', '%d', '%d' )
		);

		return false !== $ok;
	}

	/**
	 * Updates an existing route row.
	 *
	 * @param int         $route_id        Route id.
	 * @param RouteRecord $record          Record.
	 * @param string      $source_path     Source path string.
	 * @param string      $localized_path  Localized path string.
	 * @param PathHash    $source_hash     Derived source hash.
	 * @param PathHash    $localized_hash  Derived localized hash.
	 * @param string      $now             Timestamp.
	 * @return object|WP_Error
	 */
	private function update_row(
		int $route_id,
		RouteRecord $record,
		string $source_path,
		string $localized_path,
		PathHash $source_hash,
		PathHash $localized_hash,
		string $now
	) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier only.
		$ok = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'UPDATE ' . Schema::slug_routes() . '
				SET source_subtype = %s,
					source_path = %s,
					source_path_hash = UNHEX(%s),
					localized_path = %s,
					localized_path_hash = UNHEX(%s),
					localized_slug = %s,
					route_namespace = %s,
					slug_origin = %s,
					route_status = %s,
					activated_at = %s,
					updated_at = %s
				WHERE route_id = %d',
				$record->source_subtype,
				$source_path,
				$source_hash->hex(),
				$localized_path,
				$localized_hash->hex(),
				$record->localized_slug,
				$record->route_namespace,
				$record->slug_origin,
				$record->route_status,
				$record->activated_at,
				$now,
				$route_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $ok ) {
			return new WP_Error( 'route_update_failed', 'Failed to update slug route.' );
		}

		return $this->find_by_object( $record->source_type, $record->source_id, $record->language_id );
	}

	/**
	 * Hash-indexed lookup with mandatory full-path verification.
	 *
	 * @param int           $language_id Language id.
	 * @param CanonicalPath $path        Expected canonical path.
	 * @param string        $hash_column Hash column name.
	 * @param string        $path_column Path column name.
	 */
	private function find_by_path_hash_column(
		int $language_id,
		CanonicalPath $path,
		string $hash_column,
		string $path_column
	): ?object {
		global $wpdb;

		$hash = PathHash::from_canonical( $path );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier and trusted column names only.
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::slug_routes() . '
				WHERE language_id = %d AND ' . $hash_column . ' = UNHEX(%s)
				LIMIT 1',
				$language_id,
				$hash->hex()
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $row ) {
			return null;
		}

		$stored_path = (string) ( $row->{$path_column} ?? '' );
		if ( $stored_path !== $path->to_string() ) {
			return null;
		}

		return $row;
	}
}
