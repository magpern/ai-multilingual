<?php
/**
 * Hierarchy reindex frontier persistence.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Database\Schema;
use WP_Error;

/**
 * Repository for aiml_slug_reindex_frontier checkpoints (MSEO.3 contract).
 */
final class ReindexFrontierRepository {

	/**
	 * Creates or coalesces a frontier row for a parent object.
	 *
	 * @param FrontierRecord $record Frontier checkpoint.
	 * @return object|WP_Error
	 */
	public function upsert_checkpoint( FrontierRecord $record ) {
		global $wpdb;

		$existing = $this->find_by_parent( $record->parent_source_type, $record->parent_source_id );
		$now      = current_time( 'mysql', true );

		if ( null !== $existing ) {
			$generation = (int) ( $existing->generation ?? 1 ) + 1;

			$ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				Schema::slug_reindex_frontier(),
				array(
					'checkpoint_json' => $record->checkpoint_json,
					'generation'      => $generation,
					'status'          => $record->status,
					'updated_at'      => $now,
				),
				array(
					'frontier_id' => (int) $existing->frontier_id,
				),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $ok ) {
				return new WP_Error( 'frontier_update_failed', 'Failed to update reindex frontier.' );
			}

			return $this->find_by_parent( $record->parent_source_type, $record->parent_source_id );
		}

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::slug_reindex_frontier(),
			array(
				'parent_source_type' => $record->parent_source_type,
				'parent_source_id'   => $record->parent_source_id,
				'checkpoint_json'    => $record->checkpoint_json,
				'generation'         => $record->generation,
				'status'             => $record->status,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'frontier_insert_failed', 'Failed to insert reindex frontier.' );
		}

		return $this->find_by_parent( $record->parent_source_type, $record->parent_source_id );
	}

	/**
	 * Loads the frontier row for a parent object.
	 *
	 * @param string $parent_source_type Parent source type.
	 * @param int    $parent_source_id   Parent source id.
	 */
	public function find_by_parent( string $parent_source_type, int $parent_source_id ): ?object {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier only.
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::slug_reindex_frontier() . '
				WHERE parent_source_type = %s AND parent_source_id = %d
				LIMIT 1',
				$parent_source_type,
				$parent_source_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $row ) {
			return null;
		}

		return $row;
	}

	/**
	 * Oldest pending/running frontier for the worker.
	 */
	public function find_workable(): ?object {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier only.
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . Schema::slug_reindex_frontier() . "
			WHERE status IN ('pending','running')
			ORDER BY updated_at ASC
			LIMIT 1"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Lists recent frontiers for operator diagnostics (bounded).
	 *
	 * @param int $limit Max rows.
	 * @return list<object>
	 */
	public function list_recent( int $limit = 20 ): array {
		global $wpdb;

		$limit = max( 1, min( 100, $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema table + bounded limit.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::slug_reindex_frontier() . '
				ORDER BY updated_at DESC
				LIMIT %d',
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Updates checkpoint/status without bumping generation (mid-tick resume).
	 *
	 * @param string      $parent_source_type Parent source type.
	 * @param int         $parent_source_id   Parent source id.
	 * @param int         $generation         Expected generation.
	 * @param string|null $checkpoint_json    Checkpoint JSON.
	 * @param string      $status             Frontier status.
	 * @return true|WP_Error
	 */
	public function update_checkpoint(
		string $parent_source_type,
		int $parent_source_id,
		int $generation,
		?string $checkpoint_json,
		string $status
	) {
		global $wpdb;

		$ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::slug_reindex_frontier(),
			array(
				'checkpoint_json' => $checkpoint_json,
				'status'          => $status,
				'updated_at'      => current_time( 'mysql', true ),
			),
			array(
				'parent_source_type' => $parent_source_type,
				'parent_source_id'   => $parent_source_id,
				'generation'         => $generation,
			),
			array( '%s', '%s', '%s' ),
			array( '%s', '%d', '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'frontier_update_failed', 'Failed to update reindex frontier checkpoint.' );
		}

		return true;
	}
}
