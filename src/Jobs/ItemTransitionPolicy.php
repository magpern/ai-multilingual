<?php
/**
 * Legal background translation job item status transitions.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Item transition matrix (plan §6.6).
 */
final class ItemTransitionPolicy {

	/**
	 * Whether a direct item status transition is legal.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 */
	public static function can_transition( string $from, string $to ): bool {
		if ( ItemStatuses::FAILED === $from && ItemStatuses::QUEUED === $to ) {
			return true;
		}

		if ( ItemStatuses::is_terminal( $from ) ) {
			return false;
		}

		if ( '' === $from && ItemStatuses::QUEUED === $to ) {
			return true;
		}

		if ( ItemStatuses::QUEUED === $from && ItemStatuses::RUNNING === $to ) {
			return true;
		}

		if ( ItemStatuses::RUNNING === $from && ItemStatuses::RETRY_WAIT === $to ) {
			return true;
		}

		if ( ItemStatuses::RETRY_WAIT === $from && in_array( $to, array( ItemStatuses::QUEUED, ItemStatuses::RUNNING ), true ) ) {
			return true;
		}

		if ( ItemStatuses::RUNNING === $from ) {
			return in_array(
				$to,
				array(
					ItemStatuses::COMPLETED,
					ItemStatuses::FAILED,
					ItemStatuses::STALE_SOURCE,
					ItemStatuses::SKIPPED_CONFLICT,
					ItemStatuses::CANCELLED,
				),
				true
			);
		}

		if ( ItemStatuses::CANCELLED === $to ) {
			return in_array(
				$from,
				array(
					ItemStatuses::QUEUED,
					ItemStatuses::RETRY_WAIT,
					ItemStatuses::RUNNING,
				),
				true
			);
		}

		return false;
	}

	/**
	 * Validate a transition or return WP_Error.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @return true|WP_Error
	 */
	public static function validate_transition( string $from, string $to ) {
		if ( self::can_transition( $from, $to ) ) {
			return true;
		}

		return new WP_Error(
			'illegal_transition',
			sprintf( 'Illegal item transition from %s to %s.', $from, $to )
		);
	}
}
