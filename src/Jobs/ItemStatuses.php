<?php
/**
 * Background translation job item status constants.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Legal item status values and helpers (plan §6.2).
 */
final class ItemStatuses {

	public const QUEUED           = 'queued';
	public const RUNNING          = 'running';
	public const RETRY_WAIT       = 'retry_wait';
	public const COMPLETED        = 'completed';
	public const FAILED           = 'failed';
	public const STALE_SOURCE     = 'stale_source';
	public const SKIPPED_CONFLICT = 'skipped_conflict';
	public const CANCELLED        = 'cancelled';

	/**
	 * All known status values.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::QUEUED,
			self::RUNNING,
			self::RETRY_WAIT,
			self::COMPLETED,
			self::FAILED,
			self::STALE_SOURCE,
			self::SKIPPED_CONFLICT,
			self::CANCELLED,
		);
	}

	/**
	 * Whether an item status is terminal (immutable).
	 *
	 * @param string $status Item status code.
	 */
	public static function is_terminal( string $status ): bool {
		return in_array(
			$status,
			array(
				self::COMPLETED,
				self::FAILED,
				self::STALE_SOURCE,
				self::SKIPPED_CONFLICT,
				self::CANCELLED,
			),
			true
		);
	}

	/**
	 * Whether the terminal outcome counts as a successful completion.
	 *
	 * @param string $status Item status code.
	 */
	public static function is_success_terminal( string $status ): bool {
		return self::COMPLETED === $status;
	}

	/**
	 * Terminal outcomes that are not successful completions.
	 *
	 * @param string $status Item status code.
	 */
	public static function is_non_success_terminal( string $status ): bool {
		return self::is_terminal( $status ) && ! self::is_success_terminal( $status );
	}

	/**
	 * Statuses counted toward job skipped_items counter.
	 *
	 * @param string $status Item status code.
	 */
	public static function is_skipped_bucket( string $status ): bool {
		return self::SKIPPED_CONFLICT === $status;
	}

	/**
	 * Statuses counted toward job stale_items counter.
	 *
	 * @param string $status Item status code.
	 */
	public static function is_stale_bucket( string $status ): bool {
		return self::STALE_SOURCE === $status;
	}
}
