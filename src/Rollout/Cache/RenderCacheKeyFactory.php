<?php
/**
 * Frontend render cache key factory.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Cache;

use AIMultilingual\Rollout\RolloutConfiguration;
use AIMultilingual\Translation\Store;

/**
 * Sole owner of render cache identity construction.
 */
final class RenderCacheKeyFactory {

	public const RENDERER_CONTRACT_VERSION = 'f12-v1';

	/**
	 * Builds a deterministic cache key from frozen identity components.
	 *
	 * @param list<object> $rows Renderable Store rows ordered by segment_key.
	 */
	public function build(
		int $post_id,
		string $source_content_hash,
		int $language_id,
		array $rows,
		RolloutConfiguration $config,
	): string {
		$fingerprint = $this->translation_fingerprint( $rows );

		$parts = array(
			(string) $post_id,
			$source_content_hash,
			(string) $language_id,
			$fingerprint,
			(string) $config->policy_version,
			self::RENDERER_CONTRACT_VERSION,
		);

		return 'aiml_render:' . hash( 'sha1', implode( '|', $parts ) );
	}

	/**
	 * @param list<object> $rows Store rows.
	 */
	public function translation_fingerprint( array $rows ): string {
		$hashes = array();

		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$key = (string) ( $row->segment_key ?? '' );
			if ( '' === $key ) {
				continue;
			}
			$hashes[ $key ] = (string) ( $row->translation_hash ?? '' );
		}

		ksort( $hashes );

		return hash( 'sha1', implode( '|', $hashes ) );
	}

	/**
	 * Normalized post content hash.
	 */
	public function source_content_hash( string $content ): string {
		return Store::source_hash( $content, Store::FORMAT_HTML );
	}
}
