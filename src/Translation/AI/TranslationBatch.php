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

	public const OPERATION_TRANSLATE = 'translate';
	public const OPERATION_SUGGEST   = 'suggest';

	/**
	 * Builds a provider batch.
	 *
	 * Additive F11 fields (`operation`, `constraints`) default for F10 callers.
	 *
	 * @param string                      $source_locale     Source locale.
	 * @param string                      $target_locale     Target locale.
	 * @param string                      $prompt_profile    Prompt profile id.
	 * @param string                      $prompt_version    Prompt profile version.
	 * @param string                      $glossary_fragment Serialized glossary fragment.
	 * @param array<int, ProviderSegment> $segments          Segments to translate.
	 * @param string                      $operation         translate|suggest.
	 * @param array<int, string>          $constraints       Structural constraint ids.
	 */
	public function __construct(
		public readonly string $source_locale,
		public readonly string $target_locale,
		public readonly string $prompt_profile,
		public readonly string $prompt_version,
		public readonly string $glossary_fragment,
		public readonly array $segments,
		public readonly string $operation = self::OPERATION_TRANSLATE,
		public readonly array $constraints = array(),
	) {
	}
}
