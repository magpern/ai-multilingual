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
}
