<?php
/**
 * Bounded hierarchy child queries for DFS rematerialization.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Routing;

use AIMultilingual\Translation\Store;
use WP_Post;
use WP_Term;

/**
 * Direct-child cursors for O(depth) frontier traversal (I9 SQL home).
 */
final class HierarchyChildRepository {

	/**
	 * Lists direct child page/term ids after a cursor, bounded.
	 *
	 * @param string $source_type Parent source type.
	 * @param int    $source_id   Parent source id.
	 * @param int    $after_id    Last processed child id.
	 * @param int    $limit       Max rows.
	 * @return list<array{source_type: string, source_id: int}>
	 */
	public function direct_children_after( string $source_type, int $source_id, int $after_id, int $limit ): array {
		global $wpdb;

		$limit = max( 1, min( 50, $limit ) );

		if ( Store::SOURCE_POST === $source_type ) {
			$post = get_post( $source_id );
			if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
				return array();
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded DFS cursor.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_parent = %d
					  AND post_type = 'page'
					  AND ID > %d
					  AND post_status IN ('publish','private','draft')
					ORDER BY ID ASC
					LIMIT %d",
					$source_id,
					$after_id,
					$limit
				)
			);

			$out = array();
			foreach ( (array) $ids as $child_id ) {
				$out[] = array(
					'source_type' => Store::SOURCE_POST,
					'source_id'   => (int) $child_id,
				);
			}

			return $out;
		}

		if ( Store::SOURCE_TERM === $source_type ) {
			$term = get_term( $source_id );
			if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
				return array();
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded DFS cursor.
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT t.term_id FROM {$wpdb->terms} AS t
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id
					WHERE tt.taxonomy = %s
					  AND tt.parent = %d
					  AND t.term_id > %d
					ORDER BY t.term_id ASC
					LIMIT %d",
					(string) $term->taxonomy,
					$source_id,
					$after_id,
					$limit
				)
			);

			$out = array();
			foreach ( (array) $ids as $child_id ) {
				$out[] = array(
					'source_type' => Store::SOURCE_TERM,
					'source_id'   => (int) $child_id,
				);
			}

			return $out;
		}

		return array();
	}
}
