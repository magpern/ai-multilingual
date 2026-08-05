<?php
/**
 * F12 rollout operator capabilities.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Explicit rollout capabilities — not solely manage_options.
 */
final class RolloutCapabilities {

	public const VIEW_ROLLOUT           = 'aiml_view_rollout';
	public const MANAGE_ROLLOUT         = 'aiml_manage_rollout';
	public const PROMOTE_ROLLOUT        = 'aiml_promote_rollout';
	public const EMERGENCY_ROLLBACK     = 'aiml_emergency_rollback';
	public const MANAGE_ROLLOUT_METRICS = 'aiml_manage_rollout_metrics';

	/**
	 * All rollout capabilities.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::VIEW_ROLLOUT,
			self::MANAGE_ROLLOUT,
			self::PROMOTE_ROLLOUT,
			self::EMERGENCY_ROLLBACK,
			self::MANAGE_ROLLOUT_METRICS,
		);
	}

	/**
	 * Roles that receive all rollout capabilities on activation (interim policy).
	 *
	 * @return list<string>
	 */
	public static function default_roles(): array {
		return array( 'administrator' );
	}

	/**
	 * Grants rollout capabilities to default roles.
	 */
	public static function grant_default_roles(): void {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}

		foreach ( self::default_roles() as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}

			foreach ( self::all() as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}
}
