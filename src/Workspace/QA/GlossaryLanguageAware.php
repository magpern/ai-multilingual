<?php
/**
 * Optional language pair for glossary-aware QA checks.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA;

/**
 * Checks that need a language pair receive it via QAEngine context.
 */
interface GlossaryLanguageAware {

	/**
	 * Set source/target language ids for the next evaluation.
	 *
	 * @param int $source_lang_id Source language id.
	 * @param int $target_lang_id Target language id.
	 */
	public function set_language_pair( int $source_lang_id, int $target_lang_id ): void;
}
