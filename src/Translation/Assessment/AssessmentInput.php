<?php
/**
 * Input bag for TI.5 assessment assembly.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

use AIMultilingual\Translation\AI\FieldSemantic;
use AIMultilingual\Translation\Store;

/**
 * Current-state evidence inputs — no detector results yet.
 */
final class AssessmentInput {

	/**
	 * Builds assessment inputs from current segment evidence.
	 *
	 * @param string                                $source_text         Source text.
	 * @param string                                $target_text         Target text.
	 * @param string                                $text_format         Store format.
	 * @param string                                $status              Store content status.
	 * @param string                                $review_status       ADR-0015 review_status.
	 * @param string                                $field_semantic      FieldSemantic value.
	 * @param array<int, string>                    $scaffolding_markers Request markers.
	 * @param bool                                  $markers_applicable  Whether leakage is scorable.
	 * @param array<int, array<string, mixed>>|null $glossary_terms  Preferred-term list.
	 * @param string|null                           $tm_outcome_code     Optional TMGenerationOutcome code.
	 * @param string                                $provider            Generation provider id.
	 * @param string                                $model               Generation model.
	 * @param string                                $prompt_profile      Prompt profile.
	 * @param string                                $prompt_version      Prompt version.
	 */
	public function __construct(
		public readonly string $source_text,
		public readonly string $target_text,
		public readonly string $text_format = Store::FORMAT_PLAIN,
		public readonly string $status = Store::STATUS_MISSING,
		public readonly string $review_status = Store::REVIEW_NOT_SUBMITTED,
		public readonly string $field_semantic = FieldSemantic::GENERIC,
		public readonly array $scaffolding_markers = array(),
		public readonly bool $markers_applicable = false,
		public readonly ?array $glossary_terms = null,
		public readonly ?string $tm_outcome_code = null,
		public readonly string $provider = '',
		public readonly string $model = '',
		public readonly string $prompt_profile = '',
		public readonly string $prompt_version = '',
	) {
	}
}
