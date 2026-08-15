<?php
/**
 * Bounded product discovery for MSEO.4 dependency rematerialization.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Routing;

/**
 * Over-inclusive product assignment discovery (not Woo category selection).
 */
final class WooProductDependencyRepository {

	/**
	 * Products assigned to a product_cat term or its descendants, after cursor.
	 *
	 * @param int $term_id  Root category term id.
	 * @param int $after_id Last product id cursor.
	 * @param int $limit    Bound.
	 * @return list<int>
	 */
	public function list_products_for_category_subtree( int $term_id, int $after_id, int $limit ): array {
		global $wpdb;

		$term_ids = array( $term_id );
		$children = get_term_children( $term_id, 'product_cat' );
		if ( is_array( $children ) ) {
			foreach ( $children as $child ) {
				$term_ids[] = (int) $child;
			}
		}
		$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
		if ( array() === $term_ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
		$params       = array_merge( $term_ids, array( $after_id, max( 1, $limit ) ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare(
			"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} AS tr
			INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->posts} AS p ON p.ID = tr.object_id
			WHERE tt.taxonomy = 'product_cat'
			  AND tt.term_id IN ($placeholders)
			  AND p.post_type = 'product'
			  AND p.ID > %d
			ORDER BY p.ID ASC
			LIMIT %d",
			...$params
		);
		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:enable

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Published/private/draft product IDs after a cursor.
	 *
	 * @param int $after_id After product id.
	 * @param int $limit    Bound.
	 * @return list<int>
	 */
	public function list_all_products_after( int $after_id, int $limit ): array {
		global $wpdb;

		$limit = max( 1, min( 50, $limit ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'product'
				  AND post_status IN ('publish','private','draft')
				  AND ID > %d
				ORDER BY ID ASC
				LIMIT %d",
				$after_id,
				$limit
			)
		);
		// phpcs:enable

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}
}
