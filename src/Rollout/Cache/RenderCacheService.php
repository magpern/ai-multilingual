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

	private Cache $cache;

	/**
	 * @param Cache|null $cache Optional cache wrapper.
	 */
	public function __construct( ?Cache $cache = null ) {
		$this->cache = $cache ?? new Cache();
	}

	/**
	 * Reads cached HTML when enabled and present.
	 */
	public function get( string $key, RolloutConfiguration $config ): ?string {
		if ( ! $config->render_cache_enabled ) {
			return null;
		}

		$value = $this->cache->get( $key );

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Stores rendered HTML when cache is enabled.
	 */
	public function set( string $key, string $html, RolloutConfiguration $config, int $ttl = 3600 ): bool {
		if ( ! $config->render_cache_enabled || '' === $html ) {
			return false;
		}

		$this->cache->set( $key, $html, $ttl );

		return true;
	}

	/**
	 * Deletes one cache entry.
	 */
	public function delete( string $key ): void {
		$this->cache->delete( $key );
	}
}
