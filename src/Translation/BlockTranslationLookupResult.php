<?php
/**
 * Strategy F block translation lookup outcome.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

/**
 * Store-backed translation map for block rendering input.
 */
final class BlockTranslationLookupResult {

	/**
	 * Builds a lookup result.
	 *
	 * @param bool                  $successful       Whether lookup completed without fatal issues.
	 * @param array<string, string> $translations     Segment key to sanitized-ready content.
	 * @param int                   $segment_count    Candidate block segments considered.
	 * @param int                   $translated_count Accepted translations.
	 * @param int                   $rejected_count   Rejected translations.
	 * @param string                $failure_reason   Failure reason when not successful.
	 */
	public function __construct(
		public readonly bool $successful,
		public readonly array $translations = array(),
		public readonly int $segment_count = 0,
		public readonly int $translated_count = 0,
		public readonly int $rejected_count = 0,
		public readonly string $failure_reason = '',
	) {
	}
}
