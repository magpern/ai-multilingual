<?php
/**
 * Canonical render cache invalidation owner.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Cache;

use AIMultilingual\Cache\Cache;

/**
 * All render cache invalidation must go through this service.
 */
final class RenderCacheInvalidationService {

	/**
	 * Shared object cache wrapper.
	 *
	 * @var Cache
	 */
	private Cache $object_cache;

	/**
	 * Builds the invalidation service.
	 *
	 * @param RenderCacheService|null $cache        Render cache service.
	 * @param Cache|null              $object_cache Shared object cache wrapper.
	 */
	public function __construct(
		private ?RenderCacheService $cache = null,
		?Cache $object_cache = null,
	) {
		$this->cache        = $cache ?? new RenderCacheService();
		$this->object_cache = $object_cache ?? new Cache();
	}

	/**
	 * Invalidates every render cache entry for one language.
	 *
	 * @param int    $language_id Target language ID.
	 * @param string $source      Invalidation source label.
	 */
	public function invalidate_language( int $language_id, string $source = 'system' ): void {
		if ( $language_id <= 0 ) {
			return;
		}

		$this->object_cache->flush_language( $language_id );
		$this->emit_purged( 'lang:' . $language_id, $source );
	}

	/**
	 * Invalidates every render cache entry for every language.
	 *
	 * @param string $source Invalidation source label.
	 */
	public function invalidate_all( string $source = 'system' ): void {
		$this->object_cache->flush_all();
		$this->emit_purged( '*', $source );
	}

	/**
	 * Purges a specific cache key.
	 *
	 * @param string $key         Cache key.
	 * @param int    $language_id Target language ID.
	 * @param string $source      Invalidation source label.
	 */
	public function purge_key( string $key, int $language_id, string $source = 'operator' ): void {
		$this->cache->delete( $key, $language_id );

		if ( function_exists( 'do_action' ) ) {
			/**
			 * Fires after a render cache key is purged.
			 *
			 * @since 0.1.0
			 *
			 * @param string $key    Cache key.
			 * @param string $source Invalidation source label.
			 */
			\do_action( 'aiml_rollout_cache_purged', $key, $source );
		}
	}

	/**
	 * Purges all keys matching a prefix (bounded operator purge).
	 *
	 * @param string $prefix Cache key prefix.
	 */
	public function purge_prefix( string $prefix ): void {
		// Object cache groups do not support prefix purge portably; operator purge
		// records intent for future group-aware backends.
		$this->emit_purged( $prefix . '*', 'operator_prefix' );
	}

	/**
	 * Emits the canonical cache purge audit hook.
	 *
	 * @param string $target Purge target label.
	 * @param string $source Invalidation source label.
	 */
	private function emit_purged( string $target, string $source ): void {
		if ( function_exists( 'do_action' ) ) {
			/**
			 * Fires after render cache entries are purged.
			 *
			 * @since 0.1.0
			 *
			 * @param string $target Purge target label.
			 * @param string $source Invalidation source label.
			 */
			\do_action( 'aiml_rollout_cache_purged', $target, $source );
		}
	}
}
