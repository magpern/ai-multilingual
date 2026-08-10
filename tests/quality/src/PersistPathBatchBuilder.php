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
use AIMultilingual\Translation\Store;

/**
 * Builds TranslationBatch payloads matching TranslationService::translate_segment.
 *
 * The field_semantics corpus field is metadata only and is never passed to the batch.
 */
final class PersistPathBatchBuilder {

	/**
	 * Builds a single-segment translate batch for one corpus case.
	 *
	 * Mirrors TranslationService::translate_segment persist path:
	 * PromptProfileRegistry::TRANSLATE + VERSION, OPERATION_TRANSLATE, empty constraints,
	 * one ProviderSegment, glossary fragment supplied by caller.
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

		// field_semantics is corpus metadata only — intentionally excluded from batch.
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
			array()
		);
	}
}
