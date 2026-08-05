<?php
/**
 * Glossary lexicon persistence.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Glossary;

use AIMultilingual\Database\Schema;
use WP_Error;

/**
 * Repository for aiml_glossary rows. No business matching rules.
 */
final class GlossaryRepository {

	/**
	 * Insert a glossary row.
	 *
	 * @param array<string, mixed> $data Term payload.
	 * @return object|WP_Error
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			Schema::glossary(),
			array(
				'source_lang_id'         => (int) $data['source_lang_id'],
				'target_lang_id'         => (int) $data['target_lang_id'],
				'source_term'            => (string) $data['source_term'],
				'source_term_normalized' => (string) $data['source_term_normalized'],
				'target_term'            => (string) $data['target_term'],
				'context'                => (string) ( $data['context'] ?? '' ),
				'description'            => (string) ( $data['description'] ?? '' ),
				'is_active'              => ! empty( $data['is_active'] ) ? 1 : 0,
				'created_at'             => $now,
				'updated_at'             => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $ok ) {
			if ( $this->is_duplicate_error( (string) $wpdb->last_error ) ) {
				return new WP_Error( 'glossary_duplicate_term', 'Duplicate glossary term for language pair.' );
			}

			return new WP_Error( 'glossary_insert_failed', 'Failed to insert glossary term.' );
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Update a glossary row.
	 *
	 * @param int                  $glossary_id Term id.
	 * @param array<string, mixed> $data        Fields to update.
	 * @return object|WP_Error|null Null when no-op (identical).
	 */
	public function update( int $glossary_id, array $data ) {
		global $wpdb;

		$existing = $this->find( $glossary_id );
		if ( null === $existing ) {
			return new WP_Error( 'glossary_not_found', 'Glossary term not found.' );
		}

		$fields  = array();
		$formats = array();
		$map     = array(
			'source_lang_id'         => '%d',
			'target_lang_id'         => '%d',
			'source_term'            => '%s',
			'source_term_normalized' => '%s',
			'target_term'            => '%s',
			'context'                => '%s',
			'description'            => '%s',
			'is_active'              => '%d',
		);

		foreach ( $map as $key => $format ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$value = 'is_active' === $key ? ( ! empty( $data[ $key ] ) ? 1 : 0 ) : $data[ $key ];
			if ( (string) $existing->{$key} === (string) $value ) {
				continue;
			}
			$fields[ $key ] = $value;
			$formats[]      = $format;
		}

		if ( array() === $fields ) {
			return null;
		}

		$fields['updated_at'] = current_time( 'mysql', true );
		$formats[]            = '%s';

		$ok = $wpdb->update(
			Schema::glossary(),
			$fields,
			array( 'glossary_id' => $glossary_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			if ( $this->is_duplicate_error( (string) $wpdb->last_error ) ) {
				return new WP_Error( 'glossary_duplicate_term', 'Duplicate glossary term for language pair.' );
			}

			return new WP_Error( 'glossary_update_failed', 'Failed to update glossary term.' );
		}

		return $this->find( $glossary_id );
	}

	/**
	 * Delete a glossary row.
	 *
	 * @param int $glossary_id Term id.
	 */
	public function delete( int $glossary_id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete(
			Schema::glossary(),
			array( 'glossary_id' => $glossary_id ),
			array( '%d' )
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Find a glossary row by id.
	 *
	 * @param int $glossary_id Term id.
	 */
	public function find( int $glossary_id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::glossary() . ' WHERE glossary_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL
				$glossary_id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * Active terms for a language pair.
	 *
	 * @param int $source_lang_id Source language id.
	 * @param int $target_lang_id Target language id.
	 * @return list<object>
	 */
	public function list_active_for_pair( int $source_lang_id, int $target_lang_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::glossary() . ' WHERE source_lang_id = %d AND target_lang_id = %d AND is_active = 1', // phpcs:ignore WordPress.DB.PreparedSQL
				$source_lang_id,
				$target_lang_id
			)
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Paginated glossary query for admin listing.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{items:list<object>,total:int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( isset( $args['source_lang_id'] ) ) {
			$where[]  = 'source_lang_id = %d';
			$params[] = (int) $args['source_lang_id'];
		}
		if ( isset( $args['target_lang_id'] ) ) {
			$where[]  = 'target_lang_id = %d';
			$params[] = (int) $args['target_lang_id'];
		}
		if ( array_key_exists( 'is_active', $args ) && null !== $args['is_active'] ) {
			$where[]  = 'is_active = %d';
			$params[] = $args['is_active'] ? 1 : 0;
		}
		if ( ! empty( $args['q'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['q'] ) . '%';
			$where[]  = '(source_term LIKE %s OR target_term LIKE %s OR source_term_normalized LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$sql_count = 'SELECT COUNT(*) FROM ' . Schema::glossary() . ' WHERE ' . $where_sql;
		$total     = (int) ( array() === $params
			? $wpdb->get_var( $sql_count ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$sql         = 'SELECT * FROM ' . Schema::glossary() . ' WHERE ' . $where_sql . ' ORDER BY updated_at DESC, glossary_id DESC LIMIT %d OFFSET %d';
		$params_page = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $sql, $params_page ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return array(
			'items' => is_array( $rows ) ? array_values( $rows ) : array(),
			'total' => $total,
		);
	}

	/**
	 * Count active terms grouped by language pair.
	 *
	 * @return array<string, int>
	 */
	public function count_active_by_pair(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			'SELECT source_lang_id, target_lang_id, COUNT(*) AS cnt FROM ' . Schema::glossary() . ' WHERE is_active = 1 GROUP BY source_lang_id, target_lang_id' // phpcs:ignore WordPress.DB.PreparedSQL
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$key         = (int) $row->source_lang_id . ':' . (int) $row->target_lang_id;
			$out[ $key ] = (int) $row->cnt;
		}

		return $out;
	}

	/**
	 * Whether a DB error string indicates a unique-key collision.
	 *
	 * @param string $error MySQL error text.
	 */
	private function is_duplicate_error( string $error ): bool {
		return false !== stripos( $error, 'Duplicate' ) || false !== stripos( $error, '1062' );
	}
}
