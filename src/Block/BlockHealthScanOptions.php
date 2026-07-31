<?php
/**
 * Strategy F block health scan options.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Translation\Store;

/**
 * Bounded scan configuration for {@see BlockHealthService}.
 */
final class BlockHealthScanOptions {

	public const DEFAULT_SAMPLE_SIZE = 100;

	public const MAX_SAMPLE_SIZE = 1000;

	/**
	 * Builds scan options.
	 *
	 * @param string      $source_type Source type.
	 * @param int         $source_id   Optional single-object scope.
	 * @param string|null $post_type   Optional post type filter.
	 * @param int         $sample_size Requested sample size when not full scan.
	 * @param bool        $full_scan   Whether to scan all eligible posts.
	 */
	public function __construct(
		public readonly string $source_type = Store::SOURCE_POST,
		public readonly int $source_id = 0,
		public readonly ?string $post_type = null,
		public readonly int $sample_size = self::DEFAULT_SAMPLE_SIZE,
		public readonly bool $full_scan = false,
	) {
	}

	/**
	 * Normalizes sample size to the supported bounded range.
	 */
	public function normalized_sample_size(): int {
		return max( 1, min( self::MAX_SAMPLE_SIZE, $this->sample_size ) );
	}
}
