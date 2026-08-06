<?php
/**
 * Background translation job aggregate status constants.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Legal job status values and helpers (plan §6.1).
 */
final class JobStatuses {

	public const QUEUED                = 'queued';
	public const RUNNING               = 'running';
	public const PAUSED                = 'paused';
	public const RETRY_WAIT            = 'retry_wait';
	public const COMPLETED             = 'completed';
	public const COMPLETED_WITH_ERRORS = 'completed_with_errors';
	public const FAILED                = 'failed';
	public const CANCELLED             = 'cancelled';

	/**
	 * All known status values.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::QUEUED,
			self::RUNNING,
			self::PAUSED,
			self::RETRY_WAIT,
			self::COMPLETED,
			self::COMPLETED_WITH_ERRORS,
			self::FAILED,
			self::CANCELLED,
		);
	}

	/**
	 * Whether a job status is terminal (immutable).
	 *
	 * @param string $status Job status code.
	 */
	public static function is_terminal( string $status ): bool {
		return in_array(
			$status,
			array(
				self::COMPLETED,
				self::COMPLETED_WITH_ERRORS,
				self::FAILED,
				self::CANCELLED,
			),
			true
		);
	}

	/**
	 * Whether a job holds an active lock (non-terminal).
	 *
	 * Active jobs keep active_lock_key = lock_key until terminal.
	 *
	 * @param string $status Job status code.
	 */
	public static function is_active( string $status ): bool {
		return ! self::is_terminal( $status );
	}

	/**
	 * Statuses from which a worker may claim a lease.
	 *
	 * @return list<string>
	 */
	public static function claimable(): array {
		return array(
			self::QUEUED,
			self::RUNNING,
			self::RETRY_WAIT,
		);
	}
}
