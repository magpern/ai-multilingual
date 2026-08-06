<?php
/**
 * Background translation job persistence.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Database\Schema;
use WP_Error;

/**
 * Repository for aiml_jobs rows. Orchestration state only — no bodies or prompts.
 */
final class BackgroundTranslationJobRepository {

	/**
	 * Fields that must never be written to job rows.
	 *
	 * @var string[]
	 */
	private const FORBIDDEN_FIELDS = array(
		'prompt',
		'source_body',
		'translated_body',
		'source_text',
		'translated_text',
		'api_key',
		'secret',
	);

	/**
	 * Insert a job row.
	 *
	 * @param array<string, mixed> $data Job payload.
	 * @return object|WP_Error
	 */
	public function insert( array $data ) {
		$validation = $this->validate_payload( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wpdb;

		$now    = current_time( 'mysql', true );
		$row    = $this->build_row( $data, $now );
		$fields = $this->insert_formats( $row );

		$ok = $wpdb->insert(
			Schema::jobs(),
			$row,
			$fields
		);

		if ( false === $ok ) {
			return $this->map_insert_error( (string) $wpdb->last_error );
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Find a job by id.
	 *
	 * @param int $job_id Job id.
	 */
	public function find( int $job_id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::jobs() . ' WHERE job_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL
				$job_id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * Find a job by idempotency key.
	 *
	 * @param string $key Idempotency key.
	 */
	public function find_by_idempotency_key( string $key ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::jobs() . ' WHERE idempotency_key = %s', // phpcs:ignore WordPress.DB.PreparedSQL
				$key
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * Find an active job by lock key.
	 *
	 * @param string $lock_key Active lock key value.
	 */
	public function find_active_by_lock_key( string $lock_key ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::jobs() . ' WHERE active_lock_key = %s', // phpcs:ignore WordPress.DB.PreparedSQL
				$lock_key
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * Update a job row.
	 *
	 * @param int                  $job_id Job id.
	 * @param array<string, mixed> $fields Fields to update.
	 * @return object|WP_Error|null Null when no-op.
	 */
	public function update( int $job_id, array $fields ) {
		$validation = $this->validate_payload( $fields );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wpdb;

		$existing = $this->find( $job_id );
		if ( null === $existing ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		$update  = array();
		$formats = array();

		foreach ( $this->updatable_fields() as $key => $format ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}

			$value = $this->normalize_field_value( $key, $fields[ $key ] );
			if ( is_wp_error( $value ) ) {
				return $value;
			}

			if ( $this->field_values_equal( $existing, $key, $value ) ) {
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
			Schema::jobs(),
			$update,
			array( 'job_id' => $job_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $ok ) {
			return $this->map_update_error( (string) $wpdb->last_error );
		}

		return $this->find( $job_id );
	}

	/**
	 * Reject forbidden payload keys and validate checkpoint when present.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @return true|WP_Error
	 */
	private function validate_payload( array $data ) {
		foreach ( array_keys( $data ) as $key ) {
			$key_string = strtolower( (string) $key );
			if ( in_array( $key_string, self::FORBIDDEN_FIELDS, true ) ) {
				return new WP_Error( 'job_invalid_payload', 'Job payload contains forbidden field.' );
			}
		}

		if ( ! array_key_exists( 'checkpoint', $data ) ) {
			return true;
		}

		$checkpoint = $data['checkpoint'];
		if ( null === $checkpoint || '' === $checkpoint ) {
			return true;
		}

		if ( is_array( $checkpoint ) ) {
			$encoded = JobCheckpoint::encode( $checkpoint );
			if ( is_wp_error( $encoded ) ) {
				return $encoded;
			}
			return true;
		}

		if ( is_string( $checkpoint ) ) {
			$decoded = JobCheckpoint::decode( $checkpoint );
			$encoded = JobCheckpoint::encode( $decoded );
			if ( is_wp_error( $encoded ) ) {
				return $encoded;
			}
			return true;
		}

		return new WP_Error( 'job_invalid_payload', 'Job checkpoint must be an array or JSON string.' );
	}

	/**
	 * Build insert row with defaults.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @param string               $now  UTC timestamp.
	 * @return array<string, mixed>
	 */
	private function build_row( array $data, string $now ): array {
		$row = array(
			'job_type'                  => (string) ( $data['job_type'] ?? '' ),
			'status'                    => (string) ( $data['status'] ?? 'queued' ),
			'requested_action'          => (string) ( $data['requested_action'] ?? 'none' ),
			'batch_id'                  => isset( $data['batch_id'] ) ? (string) $data['batch_id'] : null,
			'idempotency_key'           => (string) ( $data['idempotency_key'] ?? '' ),
			'source_type'               => (string) ( $data['source_type'] ?? '' ),
			'source_id'                 => (int) ( $data['source_id'] ?? 0 ),
			'language_id'               => (int) ( $data['language_id'] ?? 0 ),
			'lock_key'                  => (string) ( $data['lock_key'] ?? '' ),
			'active_lock_key'           => array_key_exists( 'active_lock_key', $data ) ? $data['active_lock_key'] : null,
			'lease_owner'               => (string) ( $data['lease_owner'] ?? '' ),
			'lease_expires_at'          => $data['lease_expires_at'] ?? null,
			'lease_heartbeat_at'        => $data['lease_heartbeat_at'] ?? null,
			'stage'                     => (string) ( $data['stage'] ?? '' ),
			'checkpoint'                => $this->encode_checkpoint_field( $data['checkpoint'] ?? null ),
			'provider_id'               => (string) ( $data['provider_id'] ?? '' ),
			'prompt_profile'            => (string) ( $data['prompt_profile'] ?? '' ),
			'prompt_version'            => (string) ( $data['prompt_version'] ?? '' ),
			'provider_config_fp'        => (string) ( $data['provider_config_fp'] ?? '' ),
			'glossary_version_intended' => (int) ( $data['glossary_version_intended'] ?? 0 ),
			'glossary_version_actual'   => (int) ( $data['glossary_version_actual'] ?? 0 ),
			'total_items'               => (int) ( $data['total_items'] ?? 0 ),
			'queued_items'              => (int) ( $data['queued_items'] ?? 0 ),
			'running_items'             => (int) ( $data['running_items'] ?? 0 ),
			'completed_items'           => (int) ( $data['completed_items'] ?? 0 ),
			'failed_items'              => (int) ( $data['failed_items'] ?? 0 ),
			'skipped_items'             => (int) ( $data['skipped_items'] ?? 0 ),
			'stale_items'               => (int) ( $data['stale_items'] ?? 0 ),
			'cancelled_items'           => (int) ( $data['cancelled_items'] ?? 0 ),
			'budget_max_requests'       => (int) ( $data['budget_max_requests'] ?? 0 ),
			'budget_max_tokens'         => (int) ( $data['budget_max_tokens'] ?? 0 ),
			'budget_used_requests'      => (int) ( $data['budget_used_requests'] ?? 0 ),
			'budget_used_tokens'        => (int) ( $data['budget_used_tokens'] ?? 0 ),
			'budget_warning_pct'        => (int) ( $data['budget_warning_pct'] ?? 80 ),
			'attempt_count'             => (int) ( $data['attempt_count'] ?? 0 ),
			'last_error_code'           => (string) ( $data['last_error_code'] ?? '' ),
			'last_error_class'          => (string) ( $data['last_error_class'] ?? '' ),
			'last_error_message'        => (string) ( $data['last_error_message'] ?? '' ),
			'created_by'                => (int) ( $data['created_by'] ?? 0 ),
			'created_at'                => $now,
			'updated_at'                => $now,
			'started_at'                => $data['started_at'] ?? null,
			'finished_at'               => $data['finished_at'] ?? null,
		);

		if ( null === $row['active_lock_key'] ) {
			$row['active_lock_key'] = null;
		} else {
			$row['active_lock_key'] = (string) $row['active_lock_key'];
		}

		return $row;
	}

	/**
	 * Format map for insert().
	 *
	 * @param array<string, mixed> $row Insert row.
	 * @return string[]
	 */
	private function insert_formats( array $row ): array {
		$formats = array();
		foreach ( array_keys( $row ) as $key ) {
			$formats[] = $this->field_format( $key );
		}

		return $formats;
	}

	/**
	 * Updatable columns and their $wpdb formats.
	 *
	 * @return array<string, string>
	 */
	private function updatable_fields(): array {
		return array(
			'job_type'                  => '%s',
			'status'                    => '%s',
			'requested_action'          => '%s',
			'batch_id'                  => '%s',
			'source_type'               => '%s',
			'source_id'                 => '%d',
			'language_id'               => '%d',
			'lock_key'                  => '%s',
			'active_lock_key'           => '%s',
			'lease_owner'               => '%s',
			'lease_expires_at'          => '%s',
			'lease_heartbeat_at'        => '%s',
			'stage'                     => '%s',
			'checkpoint'                => '%s',
			'provider_id'               => '%s',
			'prompt_profile'            => '%s',
			'prompt_version'            => '%s',
			'provider_config_fp'        => '%s',
			'glossary_version_intended' => '%d',
			'glossary_version_actual'   => '%d',
			'total_items'               => '%d',
			'queued_items'              => '%d',
			'running_items'             => '%d',
			'completed_items'           => '%d',
			'failed_items'              => '%d',
			'skipped_items'             => '%d',
			'stale_items'               => '%d',
			'cancelled_items'           => '%d',
			'budget_max_requests'       => '%d',
			'budget_max_tokens'         => '%d',
			'budget_used_requests'      => '%d',
			'budget_used_tokens'        => '%d',
			'budget_warning_pct'        => '%d',
			'attempt_count'             => '%d',
			'last_error_code'           => '%s',
			'last_error_class'          => '%s',
			'last_error_message'        => '%s',
			'started_at'                => '%s',
			'finished_at'               => '%s',
		);
	}

	/**
	 * Normalize a single field value for update.
	 *
	 * @param string $key   Field name.
	 * @param mixed  $value Raw value.
	 * @return mixed|WP_Error
	 */
	private function normalize_field_value( string $key, $value ) {
		if ( 'checkpoint' === $key ) {
			return $this->encode_checkpoint_field( $value );
		}

		if ( 'active_lock_key' === $key ) {
			return null === $value ? null : (string) $value;
		}

		$format = $this->field_format( $key );
		if ( '%d' === $format ) {
			return (int) $value;
		}

		return (string) $value;
	}

	/**
	 * Encode checkpoint for DB storage.
	 *
	 * @param mixed $checkpoint Raw checkpoint value.
	 * @return string|null|WP_Error
	 */
	private function encode_checkpoint_field( $checkpoint ) {
		if ( null === $checkpoint || '' === $checkpoint ) {
			return null;
		}

		if ( is_string( $checkpoint ) ) {
			$checkpoint = JobCheckpoint::decode( $checkpoint );
		}

		if ( ! is_array( $checkpoint ) ) {
			return new WP_Error( 'job_invalid_payload', 'Job checkpoint must be an array or JSON string.' );
		}

		return JobCheckpoint::encode( $checkpoint );
	}

	/**
	 * $wpdb format for a column.
	 *
	 * @param string $key Column name.
	 */
	private function field_format( string $key ): string {
		$int_fields = array(
			'source_id',
			'language_id',
			'glossary_version_intended',
			'glossary_version_actual',
			'total_items',
			'queued_items',
			'running_items',
			'completed_items',
			'failed_items',
			'skipped_items',
			'stale_items',
			'cancelled_items',
			'budget_max_requests',
			'budget_max_tokens',
			'budget_used_requests',
			'budget_used_tokens',
			'budget_warning_pct',
			'attempt_count',
			'created_by',
		);

		return in_array( $key, $int_fields, true ) ? '%d' : '%s';
	}

	/**
	 * Whether an existing row value equals the candidate update.
	 *
	 * @param object $existing Existing row.
	 * @param string $key      Field name.
	 * @param mixed  $value    Candidate value.
	 */
	private function field_values_equal( object $existing, string $key, $value ): bool {
		if ( ! property_exists( $existing, $key ) ) {
			return false;
		}

		$current = $existing->{$key};
		if ( null === $current && null === $value ) {
			return true;
		}

		return (string) $current === (string) $value;
	}

	/**
	 * Map insert duplicate errors to stable codes.
	 *
	 * @param string $error MySQL error text.
	 * @return WP_Error
	 */
	private function map_insert_error( string $error ): WP_Error {
		if ( $this->is_duplicate_for_key( $error, 'idempotency_key' ) ) {
			return new WP_Error( 'job_idempotency_conflict', 'Duplicate job idempotency key.' );
		}

		if ( $this->is_duplicate_for_key( $error, 'active_lock_key' ) ) {
			return new WP_Error( 'job_lock_key_conflict', 'Duplicate active lock key.' );
		}

		return new WP_Error( 'job_insert_failed', 'Failed to insert job.' );
	}

	/**
	 * Map update duplicate errors to stable codes.
	 *
	 * @param string $error MySQL error text.
	 * @return WP_Error
	 */
	private function map_update_error( string $error ): WP_Error {
		if ( $this->is_duplicate_for_key( $error, 'active_lock_key' ) ) {
			return new WP_Error( 'job_lock_key_conflict', 'Duplicate active lock key.' );
		}

		return new WP_Error( 'job_update_failed', 'Failed to update job.' );
	}

	/**
	 * Whether a DB error indicates a duplicate on a named key.
	 *
	 * @param string $error MySQL error text.
	 * @param string $key   Index / key name.
	 */
	private function is_duplicate_for_key( string $error, string $key ): bool {
		if ( false === stripos( $error, 'Duplicate' ) && false === stripos( $error, '1062' ) ) {
			return false;
		}

		return false !== stripos( $error, $key );
	}
}
