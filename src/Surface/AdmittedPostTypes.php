<?php
/**
 * Internal CPT admission foundation (code-owned; no public WP filter).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

/**
 * Single authority consolidating Workspace / RenderGate / Rollout admitted types.
 *
 * Contexts preserve prior inclusions/exclusions (nav_menu_item workspace-only).
 * Forbidden: public admitted-post-types WP filter/API, auto-admit all public CPTs.
 */
final class AdmittedPostTypes {

	public const CONTEXT_WORKSPACE         = 'workspace';
	public const CONTEXT_FRONTEND_OVERLAY  = 'frontend_overlay';
	public const CONTEXT_ROLLOUT           = 'rollout';
	public const CONTEXT_LEGACY_ADMIN_EDIT = 'legacy_admin_edit';

	/**
	 * Workspace admitted types (includes nav_menu_item).
	 *
	 * @var list<string>
	 */
	public const WORKSPACE_TYPES = array( 'post', 'page', 'product', 'nav_menu_item' );

	/**
	 * Frontend overlay / render gate admitted types.
	 *
	 * @var list<string>
	 */
	public const FRONTEND_OVERLAY_TYPES = array( 'post', 'page', 'product' );

	/**
	 * Rollout approved types.
	 *
	 * @var list<string>
	 */
	public const ROLLOUT_TYPES = array( 'post', 'page', 'product' );

	/**
	 * Legacy Admin Editor page/post only.
	 *
	 * @var list<string>
	 */
	public const LEGACY_ADMIN_EDIT_TYPES = array( 'page', 'post' );

	/**
	 * Admitted post types for a context.
	 *
	 * @param string $context Context token.
	 * @return list<string>
	 */
	public static function for_context( string $context ): array {
		return match ( $context ) {
			self::CONTEXT_WORKSPACE => self::WORKSPACE_TYPES,
			self::CONTEXT_FRONTEND_OVERLAY => self::FRONTEND_OVERLAY_TYPES,
			self::CONTEXT_ROLLOUT => self::ROLLOUT_TYPES,
			self::CONTEXT_LEGACY_ADMIN_EDIT => self::LEGACY_ADMIN_EDIT_TYPES,
			default => array(),
		};
	}

	/**
	 * Whether a post type is admitted in a context.
	 *
	 * @param string $post_type Post type.
	 * @param string $context   Context token.
	 */
	public static function admits( string $post_type, string $context ): bool {
		return in_array( $post_type, self::for_context( $context ), true );
	}
}
