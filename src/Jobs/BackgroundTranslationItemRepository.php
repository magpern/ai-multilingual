<?php
/**
 * Background translation job item persistence.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Database\Schema;
use WP_Error;

/**
 * Repository for aiml_job_items rows. No translation bodies or prompts.
 */
final class BackgroundTranslationItemRepository {

	/**
	 * Fields that must never be written to item rows.
	 *
	 * @var string[]
	 */
	private const FORBIDDEN_FIELDS = array(
		'prompt',
		'body',
		'source_body',
		'translated_body',
		'source_text',
		'translated_text',
		'api_key',
		'secret',
	);

	/**
	 * Insert a job item row.
	 *
	 * @param array<string, mixed> $data Item payload.
	 * @return object|WP_Error
	 */
	public function insert( array $data ) {
		$validation = $this->validate_payload( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wpdb;

		$now = current_time( 'mysql', true );
		$row = array(
			'job_id'                    => (int) ( $data['job_id'] ?? 0 ),
			'segment_key'               => (string) ( $data['segment_key'] ?? '' ),
			'status'                    => (string) ( $data['status'] ?? 'queued' ),
			'result_code'               => (string) ( $data['result_code'] ?? '' ),
			'attempt_count'             => (int) ( $data['attempt_count'] ?? 0 ),
			'source_hash_captured'      => (string) ( $data['source_hash_captured'] ?? '' ),
			'translation_hash_captured' => (string) ( $data['translation_hash_captured'] ?? '' ),
			'glossary_version_actual'   => (int) ( $data['glossary_version_actual'] ?? 0 ),
			'last_error_code'           => (string) ( $data['last_error_code'] ?? '' ),
			'last_error_class'          => (string) ( $data['last_error_class'] ?? '' ),
			'last_error_message'        => (string) ( $data['last_error_message'] ?? '' ),
			'created_at'                => $now,
			'updated_at'                => $now,
			'started_at'                => $data['started_at'] ?? null,
			'finished_at'               => $data['finished_at'] ?? null,
		);

		$ok = $wpdb->insert(
			Schema::job_items(),
			$row,
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			if ( $this->is_duplicate_error( (string) $wpdb->last_error ) ) {
				return new WP_Error( 'job_item_duplicate', 'Duplicate job item for segment key.' );
			}

			return new WP_Error( 'job_item_insert_failed', 'Failed to insert job item.' );
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Find an item by id.
	 *
	 * @param int $item_id Item id.
	 */
	public function find( int $item_id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::job_items() . ' WHERE item_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL
				$item_id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * Find an item by job and segment key.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $segment_key Segment key.
	 */
	public function find_by_job_and_segment( int $job_id, string $segment_key ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::job_items() . ' WHERE job_id = %d AND segment_key = %s', // phpcs:ignore WordPress.DB.PreparedSQL
				$job_id,
				$segment_key
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * List items for a job, optionally filtered by status.
	 *
	 * @param int         $job_id Job id.
	 * @param string|null $status Optional status filter.
	 * @return list<object>
	 */
	public function list_by_job( int $job_id, ?string $status = null ): array {
		global $wpdb;

		if ( null !== $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . Schema::job_items() . ' WHERE job_id = %d AND status = %s ORDER BY item_id ASC', // phpcs:ignore WordPress.DB.PreparedSQL
					$job_id,
					$status
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . Schema::job_items() . ' WHERE job_id = %d ORDER BY item_id ASC', // phpcs:ignore WordPress.DB.PreparedSQL
					$job_id
				)
			);
		}

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Update a job item row.
	 *
	 * @param int                  $item_id Item id.
	 * @param array<string, mixed> $fields  Fields to update.
	 * @return object|WP_Error|null Null when no-op.
	 */
	public function update( int $item_id, array $fields ) {
		$validation = $this->validate_payload( $fields );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wpdb;

		$existing = $this->find( $item_id );
		if ( null === $existing ) {
			return new WP_Error( 'job_item_not_found', 'Job item not found.' );
		}

		$map = array(
			'status'                    => '%s',
			'result_code'               => '%s',
			'attempt_count'             => '%d',
			'source_hash_captured'      => '%s',
			'translation_hash_captured' => '%s',
			'glossary_version_actual'   => '%d',
			'last_error_code'           => '%s',
			'last_error_class'          => '%s',
			'last_error_message'        => '%s',
			'started_at'                => '%s',
			'finished_at'               => '%s',
		);

		$update  = array();
		$formats = array();

		foreach ( $map as $key => $format ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}

			$value = '%d' === $format ? (int) $fields[ $key ] : (string) $fields[ $key ];
			if ( (string) $existing->{$key} === (string) $value ) {
				continue;
			}

			$update[ $key ] = $value;
			$formats[]      = $format;
		}

		if ( array() === $update ) {
			return null;
		}

		$update['updated_at'] = current_time( 'mysql', true );
		$formats[]            = '%s';

		$ok = $wpdb->update(
			Schema::job_items(),
			$update,
			array( 'item_id' => $item_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			if ( $this->is_duplicate_error( (string) $wpdb->last_error ) ) {
				return new WP_Error( 'job_item_duplicate', 'Duplicate job item for segment key.' );
			}

			return new WP_Error( 'job_item_update_failed', 'Failed to update job item.' );
		}

		return $this->find( $item_id );
	}

	/**
	 * Count items grouped by status for a job.
	 *
	 * @param int $job_id Job id.
	 * @return array<string, int>
	 */
	public function count_by_status( int $job_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS cnt FROM ' . Schema::job_items() . ' WHERE job_id = %d GROUP BY status', // phpcs:ignore WordPress.DB.PreparedSQL
				$job_id
			)
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->status ] = (int) $row->cnt;
		}

		return $out;
	}

	/**
	 * Reset running items for a job back to queued (stale lease recovery).
	 *
	 * @param int $job_id Job id.
	 * @return int Number of rows updated.
	 */
	public function reset_running_to_queued( int $job_id ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::job_items() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' SET status = %s, updated_at = %s, started_at = NULL'
				. ' WHERE job_id = %d AND status = %s',
				ItemStatuses::QUEUED,
				$now,
				$job_id,
				ItemStatuses::RUNNING
			)
		);

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * Reject forbidden payload keys.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @return true|WP_Error
	 */
	private function validate_payload( array $data ) {
		foreach ( array_keys( $data ) as $key ) {
			$key_string = strtolower( (string) $key );
			if ( in_array( $key_string, self::FORBIDDEN_FIELDS, true ) ) {
				return new WP_Error( 'job_invalid_payload', 'Job item payload contains forbidden field.' );
			}
		}

		return true;
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
