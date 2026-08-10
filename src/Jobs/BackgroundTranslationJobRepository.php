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
	 * Count jobs with an active (non-expired) lease.
	 */
	public function count_stuck_leases(): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Schema::jobs() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE lease_owner != %s'
				. ' AND lease_expires_at IS NOT NULL'
				. ' AND lease_expires_at <= %s'
				. ' AND status IN (%s, %s, %s)',
				'',
				$now,
				JobStatuses::QUEUED,
				JobStatuses::RUNNING,
				JobStatuses::RETRY_WAIT
			)
		);

		return (int) $count;
	}

	/**
	 * Oldest queued job age stats (bounded).
	 *
	 * @return array{count: int, max_seconds: int}
	 */
	public function queue_age_stats(): array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) AS cnt, MIN(created_at) AS oldest FROM ' . Schema::jobs() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE status = %s',
				JobStatuses::QUEUED
			)
		);

		$count = null !== $row ? (int) ( $row->cnt ?? 0 ) : 0;
		if ( $count <= 0 || empty( $row->oldest ) ) {
			return array(
				'count'       => 0,
				'max_seconds' => 0,
			);
		}

		$oldest_ts = strtotime( (string) $row->oldest . ' UTC' );
		$age       = false === $oldest_ts ? 0 : max( 0, time() - $oldest_ts );

		return array(
			'count'       => $count,
			'max_seconds' => min( BackgroundTranslationDiagnostics::QUEUE_AGE_BOUND_SECONDS, $age ),
		);
	}

	/**
	 * Completed item throughput hints from finished jobs (last hour).
	 *
	 * @return array{completed_items_last_hour: int, jobs_finished_last_hour: int}
	 */
	public function throughput_stats(): array {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) AS jobs_finished, COALESCE(SUM(completed_items), 0) AS items_completed FROM ' . Schema::jobs() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE finished_at IS NOT NULL AND finished_at >= %s'
				. ' AND status IN (%s, %s, %s)',
				$since,
				JobStatuses::COMPLETED,
				JobStatuses::COMPLETED_WITH_ERRORS,
				JobStatuses::FAILED
			)
		);

		return array(
			'completed_items_last_hour' => null !== $row ? (int) ( $row->items_completed ?? 0 ) : 0,
			'jobs_finished_last_hour'   => null !== $row ? (int) ( $row->jobs_finished ?? 0 ) : 0,
		);
	}

	/**
	 * Counts of terminal jobs eligible for retention cleanup.
	 *
	 * @return array{completed: int, failed_or_cancelled: int}
	 */
	public function cleanup_backlog_counts(): array {
		$now              = time();
		$completed_cutoff = gmdate( 'Y-m-d H:i:s', $now - BackgroundTranslationRetentionCleanup::COMPLETED_RETENTION_SECONDS );
		$failed_cutoff    = gmdate( 'Y-m-d H:i:s', $now - BackgroundTranslationRetentionCleanup::FAILED_RETENTION_SECONDS );

		return array(
			'completed'           => $this->count_retention_candidates(
				array( JobStatuses::COMPLETED, JobStatuses::COMPLETED_WITH_ERRORS ),
				$completed_cutoff
			),
			'failed_or_cancelled' => $this->count_retention_candidates(
				array( JobStatuses::FAILED, JobStatuses::CANCELLED ),
				$failed_cutoff
			),
		);
	}

	/**
	 * Terminal, unleased jobs older than cutoff eligible for deletion.
	 *
	 * @param string[] $statuses Terminal statuses.
	 * @param string   $cutoff   UTC finished_at cutoff.
	 * @param int      $limit    Max rows.
	 * @return list<object>
	 */
	public function find_retention_candidates( array $statuses, string $cutoff, int $limit ): array {
		global $wpdb;

		if ( array() === $statuses || $limit <= 0 ) {
			return array();
		}

		$now          = current_time( 'mysql', true );
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge(
			$statuses,
			array( $cutoff, '', '', $now, $limit )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = 'SELECT * FROM ' . Schema::jobs()
			. ' WHERE status IN (' . $placeholders . ')'
			. ' AND finished_at IS NOT NULL AND finished_at <= %s'
			. ' AND (active_lock_key IS NULL OR active_lock_key = %s)'
			. ' AND (lease_owner = %s OR lease_expires_at IS NULL OR lease_expires_at <= %s)'
			. ' ORDER BY finished_at ASC LIMIT %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Count retention-eligible jobs for one cutoff bucket.
	 *
	 * @param string[] $statuses Terminal statuses.
	 * @param string   $cutoff   UTC finished_at cutoff.
	 */
	private function count_retention_candidates( array $statuses, string $cutoff ): int {
		global $wpdb;

		if ( array() === $statuses ) {
			return 0;
		}

		$now          = current_time( 'mysql', true );
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( $statuses, array( $cutoff, '', '', $now ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = 'SELECT COUNT(*) FROM ' . Schema::jobs()
			. ' WHERE status IN (' . $placeholders . ')'
			. ' AND finished_at IS NOT NULL AND finished_at <= %s'
			. ' AND (active_lock_key IS NULL OR active_lock_key = %s)'
			. ' AND (lease_owner = %s OR lease_expires_at IS NULL OR lease_expires_at <= %s)';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}

	/**
	 * Deletes one terminal job row when still eligible.
	 *
	 * @param int $job_id Job id.
	 * @return bool|WP_Error True when deleted, false when skipped.
	 */
	public function delete_terminal( int $job_id ) {
		global $wpdb;

		$job = $this->find( $job_id );
		if ( null === $job ) {
			return false;
		}

		if ( ! JobStatuses::is_terminal( (string) $job->status ) ) {
			return false;
		}

		if ( null !== $job->active_lock_key && '' !== (string) $job->active_lock_key ) {
			return false;
		}

		if ( '' !== (string) ( $job->lease_owner ?? '' ) ) {
			$expires = (string) ( $job->lease_expires_at ?? '' );
			if ( '' !== $expires && $expires > current_time( 'mysql', true ) ) {
				return false;
			}
		}

		$deleted = $wpdb->delete(
			Schema::jobs(),
			array( 'job_id' => $job_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'job_delete_failed', 'Failed to delete job.' );
		}

		return $deleted > 0;
	}

	/**
	 * Count jobs with a given status.
	 *
	 * @param string $status Job status.
	 */
	public function count_by_status( string $status ): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . Schema::jobs() . ' WHERE status = %s', // phpcs:ignore WordPress.DB.PreparedSQL
				$status
			)
		);

		return (int) $count;
	}

	/**
	 * Atomically transition queued/retry_wait work to running under a site cap.
	 *
	 * @param int    $job_id     Job id.
	 * @param string $from_status Expected current status.
	 * @param int    $max_running Maximum running jobs.
	 * @return object|WP_Error|null
	 */
	public function try_transition_to_running_under_cap( int $job_id, string $from_status, int $max_running ) {
		return $this->with_running_cap_lock(
			function () use ( $job_id, $from_status, $max_running ) {
				global $wpdb;

				$now   = current_time( 'mysql', true );
				$table = Schema::jobs();
				$sql   = 'UPDATE ' . $table . ' SET status = %s,'
					. ' started_at = IF(started_at IS NULL, %s, started_at), updated_at = %s'
					. ' WHERE job_id = %d AND status = %s'
					. ' AND (SELECT cnt FROM (SELECT COUNT(*) AS cnt FROM ' . $table . ' WHERE status = %s) AS running_count) < %d';

				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table name is from Schema and values are prepared.
				$updated = $wpdb->query(
					$wpdb->prepare(
						$sql,
						JobStatuses::RUNNING,
						$now,
						$now,
						$job_id,
						$from_status,
						JobStatuses::RUNNING,
						max( 1, $max_running )
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

				if ( false === $updated ) {
					return new WP_Error( 'job_update_failed', 'Failed to admit job into running.' );
				}
				if ( 0 === $updated ) {
					$current = $this->find( $job_id );
					if (
						null !== $current
						&& (string) $current->status === $from_status
						&& $this->count_by_status( JobStatuses::RUNNING ) >= $max_running
					) {
						return new WP_Error(
							BackgroundTranslationConcurrencyPolicy::ERROR_CODE,
							'Maximum concurrent running jobs reached.',
							array( 'status' => 409 )
						);
					}
					return null;
				}

				return $this->find( $job_id );
			}
		);
	}

	/**
	 * Query jobs with optional filters and pagination.
	 *
	 * @param array<string, mixed> $args Query arguments (status, batch_id, language_id, page, per_page).
	 * @return array{items: list<object>, total: int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}

		if ( ! empty( $args['batch_id'] ) ) {
			$where[]  = 'batch_id = %s';
			$params[] = (string) $args['batch_id'];
		}

		if ( ! empty( $args['language_id'] ) ) {
			$where[]  = 'language_id = %d';
			$params[] = (int) $args['language_id'];
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from Schema helper.
		$count_sql = 'SELECT COUNT(*) FROM ' . Schema::jobs() . ' WHERE ' . $where_sql;
		if ( array() !== $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, ...$params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $count_sql );

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$list_sql = 'SELECT * FROM ' . Schema::jobs() . ' WHERE ' . $where_sql . ' ORDER BY job_id DESC LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$list_sql = $wpdb->prepare( $list_sql, ...$list_params );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $list_sql );

		return array(
			'items' => is_array( $rows ) ? array_values( $rows ) : array(),
			'total' => $total,
		);
	}

	/**
	 * List jobs sharing a batch_id.
	 *
	 * @param string $batch_id Batch identifier.
	 * @return list<object>
	 */
	public function list_by_batch_id( string $batch_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::jobs() . ' WHERE batch_id = %s ORDER BY job_id ASC', // phpcs:ignore WordPress.DB.PreparedSQL
				$batch_id
			)
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Find jobs with expired leases.
	 *
	 * @param string $now UTC datetime (Y-m-d H:i:s).
	 * @return list<object>
	 */
	public function find_stale_leases( string $now ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::jobs() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE lease_owner != %s'
				. ' AND lease_expires_at IS NOT NULL'
				. ' AND lease_expires_at <= %s'
				. ' AND status IN (%s, %s, %s)'
				. ' ORDER BY job_id ASC',
				'',
				$now,
				JobStatuses::QUEUED,
				JobStatuses::RUNNING,
				JobStatuses::RETRY_WAIT
			)
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Atomically claim a job lease and transition queued/retry_wait work to running.
	 *
	 * @param int    $job_id       Job id.
	 * @param string $owner_token  Worker lease owner token.
	 * @param int    $ttl_seconds  Lease TTL.
	 * @param string $now          UTC datetime.
	 * @return object|WP_Error|null Updated row, null when not claimable, or error.
	 */
	public function claim_lease( int $job_id, string $owner_token, int $ttl_seconds, string $now ) {
		return $this->with_running_cap_lock(
			function () use ( $job_id, $owner_token, $ttl_seconds, $now ) {
				return $this->claim_lease_unlocked( $job_id, $owner_token, $ttl_seconds, $now );
			}
		);
	}

	/**
	 * Lease claim body executed under the running-cap advisory lock.
	 *
	 * @param int    $job_id       Job id.
	 * @param string $owner_token  Worker lease owner token.
	 * @param int    $ttl_seconds  Lease TTL.
	 * @param string $now          UTC datetime.
	 * @return object|WP_Error|null
	 */
	private function claim_lease_unlocked( int $job_id, string $owner_token, int $ttl_seconds, string $now ) {
		global $wpdb;

		$expires = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC' ) + $ttl_seconds );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from Schema helper.
		$sql = 'UPDATE ' . Schema::jobs() . ' SET '
			. 'lease_owner = %s, lease_expires_at = %s, lease_heartbeat_at = %s, updated_at = %s, '
			. 'status = IF(status IN (%s, %s), %s, status), '
			. 'started_at = IF(started_at IS NULL, %s, started_at) '
			. 'WHERE job_id = %d '
			. 'AND status IN (%s, %s, %s) '
			. 'AND requested_action = %s '
			. 'AND (lease_owner = %s OR lease_owner = %s OR lease_expires_at IS NULL OR lease_expires_at <= %s) '
			. 'AND (status = %s OR '
			. '(SELECT cnt FROM (SELECT COUNT(*) AS cnt FROM ' . Schema::jobs()
			. ' WHERE status = %s) AS running_count) < %d)';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$prepared = $wpdb->prepare(
			$sql,
			$owner_token,
			$expires,
			$now,
			$now,
			JobStatuses::QUEUED,
			JobStatuses::RETRY_WAIT,
			JobStatuses::RUNNING,
			$now,
			$job_id,
			JobStatuses::QUEUED,
			JobStatuses::RUNNING,
			JobStatuses::RETRY_WAIT,
			RequestedActions::NONE,
			'',
			$owner_token,
			$now,
			JobStatuses::RUNNING,
			JobStatuses::RUNNING,
			JobBounds::MAX_CONCURRENT_RUNNING
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$updated = $wpdb->query( $prepared );

		if ( false === $updated ) {
			return new WP_Error( 'job_lease_claim_failed', 'Failed to claim job lease.' );
		}

		if ( 0 === $updated ) {
			$current = $this->find( $job_id );
			if (
				null !== $current
				&& in_array( (string) $current->status, array( JobStatuses::QUEUED, JobStatuses::RETRY_WAIT ), true )
				&& $this->count_by_status( JobStatuses::RUNNING ) >= JobBounds::MAX_CONCURRENT_RUNNING
			) {
				return new WP_Error(
					BackgroundTranslationConcurrencyPolicy::ERROR_CODE,
					'Maximum concurrent running jobs reached.',
					array( 'status' => 409 )
				);
			}
			return null;
		}

		return $this->find( $job_id );
	}

	/**
	 * Extend an active lease owned by the given token.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $owner_token Lease owner token.
	 * @param int    $ttl_seconds Lease TTL.
	 * @param string $now         UTC datetime.
	 * @return object|WP_Error|null
	 */
	public function heartbeat_lease( int $job_id, string $owner_token, int $ttl_seconds, string $now ) {
		global $wpdb;

		$expires = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC' ) + $ttl_seconds );

		$updated = $wpdb->update(
			Schema::jobs(),
			array(
				'lease_expires_at'   => $expires,
				'lease_heartbeat_at' => $now,
				'updated_at'         => $now,
			),
			array(
				'job_id'      => $job_id,
				'lease_owner' => $owner_token,
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'job_lease_heartbeat_failed', 'Failed to heartbeat job lease.' );
		}

		if ( 0 === $updated ) {
			$job = $this->find( $job_id );
			if ( null !== $job && (string) $job->lease_owner === $owner_token ) {
				return $job;
			}

			return null;
		}

		return $this->find( $job_id );
	}

	/**
	 * Release a lease without clearing active_lock_key.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $owner_token Lease owner token.
	 * @return object|WP_Error|null
	 */
	public function release_lease( int $job_id, string $owner_token ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$updated = $wpdb->update(
			Schema::jobs(),
			array(
				'lease_owner'        => '',
				'lease_expires_at'   => null,
				'lease_heartbeat_at' => null,
				'updated_at'         => $now,
			),
			array(
				'job_id'      => $job_id,
				'lease_owner' => $owner_token,
			),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'job_lease_release_failed', 'Failed to release job lease.' );
		}

		if ( 0 === $updated ) {
			return null;
		}

		return $this->find( $job_id );
	}

	/**
	 * Archive idempotency key so a new job may reuse the canonical digest.
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error|null
	 */
	public function archive_idempotency_key( int $job_id ) {
		$job = $this->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		$archived = hash( 'sha256', (string) $job->idempotency_key . '|archived|' . $job_id );

		return $this->update(
			$job_id,
			array(
				'idempotency_key' => $archived,
			)
		);
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
	 * Atomically increment budget usage counters.
	 *
	 * @param int $job_id   Job id.
	 * @param int $requests Request units to add.
	 * @param int $tokens   Token units to add.
	 * @return object|WP_Error
	 */
	public function increment_budget_usage( int $job_id, int $requests, int $tokens ) {
		global $wpdb;

		$requests = max( 0, $requests );
		$tokens   = max( 0, $tokens );
		$now      = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::jobs() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' SET budget_used_requests = budget_used_requests + %d,'
				. ' budget_used_tokens = budget_used_tokens + %d,'
				. ' updated_at = %s'
				. ' WHERE job_id = %d',
				$requests,
				$tokens,
				$now,
				$job_id
			)
		);

		if ( false === $updated ) {
			return new WP_Error( 'job_budget_update_failed', 'Failed to record job budget usage.' );
		}

		if ( 0 === $updated ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		$job = $this->find( $job_id );
		if ( null === $job ) {
			return new WP_Error( 'job_not_found', 'Job not found.' );
		}

		return $job;
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
			'idempotency_key'           => '%s',
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
		if ( ! $this->is_duplicate_error( $error ) ) {
			return new WP_Error( 'job_insert_failed', 'Failed to insert job.' );
		}

		if ( $this->is_duplicate_for_key( $error, 'idempotency_key' ) ) {
			return new WP_Error( 'job_idempotency_conflict', 'Duplicate job idempotency key.' );
		}

		return new WP_Error( 'job_lock_key_conflict', 'Duplicate active lock key.' );
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
		if ( ! $this->is_duplicate_error( $error ) ) {
			return false;
		}

		return false !== stripos( $error, $key );
	}

	/**
	 * Whether a DB error string indicates a unique-key collision.
	 *
	 * @param string $error MySQL error text.
	 */
	private function is_duplicate_error( string $error ): bool {
		return false !== stripos( $error, 'Duplicate' ) || false !== stripos( $error, '1062' );
	}

	/**
	 * Serialize site running-cap admissions via a MySQL named lock (no schema).
	 *
	 * @param callable $callback Work that counts/transitions running jobs.
	 * @return mixed
	 */
	private function with_running_cap_lock( callable $callback ) {
		global $wpdb;

		$lock_name = 'aiml_jobs_running_cap';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- lock name is a fixed literal.
		$got = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 ) );
		if ( 1 !== $got ) {
			return new WP_Error(
				BackgroundTranslationConcurrencyPolicy::ERROR_CODE,
				'Maximum concurrent running jobs reached.',
				array( 'status' => 409 )
			);
		}

		try {
			return $callback();
		} finally {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- lock name is a fixed literal.
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}
}
