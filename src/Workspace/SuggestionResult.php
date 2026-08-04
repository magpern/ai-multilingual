<?php
/**
 * Read-only suggestion outcome from TranslationService (F11 §4.3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

/**
 * Suggest mode never persists; Accept flows through save_segment.
 */
final class SuggestionResult {

	/**
	 * Builds a suggestion result.
	 *
	 * @param string $target_text     Suggested text.
	 * @param string $prompt_profile  Profile id.
	 * @param string $prompt_version  Profile version.
	 * @param string $model           Provider model id.
	 * @param float  $confidence      Normalized confidence (AI has no vendor score).
	 */
	public function __construct(
		public readonly string $target_text,
		public readonly string $prompt_profile,
		public readonly string $prompt_version,
		public readonly string $model = '',
		public readonly float $confidence = 70.0
	) {
	}
}
