<?php
/**
 * Persist-path parity adapter for TQ.0 generation batches.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\ProviderSegment;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\AI\TranslationContextBuilder;
use AIMultilingual\Translation\Store;

/**
 * Builds TranslationBatch payloads matching TranslationService::translate_segment.
 *
 * Uses the real TI.2 TranslationContextBuilder path (not a quality-only approximation).
 */
final class PersistPathBatchBuilder {

	/**
	 * @var TranslationContextBuilder
	 */
	private TranslationContextBuilder $context_builder;

	/**
	 * @param TranslationContextBuilder|null $context_builder Shared context builder.
	 */
	public function __construct( ?TranslationContextBuilder $context_builder = null ) {
		$this->context_builder = $context_builder ?? new TranslationContextBuilder();
	}

	/**
	 * Builds a single-segment translate batch for one corpus case.
	 *
	 * Mirrors TranslationService::translate_segment persist path:
	 * PromptProfileRegistry::TRANSLATE + VERSION, OPERATION_TRANSLATE, empty constraints,
	 * one ProviderSegment, glossary fragment supplied by caller, optional TranslationContext.
	 *
	 * @param array<string,mixed> $case              Corpus case row.
	 * @param string              $source_locale     Source locale (e.g. en_US).
	 * @param string              $target_locale     Target locale (e.g. sv_SE).
	 * @param string              $glossary_fragment Serialized glossary fragment.
	 * @return TranslationBatch
	 */
	public function build_for_case(
		array $case,
		string $source_locale,
		string $target_locale,
		string $glossary_fragment
	): TranslationBatch {
		$case_id = (string) ( $case['id'] ?? '' );
		if ( '' === $case_id ) {
			throw new \InvalidArgumentException( 'Corpus case id is required.' );
		}

		$context = $this->context_builder->build_for_corpus_case( $case );

		return new TranslationBatch(
			$source_locale,
			$target_locale,
			PromptProfileRegistry::TRANSLATE,
			PromptProfileRegistry::VERSION,
			$glossary_fragment,
			array(
				new ProviderSegment(
					$case_id,
					(string) ( $case['source_text'] ?? '' ),
					(string) ( $case['text_format'] ?? Store::FORMAT_PLAIN )
				),
			),
			TranslationBatch::OPERATION_TRANSLATE,
			array(),
			$context
		);
	}
}
