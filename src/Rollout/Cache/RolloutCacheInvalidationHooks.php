<?php
/**
 * Registers canonical render cache invalidation hooks.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Cache;

use AIMultilingual\Language\Languages;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * Wires invalidation triggers to {@see RenderCacheInvalidationService}.
 */
final class RolloutCacheInvalidationHooks {

	/**
	 * Builds the cache invalidation hooks registrar.
	 *
	 * @param RenderCacheInvalidationService $invalidation Invalidation owner.
	 * @param Languages|null                 $languages    Language registry.
	 */
	public function __construct(
		private RenderCacheInvalidationService $invalidation,
		private ?Languages $languages = null,
	) {
	}

	/**
	 * Registers WordPress hooks when available.
	 */
	public function register(): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		add_action( 'aiml_rollout_config_saved', array( $this, 'on_config_saved' ), 10, 0 );
		add_action( 'aiml_translation_saved', array( $this, 'on_translation_saved' ), 10, 3 );
	}

	/**
	 * Invalidates render cache when canonical post content changes.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	public function on_save_post( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( Store::SOURCE_POST !== $post->post_type && 'page' !== $post->post_type ) {
			return;
		}

		$this->invalidation->invalidate_all( 'source_content_save' );
	}

	/**
	 * Invalidates render cache after rollout configuration changes.
	 */
	public function on_config_saved(): void {
		$this->invalidation->invalidate_all( 'rollout_config_change' );
	}

	/**
	 * Invalidates render cache for one language after a translation save.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object ID.
	 * @param int    $language_id Language ID.
	 */
	public function on_translation_saved( string $source_type, int $source_id, int $language_id ): void {
		unset( $source_type, $source_id );

		if ( $language_id > 0 ) {
			$this->invalidation->invalidate_language( $language_id, 'translation_save' );
		}
	}
}
