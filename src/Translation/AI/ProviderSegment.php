<?php
/**
 * One segment within a provider translation batch.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Segment payload for provider translation.
 */
final class ProviderSegment {

	/**
	 * Builds a provider segment.
	 *
	 * @param string $segment_key     Segment key.
	 * @param string $source_text     Source text.
	 * @param string $text_format     Text format constant.
	 * @param string $existing_target Current target (suggest profiles).
	 */
	public function __construct(
		public readonly string $segment_key,
		public readonly string $source_text,
		public readonly string $text_format,
		public readonly string $existing_target = '',
	) {
	}
}
