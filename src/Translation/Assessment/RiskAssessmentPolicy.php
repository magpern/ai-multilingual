<?php
/**
 * TI.5 risk/readiness policy over assembled evidence bags.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Assessment;

use AIMultilingual\Translation\AI\FieldSemantic;
use AIMultilingual\Translation\Memory\TMGenerationOutcome;
use AIMultilingual\Translation\QA\DeterministicDetectorSuite;
use AIMultilingual\Translation\QA\RawFinding;
use AIMultilingual\Translation\Store;

/**
 * Maps evidence → TranslationAssessment. Does not detect.
 */
final class RiskAssessmentPolicy {

	public const CONFLICT_APPROVED_WITH_HARD = 'approved_with_hard_findings';

	public const LANE_HARD    = 'hard';
	public const LANE_ERROR   = 'error';
	public const LANE_WARNING = 'warning';
	public const LANE_INFO    = 'info';
	public const LANE_NA      = 'na';

	/**
	 * Check ids that are TI.1 persist blockers (hard).
	 *
	 * @var list<string>
	 */
	private const HARD_CHECK_IDS = array(
		DeterministicDetectorSuite::CHECK_EMPTY_TARGET,
		DeterministicDetectorSuite::CHECK_PLACEHOLDER_LOSS,
		DeterministicDetectorSuite::CHECK_HTML_TAG_LOSS,
		DeterministicDetectorSuite::CHECK_FORBIDDEN_MARKUP,
		DeterministicDetectorSuite::CHECK_URL_LOSS,
	);

	/**
	 * H1.1-style error lane (non-hard).
	 *
	 * @var list<string>
	 */
	private const ERROR_CHECK_IDS = array(
		DeterministicDetectorSuite::CHECK_NUMBER_CORRUPTION,
		DeterministicDetectorSuite::CHECK_GLOSSARY_TERM_MISSING,
		DeterministicDetectorSuite::CHECK_UNICODE_DAMAGE,
		DeterministicDetectorSuite::CHECK_ENTITY_DAMAGE,
	);

	/**
	 * Builds assessment from raw findings + input metadata.
	 *
	 * @param AssessmentInput        $input    Assessment input.
	 * @param array<int, RawFinding> $findings One DeterministicQA pass results.
	 */
	public function assess( AssessmentInput $input, array $findings ): TranslationAssessment {
		$hard              = array();
		$errors            = array();
		$warnings          = array();
		$na_codes          = array();
		$soft_for_category = false;

		$leakage_applicable = $input->markers_applicable;

		if ( ! $leakage_applicable ) {
			$na_codes[] = 'leakage_not_applicable';
		}

		foreach ( $findings as $finding ) {
			if ( ! $finding instanceof RawFinding ) {
				continue;
			}

			$lane = $this->lane_for( $finding, $leakage_applicable, $input->field_semantic );
			$ref  = new AssessmentFindingRef(
				$finding->check_id,
				$lane,
				$finding->message,
				$finding->evidence
			);

			if ( self::LANE_HARD === $lane ) {
				$hard[] = $ref;
			} elseif ( self::LANE_ERROR === $lane ) {
				$errors[] = $ref;
			} elseif ( self::LANE_WARNING === $lane ) {
				$warnings[]        = $ref;
				$soft_for_category = true;
			} elseif ( self::LANE_INFO === $lane ) {
				// Visible but non-escalating (RA9 Partial ui_label source==target).
				$warnings[] = $ref;
			}
		}

		$provenance   = $this->classify_provenance( $input );
		$completeness = $this->completeness( $leakage_applicable, $na_codes, $findings, $input );
		$conflicts    = array();

		$category = $this->category(
			$input,
			$hard,
			$errors,
			$soft_for_category
		);

		if ( Store::REVIEW_APPROVED === $input->review_status && array() !== $hard ) {
			$conflicts[] = self::CONFLICT_APPROVED_WITH_HARD;
			// Never greenwash: keep blocked (or at least needs_review).
			if ( AssessmentCategory::STRUCTURALLY_CLEAN === $category
				|| AssessmentCategory::REVIEW_RECOMMENDED === $category ) {
				$category = AssessmentCategory::NEEDS_REVIEW;
			}
		}

		$facets = $this->build_facets(
			$input,
			$hard,
			$errors,
			$warnings,
			$na_codes,
			$leakage_applicable,
			$provenance,
			$completeness
		);

		return new TranslationAssessment(
			TranslationAssessment::VERSION,
			TranslationAssessment::METHODOLOGY_REF,
			$category,
			$facets,
			$hard,
			$errors,
			$warnings,
			$input->review_status,
			$provenance,
			$conflicts,
			$completeness,
			true
		);
	}

	/**
	 * Classifies one finding into an assessment lane.
	 *
	 * @param RawFinding $finding            Raw finding.
	 * @param bool       $leakage_applicable Markers applicable.
	 * @param string     $field_semantic     FieldSemantic value.
	 */
	private function lane_for( RawFinding $finding, bool $leakage_applicable, string $field_semantic ): string {
		if ( 'qd3_scaffolding_leakage' === $finding->check_id ) {
			return $leakage_applicable ? self::LANE_HARD : self::LANE_NA;
		}

		if ( in_array( $finding->check_id, self::HARD_CHECK_IDS, true ) ) {
			return self::LANE_HARD;
		}

		if ( in_array( $finding->check_id, self::ERROR_CHECK_IDS, true ) ) {
			return self::LANE_ERROR;
		}

		// RA9 Partial: ui_label source==target does not escalate category (still listed as info).
		if ( DeterministicDetectorSuite::CHECK_SOURCE_EQUALS_TARGET === $finding->check_id
			&& FieldSemantic::UI_LABEL === $field_semantic ) {
			return self::LANE_INFO;
		}

		return self::LANE_WARNING;
	}

	/**
	 * Resolves overall_category from hard/soft/human evidence.
	 *
	 * @param AssessmentInput                  $input             Input.
	 * @param array<int, AssessmentFindingRef> $hard              Hard findings.
	 * @param array<int, AssessmentFindingRef> $errors            Error findings.
	 * @param bool                             $soft_for_category Escalating soft warnings present.
	 */
	private function category(
		AssessmentInput $input,
		array $hard,
		array $errors,
		bool $soft_for_category
	): string {
		if ( array() !== $hard ) {
			return AssessmentCategory::BLOCKED;
		}

		if ( array() !== $errors ) {
			return AssessmentCategory::NEEDS_REVIEW;
		}

		$unreviewed_machine = Store::STATUS_MACHINE_TRANSLATED === $input->status
			&& Store::REVIEW_APPROVED !== $input->review_status;

		$pending_material = Store::REVIEW_PENDING === $input->review_status && $soft_for_category;

		if ( $pending_material ) {
			return AssessmentCategory::NEEDS_REVIEW;
		}

		if ( $soft_for_category || $unreviewed_machine || Store::REVIEW_REJECTED === $input->review_status ) {
			return AssessmentCategory::REVIEW_RECOMMENDED;
		}

		if ( Store::STATUS_MISSING === $input->status && '' === trim( $input->target_text ) ) {
			return AssessmentCategory::REVIEW_RECOMMENDED;
		}

		return AssessmentCategory::STRUCTURALLY_CLEAN;
	}

	/**
	 * Best-effort provenance classification from Store/TM evidence.
	 *
	 * @param AssessmentInput $input Input.
	 */
	private function classify_provenance( AssessmentInput $input ): string {
		if ( Store::STATUS_MISSING === $input->status && '' === trim( $input->target_text ) ) {
			return ProvenanceClass::MISSING;
		}

		if ( TMGenerationOutcome::DIRECT_REUSE === (string) $input->tm_outcome_code ) {
			return ProvenanceClass::TM_DIRECT_REUSE;
		}

		if ( Store::STATUS_MANUALLY_EDITED === $input->status ) {
			return ProvenanceClass::MANUALLY_EDITED;
		}

		if ( Store::STATUS_REVIEWED === $input->status ) {
			return ProvenanceClass::LEGACY_REVIEWED_STATUS;
		}

		if ( Store::STATUS_MACHINE_TRANSLATED === $input->status ) {
			return ProvenanceClass::AI_GENERATED;
		}

		return ProvenanceClass::UNKNOWN;
	}

	/**
	 * Computes evidence-completeness (unavailable ≠ PASS).
	 *
	 * @param bool                   $leakage_applicable Markers applicable.
	 * @param array<int, string>     $na_codes           N/A codes.
	 * @param array<int, RawFinding> $findings           Findings.
	 * @param AssessmentInput        $input              Assessment input.
	 */
	private function completeness(
		bool $leakage_applicable,
		array $na_codes,
		array $findings,
		AssessmentInput $input
	): string {
		unset( $na_codes, $findings );

		if ( ! $leakage_applicable ) {
			return EvidenceCompleteness::UNAVAILABLE;
		}

		if ( array() === $input->scaffolding_markers ) {
			// Claimed applicable without marker inventory → partial, never "no leakage".
			return EvidenceCompleteness::PARTIAL;
		}

		return EvidenceCompleteness::COMPLETE;
	}

	/**
	 * Builds explainable facet map.
	 *
	 * @param AssessmentInput                  $input              Input.
	 * @param array<int, AssessmentFindingRef> $hard               Hard findings.
	 * @param array<int, AssessmentFindingRef> $errors             Error findings.
	 * @param array<int, AssessmentFindingRef> $warnings           Warning findings.
	 * @param array<int, string>               $na_codes           N/A codes.
	 * @param bool                             $leakage_applicable Whether leakage is applicable.
	 * @param string                           $provenance         Provenance class.
	 * @param string                           $completeness       Completeness state.
	 * @return array<string, AssessmentFacet>
	 */
	private function build_facets(
		AssessmentInput $input,
		array $hard,
		array $errors,
		array $warnings,
		array $na_codes,
		bool $leakage_applicable,
		string $provenance,
		string $completeness
	): array {
		$structural_refs = array_values(
			array_filter(
				$hard,
				static fn( AssessmentFindingRef $ref ): bool => 'qd3_scaffolding_leakage' !== $ref->check_id
			)
		);
		$leakage_refs    = array_values(
			array_filter(
				$hard,
				static fn( AssessmentFindingRef $ref ): bool => 'qd3_scaffolding_leakage' === $ref->check_id
			)
		);
		$term_refs       = array_values(
			array_filter(
				array_merge( $errors, $warnings ),
				static fn( AssessmentFindingRef $ref ): bool => DeterministicDetectorSuite::CHECK_GLOSSARY_TERM_MISSING === $ref->check_id
			)
		);
		$det_refs        = array_merge( $errors, $warnings );

		return array(
			'structural'            => new AssessmentFacet(
				'structural',
				array() === $structural_refs ? 'clean' : 'finding',
				array() === $structural_refs ? 'none' : 'hard',
				array_map( static fn( AssessmentFindingRef $r ): string => $r->check_id, $structural_refs ),
				$structural_refs
			),
			'deterministic_qa'      => new AssessmentFacet(
				'deterministic_qa',
				array() === $det_refs ? 'clean' : 'finding',
				array() === $errors ? ( array() === $warnings ? 'none' : 'warning' ) : 'error',
				array_map( static fn( AssessmentFindingRef $r ): string => $r->check_id, $det_refs ),
				$det_refs
			),
			'leakage'               => new AssessmentFacet(
				'leakage',
				$leakage_applicable
					? ( array() === $leakage_refs ? 'clean' : 'finding' )
					: 'not_applicable',
				$leakage_applicable
					? ( array() === $leakage_refs ? 'none' : 'hard' )
					: 'not_applicable',
				$leakage_applicable
					? array_map( static fn( AssessmentFindingRef $r ): string => $r->check_id, $leakage_refs )
					: $na_codes,
				$leakage_refs
			),
			'terminology'           => new AssessmentFacet(
				'terminology',
				array() === $term_refs ? 'clean' : 'finding',
				array() === $term_refs ? 'none' : 'error',
				array_map( static fn( AssessmentFindingRef $r ): string => $r->check_id, $term_refs ),
				$term_refs
			),
			'review'                => new AssessmentFacet(
				'review',
				$input->review_status,
				'review_status',
				array( $input->review_status ),
				array()
			),
			'provenance'            => new AssessmentFacet(
				'provenance',
				$provenance,
				'provenance_class',
				array( $provenance ),
				array()
			),
			'evidence_completeness' => new AssessmentFacet(
				'evidence_completeness',
				$completeness,
				'completeness',
				array( $completeness ),
				array()
			),
		);
	}

	/**
	 * Exposes hard check ids for tests (authority alignment).
	 *
	 * @return array<int, string>
	 */
	public static function hard_check_ids(): array {
		return self::HARD_CHECK_IDS;
	}
}
