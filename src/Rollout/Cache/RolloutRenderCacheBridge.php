<?php
/**
 * Render-path cache coordinator (default off).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Cache;

use AIMultilingual\Rollout\RolloutConfiguration;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Translation\Store;

/**
 * Applies render cache reads/writes in the canonical frontend pipeline.
 *
 * Callers must not construct cache keys; this bridge owns key identity.
 */
final class RolloutRenderCacheBridge {

	/**
	 * Builds the render cache bridge.
	 *
	 * @param RenderCacheService             $cache       Render cache service.
	 * @param RenderCacheKeyFactory          $key_factory Cache key factory.
	 * @param Store                          $store       Segment store.
	 * @param RolloutConfigurationRepository $config_repo Active rollout config.
	 */
	public function __construct(
		private RenderCacheService $cache,
		private RenderCacheKeyFactory $key_factory,
		private Store $store,
		private RolloutConfigurationRepository $config_repo,
	) {
	}

	/**
	 * Reads cached HTML when cache is enabled and a matching entry exists.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $content     Source post content.
	 * @param int    $language_id Target language ID.
	 */
	public function try_get( int $post_id, string $content, int $language_id ): ?string {
		$config = $this->config_repo->get();

		if ( ! $config->render_cache_enabled || $language_id <= 0 ) {
			return null;
		}

		$key = $this->build_key( $post_id, $content, $language_id, $config );

		return $this->cache->get( $key, $language_id, $config );
	}

	/**
	 * Stores rendered HTML when cache is enabled.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $content     Source post content.
	 * @param int    $language_id Target language ID.
	 * @param string $html        Rendered HTML.
	 */
	public function store( int $post_id, string $content, int $language_id, string $html ): void {
		$config = $this->config_repo->get();

		if ( ! $config->render_cache_enabled || $language_id <= 0 || '' === $html ) {
			return;
		}

		$key = $this->build_key( $post_id, $content, $language_id, $config );
		$this->cache->set( $key, $language_id, $html, $config );
	}

	/**
	 * Builds the cache key for one post/language/content snapshot.
	 *
	 * @param int                  $post_id     Post ID.
	 * @param string               $content     Source post content.
	 * @param int                  $language_id Target language ID.
	 * @param RolloutConfiguration $config      Active rollout configuration.
	 * @param array<int, object>   $rows        Store rows when preloaded; otherwise loaded here.
	 */
	private function build_key(
		int $post_id,
		string $content,
		int $language_id,
		RolloutConfiguration $config,
		array $rows = array(),
	): string {
		if ( array() === $rows ) {
			$rows = array_values(
				$this->store->load_object( Store::SOURCE_POST, $post_id, $language_id )
			);
		}

		return $this->key_factory->build(
			$post_id,
			$this->key_factory->source_content_hash( $content ),
			$language_id,
			$rows,
			$config
		);
	}
}
