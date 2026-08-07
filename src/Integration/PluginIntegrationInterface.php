<?php
/**
 * Plugin integration extension interface (Integration API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

use WP_Post;

/**
 * Public extension contract for code-owned plugin integrations.
 */
interface PluginIntegrationInterface {

	/**
	 * Immutable lowercase-safe integration ID.
	 */
	public function get_id(): string;

	/**
	 * Integration API version this implementation targets.
	 */
	public function get_api_version(): string;

	/**
	 * Current compatibility / lifecycle status.
	 */
	public function get_compatibility(): CompatibilityStatus;

	/**
	 * Extract visitor-facing units for a canonical post/document context.
	 *
	 * @param WP_Post $post Canonical post.
	 * @return list<TranslationUnitDescriptor>
	 */
	public function extract_for_post( WP_Post $post ): array;

	/**
	 * Register visitor-output overlay hooks for the current request.
	 *
	 * @param callable(string): (?string) $resolve Segment key → translated text or null.
	 */
	public function register_output_hooks( callable $resolve ): void;
}
