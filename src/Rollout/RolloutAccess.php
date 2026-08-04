<?php
/**
 * Rollout authorization helpers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Capability checks for rollout mutations.
 */
final class RolloutAccess {

	/**
	 * Whether a user has a rollout capability.
	 *
	 * @param int    $user_id User ID (0 = current user when available).
	 * @param string $cap     Capability slug.
	 */
	public static function user_can( int $user_id, string $cap ): bool {
		if ( ! function_exists( 'user_can' ) ) {
			return false;
		}

		if ( $user_id <= 0 && function_exists( 'get_current_user_id' ) ) {
			$user_id = (int) get_current_user_id();
		}

		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, $cap );
	}

	/**
	 * Whether rollout rendering is allowed by the global master switch.
	 *
	 * @param callable():bool $frontend_render_enabled Returns block_frontend_rendering_enabled.
	 */
	public static function master_render_enabled( callable $frontend_render_enabled ): bool {
		return (bool) $frontend_render_enabled();
	}
}
