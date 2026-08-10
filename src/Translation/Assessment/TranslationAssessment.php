<?php
/**
 * Versioned read-only translation assessment (TI.5 / ADR-0019).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

/**
 * Computed current-state assessment — no publish_decision / sole score.
 */
final class TranslationAssessment {

	public const VERSION = 'R1.0';

	public const METHODOLOGY_REF = 'deterministic_qa:1+workspace_qa_policy+persist_safety_policy+assessment:R1.0';

	/**
	 * Builds a versioned assessment result.
	 *
	 * @param string                           $assessment_version    Assessment version.
	 * @param string                           $qa_methodology_ref    Methodology reference.
	 * @param string                           $overall_category      AssessmentCategory value.
	 * @param array<string, AssessmentFacet>   $facets                Facet map by id.
	 * @param array<int, AssessmentFindingRef> $hard_blockers         Hard findings.
	 * @param array<int, AssessmentFindingRef> $errors                Error findings.
	 * @param array<int, AssessmentFindingRef> $warnings              Warning findings.
	 * @param string                           $review_status         ADR-0015 review_status.
	 * @param string                           $provenance_class      ProvenanceClass value.
	 * @param array<int, string>               $conflicts             Conflict codes.
	 * @param string                           $evidence_completeness EvidenceCompleteness value.
	 * @param bool                             $dimensions_visible    Always true.
	 */
	public function __construct(
		public readonly string $assessment_version,
		public readonly string $qa_methodology_ref,
		public readonly string $overall_category,
		public readonly array $facets,
		public readonly array $hard_blockers,
		public readonly array $errors,
		public readonly array $warnings,
		public readonly string $review_status,
		public readonly string $provenance_class,
		public readonly array $conflicts,
		public readonly string $evidence_completeness,
		public readonly bool $dimensions_visible = true,
	) {
	}

	/**
	 * Stable JSON/ViewModel shape for Workspace and future TI.7 consumers.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$facets = array();
		foreach ( $this->facets as $id => $facet ) {
			$facets[ $id ] = $facet instanceof AssessmentFacet ? $facet->to_array() : $facet;
		}

		return array(
			'assessment_version'    => $this->assessment_version,
			'qa_methodology_ref'    => $this->qa_methodology_ref,
			'overall_category'      => $this->overall_category,
			'facets'                => $facets,
			'hard_blockers'         => array_map(
				static fn( AssessmentFindingRef $ref ): array => $ref->to_array(),
				$this->hard_blockers
			),
			'errors'                => array_map(
				static fn( AssessmentFindingRef $ref ): array => $ref->to_array(),
				$this->errors
			),
			'warnings'              => array_map(
				static fn( AssessmentFindingRef $ref ): array => $ref->to_array(),
				$this->warnings
			),
			'review_status'         => $this->review_status,
			'provenance_class'      => $this->provenance_class,
			'conflicts'             => $this->conflicts,
			'evidence_completeness' => $this->evidence_completeness,
			'dimensions_visible'    => $this->dimensions_visible,
		);
	}
}
