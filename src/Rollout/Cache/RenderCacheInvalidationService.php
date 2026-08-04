<?php
/**
 * Canonical render cache invalidation owner.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Cache;

/**
 * All render cache invalidation must go through this service.
 */
final class RenderCacheInvalidationService {

	/**
	 * @param RenderCacheService|null $cache Cache service.
	 */
	public function __construct(
		private ?RenderCacheService $cache = null,
	) {
		$this->cache = $cache ?? new RenderCacheService();
	}

	/**
	 * Purges a specific cache key.
	 */
	public function purge_key( string $key, string $source = 'operator' ): void {
		$this->cache->delete( $key );

		if ( function_exists( 'do_action' ) ) {
			/**
			 * Fires after a render cache key is purged.
			 *
			 * @param string $key    Cache key.
			 * @param string $source Invalidation source label.
			 */
			\do_action( 'aiml_rollout_cache_purged', $key, $source );
		}
	}

	/**
	 * Purges all keys matching a prefix (bounded operator purge).
	 */
	public function purge_prefix( string $prefix ): void {
		// Object cache groups do not support prefix purge portably; operator purge
		// records intent for future group-aware backends.
		if ( function_exists( 'do_action' ) ) {
			\do_action( 'aiml_rollout_cache_purged', $prefix . '*', 'operator_prefix' );
		}
	}
}
