<?php
/**
 * Frontend render cache service (default off).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Cache;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Rollout\RolloutConfiguration;

/**
 * Object-cache backed render output cache.
 */
final class RenderCacheService {

	/**
	 * Object cache wrapper.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Builds the render cache service.
	 *
	 * @param Cache|null $cache Optional cache wrapper.
	 */
	public function __construct( ?Cache $cache = null ) {
		$this->cache = $cache ?? new Cache();
	}

	/**
	 * Reads cached HTML when enabled and present.
	 *
	 * @param string               $key         Cache key.
	 * @param int                  $language_id Target language ID.
	 * @param RolloutConfiguration $config      Active rollout configuration.
	 */
	public function get( string $key, int $language_id, RolloutConfiguration $config ): ?string {
		if ( ! $config->render_cache_enabled || $language_id <= 0 ) {
			return null;
		}

		$value = $this->cache->get( $key, $language_id );

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Stores rendered HTML when cache is enabled.
	 *
	 * @param string               $key         Cache key.
	 * @param int                  $language_id Target language ID.
	 * @param string               $html        Rendered HTML.
	 * @param RolloutConfiguration $config      Active rollout configuration.
	 * @param int                  $ttl         Cache TTL in seconds.
	 */
	public function set( string $key, int $language_id, string $html, RolloutConfiguration $config, int $ttl = 3600 ): bool {
		if ( ! $config->render_cache_enabled || $language_id <= 0 || '' === $html ) {
			return false;
		}

		$this->cache->set( $key, $language_id, $html, $ttl );

		return true;
	}

	/**
	 * Deletes one cache entry.
	 *
	 * @param string $key         Cache key.
	 * @param int    $language_id Target language ID.
	 */
	public function delete( string $key, int $language_id ): void {
		if ( $language_id <= 0 ) {
			return;
		}

		$this->cache->delete( $key, $language_id );
	}
}
