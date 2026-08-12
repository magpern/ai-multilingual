<?php
/**
 * Narrow internal surface capability contract (facts / delegation only).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

/**
 * Supplies source facts and delegation entry points.
 *
 * Must NOT run Jobs / publication / review / QA policy, absorb SegmentAssembler
 * or Store orchestration, or expose can_publish_translation.
 */
interface SurfaceCapability {

	/**
	 * Stable source_type classification (e.g. post).
	 */
	public function source_type(): string;

	/**
	 * Whether the source object currently exists.
	 *
	 * @param int $source_id Source object id.
	 */
	public function exists( int $source_id ): bool;

	/**
	 * Source subtype fact (e.g. post_type / taxonomy slug).
	 *
	 * @param int $source_id Source object id.
	 */
	public function source_subtype( int $source_id ): string;

	/**
	 * Authorization fact via WordPress capabilities (not admin-configured callbacks).
	 *
	 * @param int $user_id   User id (0 = current user semantics when WP uses current).
	 * @param int $source_id Source object id.
	 */
	public function user_can_edit_source( int $user_id, int $source_id ): bool;

	/**
	 * Visitor visibility fact only — never publication policy.
	 *
	 * @param int $source_id Source object id.
	 */
	public function is_visitor_public( int $source_id ): bool;

	/**
	 * Whether this surface declares a named capability.
	 *
	 * @param string $capability {@see SurfaceCapabilityNames}.
	 */
	public function supports( string $capability ): bool;

	/**
	 * Whether the named feature is implemented by this surface.
	 *
	 * Distinct from settings activation (flags may still be OFF).
	 *
	 * @param string $feature Feature token (e.g. block_extraction, elementor_extraction).
	 */
	public function feature_implemented( string $feature ): bool;

	/**
	 * Whether the named feature is activated in settings (may be false while implemented).
	 *
	 * @param string $feature Feature token.
	 */
	public function feature_activated( string $feature ): bool;

	/**
	 * Current source segments for this identity, keyed by segment key.
	 *
	 * Delegation only — the surface owns which extractor answers for its
	 * source type, never how segments are stored or reconciled.
	 *
	 * @param int $source_id Source object id.
	 * @return array<string, array<string, mixed>>
	 */
	public function extract_segments( int $source_id ): array;

	/**
	 * Register invalidation event mappers into the request-local coordinator.
	 *
	 * Hooks must only mark dirty — never sync or call providers.
	 *
	 * @param RequestLocalInvalidationCoordinator $coordinator Coordinator.
	 */
	public function register_invalidation_events( RequestLocalInvalidationCoordinator $coordinator ): void;
}
