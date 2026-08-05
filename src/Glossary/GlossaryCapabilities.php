<?php
/**
 * Glossary operator capability (ADR-0014).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Glossary;

/**
 * Explicit glossary capability — not manage_options.
 */
final class GlossaryCapabilities {

	public const MANAGE_GLOSSARY = 'aiml_manage_glossary';

	/**
	 * All glossary capabilities.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::MANAGE_GLOSSARY );
	}

	/**
	 * Roles that receive glossary capabilities on activation.
	 *
	 * @return list<string>
	 */
	public static function default_roles(): array {
		return array( 'administrator' );
	}

	/**
	 * Grants glossary capabilities to default roles.
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
	 * Removes glossary capabilities from every role.
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
