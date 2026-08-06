<?php
/**
 * Background translation job operator capabilities (plan §21).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Explicit job capabilities — not manage_options.
 */
final class JobsCapabilities {

	public const VIEW_JOBS   = 'aiml_view_translation_jobs';
	public const MANAGE_JOBS = 'aiml_manage_translation_jobs';
	public const RUN_JOBS    = 'aiml_run_translation_jobs';
	public const CANCEL_JOBS = 'aiml_cancel_translation_jobs';

	/**
	 * All job capabilities.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::VIEW_JOBS,
			self::MANAGE_JOBS,
			self::RUN_JOBS,
			self::CANCEL_JOBS,
		);
	}

	/**
	 * Roles that receive view, manage, and cancel on activation.
	 *
	 * @return list<string>
	 */
	public static function operator_roles(): array {
		return array( 'administrator', 'editor' );
	}

	/**
	 * Roles that receive run/wake/retry on activation.
	 *
	 * @return list<string>
	 */
	public static function run_roles(): array {
		return array( 'administrator' );
	}

	/**
	 * Grants default job capabilities to configured roles.
	 */
	public static function grant_default_roles(): void {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}

		foreach ( self::operator_roles() as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}

			foreach ( array( self::VIEW_JOBS, self::MANAGE_JOBS, self::CANCEL_JOBS ) as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}

		foreach ( self::run_roles() as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}

			if ( ! $role->has_cap( self::RUN_JOBS ) ) {
				$role->add_cap( self::RUN_JOBS );
			}
		}
	}

	/**
	 * Removes job capabilities from every role.
	 */
	public static function revoke_all_roles(): void {
		if ( ! function_exists( 'wp_roles' ) ) {
			return;
		}

		$roles = wp_roles();
		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = $roles->get_role( (string) $role_name );
			if ( null === $role ) {
				continue;
			}
			foreach ( self::all() as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}
