<?php
/**
 * Review workflow reviewer capability (ADR-0015).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

/**
 * Explicit review capability — not manage_options, not aiml_translate.
 */
final class ReviewCapabilities {

	public const REVIEW_TRANSLATIONS = 'aiml_review_translations';

	/**
	 * All review capabilities.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::REVIEW_TRANSLATIONS );
	}

	/**
	 * Roles that receive review capabilities on activation.
	 *
	 * @return list<string>
	 */
	public static function default_roles(): array {
		return array( 'administrator' );
	}

	/**
	 * Grants review capabilities to default roles.
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

	/**
	 * Removes review capabilities from every role.
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
