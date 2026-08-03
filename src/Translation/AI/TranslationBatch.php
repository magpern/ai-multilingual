<?php
/**
 * Provider input payload for one translation request.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Immutable batch sent to an AI provider.
 */
final class TranslationBatch {

	/**
	 * Builds a provider batch.
	 *
	 * @param string                       $source_locale Source locale.
	 * @param string                       $target_locale Target locale.
	 * @param string                       $prompt_profile Prompt profile id.
	 * @param string                       $prompt_version Prompt profile version.
	 * @param string                       $glossary_fragment Serialized glossary fragment.
	 * @param array<int, ProviderSegment>  $segments Segments to translate.
	 */
	public function __construct(
		public readonly string $source_locale,
		public readonly string $target_locale,
		public readonly string $prompt_profile,
		public readonly string $prompt_version,
		public readonly string $glossary_fragment,
		public readonly array $segments,
	) {
	}
}
