<?php
/**
 * Assembles TI.5 assessment from current evidence (one DeterministicQA pass).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

use AIMultilingual\Translation\AI\FieldSemantic;
use AIMultilingual\Translation\QA\DetectionInput;
use AIMultilingual\Translation\QA\DeterministicQA;
use AIMultilingual\Translation\Store;

/**
 * Single assessment core entry point.
 */
final class AssessmentAssembler {

	/**
	 * Shared detector orchestrator.
	 *
	 * @var DeterministicQA
	 */
	private DeterministicQA $qa;

	/**
	 * Risk / readiness policy.
	 *
	 * @var RiskAssessmentPolicy
	 */
	private RiskAssessmentPolicy $policy;

	/**
	 * Builds the assembler.
	 *
	 * @param DeterministicQA|null      $qa     Optional QA orchestrator.
	 * @param RiskAssessmentPolicy|null $policy Optional policy.
	 */
	public function __construct( ?DeterministicQA $qa = null, ?RiskAssessmentPolicy $policy = null ) {
		$this->qa     = $qa ?? new DeterministicQA();
		$this->policy = $policy ?? new RiskAssessmentPolicy();
	}

	/**
	 * Assesses from an AssessmentInput (runs detectors once).
	 *
	 * @param AssessmentInput $input Assessment input.
	 */
	public function assess( AssessmentInput $input ): TranslationAssessment {
		$detection = new DetectionInput(
			$input->source_text,
			$input->target_text,
			$input->text_format,
			$input->scaffolding_markers,
			$input->markers_applicable,
			$input->glossary_terms,
			'',
			'',
			array()
		);

		$findings = $this->qa->detect( $detection );

		return $this->policy->assess( $input, $findings );
	}

	/**
	 * Builds AssessmentInput from a Workspace/Store segment DTO shape.
	 *
	 * @param array<string, mixed>                  $segment            Segment DTO.
	 * @param string                                $field_semantic     FieldSemantic value.
	 * @param array<int, string>                    $markers            Optional markers.
	 * @param bool                                  $markers_applicable Marker applicability.
	 * @param array<int, array<string, mixed>>|null $glossary_terms Glossary terms.
	 * @param string|null                           $tm_outcome_code    Optional TM outcome.
	 */
	public function assess_segment(
		array $segment,
		string $field_semantic = FieldSemantic::GENERIC,
		array $markers = array(),
		bool $markers_applicable = false,
		?array $glossary_terms = null,
		?string $tm_outcome_code = null
	): TranslationAssessment {
		$input = new AssessmentInput(
			(string) ( $segment['source_text'] ?? '' ),
			(string) ( $segment['translated_text'] ?? '' ),
			(string) ( $segment['text_format'] ?? Store::FORMAT_PLAIN ),
			(string) ( $segment['status'] ?? Store::STATUS_MISSING ),
			(string) ( $segment['review_status'] ?? Store::REVIEW_NOT_SUBMITTED ),
			$field_semantic,
			$markers,
			$markers_applicable,
			$glossary_terms,
			$tm_outcome_code,
			(string) ( $segment['provider'] ?? '' ),
			(string) ( $segment['model'] ?? '' ),
			(string) ( $segment['prompt_profile'] ?? '' ),
			(string) ( $segment['prompt_version'] ?? '' )
		);

		return $this->assess( $input );
	}
}
