<?php
/**
 * Operator-requested job actions (separate from job status).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Requested_action column values (plan §6.3).
 */
final class RequestedActions {

	public const NONE   = 'none';
	public const PAUSE  = 'pause';
	public const CANCEL = 'cancel';

	/**
	 * All known requested_action values.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::NONE,
			self::PAUSE,
			self::CANCEL,
		);
	}

	/**
	 * Whether an operator has requested pause or cancel.
	 *
	 * @param string $action Requested action code.
	 */
	public static function is_pending( string $action ): bool {
		return self::NONE !== $action;
	}
}
