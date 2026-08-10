<?php
/**
 * Canonical TI.7 PublicationPolicy (ADR-0020) — pure / non-mutating.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Publication;

use AIMultilingual\Translation\Assessment\AssessmentCategory;
use AIMultilingual\Translation\Assessment\EvidenceCompleteness;
use AIMultilingual\Translation\Assessment\ProvenanceClass;
use AIMultilingual\Translation\Assessment\TranslationAssessment;
use AIMultilingual\Translation\Store;

/**
 * Deterministic publication eligibility from frozen upstream evidence + settings.
 */
final class PublicationPolicy {

	public const VERSION = PublicationDecision::VERSION;

	/**
	 * Evaluates whether a translation may be published under the active mode.
	 *
	 * @param TranslationAssessment $assessment     TI.5 R1.0 assessment.
	 * @param string                $mode           Publication mode.
	 * @param string                $publish_status Current publish_status.
	 * @param string                $review_status  Current review_status.
	 * @param bool                  $source_public  Source object public visibility.
	 * @param bool                  $is_stale       Translation is_stale flag.
	 * @param bool                  $for_automatic  Whether this is an automatic path.
	 */
	public function evaluate(
		TranslationAssessment $assessment,
		string $mode,
		string $publish_status,
		string $review_status,
		bool $source_public,
		bool $is_stale,
		bool $for_automatic = false
	): PublicationDecision {
		if ( ! PublicationMode::is_valid( $mode ) ) {
			$mode = PublicationMode::MANUAL;
		}

		$reasons = array();
		$eligible = true;

		if ( Store::PUBLISH_PUBLISHED === $publish_status ) {
			return new PublicationDecision(
				self::VERSION,
				true,
				array( PublicationReasonCodes::PUBLICATION_ALREADY_ACTIVE ),
				$mode,
				$assessment->assessment_version,
				$assessment->overall_category,
				$publish_status,
				$review_status,
				$assessment->evidence_completeness,
				$assessment->provenance_class,
				$source_public,
				$is_stale,
				array( 'already_published' => true )
			);
		}

		if ( AssessmentCategory::BLOCKED === $assessment->overall_category ) {
			$eligible  = false;
			$reasons[] = PublicationReasonCodes::ASSESSMENT_BLOCKED;
		}

		if ( Store::REVIEW_REJECTED === $review_status ) {
			$eligible  = false;
			$reasons[] = PublicationReasonCodes::REVIEW_REJECTED;
		}

		if ( ! $source_public ) {
			$eligible  = false;
			$reasons[] = PublicationReasonCodes::SOURCE_NOT_PUBLIC;
		}

		if ( $is_stale ) {
			$eligible  = false;
			$reasons[] = PublicationReasonCodes::TRANSLATION_STALE;
		}

		if ( $for_automatic ) {
			if ( PublicationMode::MANUAL === $mode ) {
				$eligible  = false;
				$reasons[] = PublicationReasonCodes::AUTOMATION_DISABLED;
			}

			if ( AssessmentCategory::NEEDS_REVIEW === $assessment->overall_category ) {
				$eligible  = false;
				$reasons[] = PublicationReasonCodes::ASSESSMENT_NEEDS_REVIEW;
			}

			if ( AssessmentCategory::REVIEW_RECOMMENDED === $assessment->overall_category ) {
				$eligible  = false;
				$reasons[] = PublicationReasonCodes::ASSESSMENT_REVIEW_RECOMMENDED;
			}

			if ( EvidenceCompleteness::COMPLETE !== $assessment->evidence_completeness ) {
				$eligible  = false;
				$reasons[] = PublicationReasonCodes::EVIDENCE_INCOMPLETE;
			}

			if ( PublicationMode::APPROVED_ONLY === $mode && Store::REVIEW_APPROVED !== $review_status ) {
				$eligible  = false;
				$reasons[] = PublicationReasonCodes::HUMAN_APPROVAL_REQUIRED;
			}

			if ( PublicationMode::CONTROLLED_AUTO === $mode ) {
				if ( AssessmentCategory::STRUCTURALLY_CLEAN !== $assessment->overall_category ) {
					$eligible  = false;
					$reasons[] = PublicationReasonCodes::STRUCTURALLY_CLEAN_REQUIRED;
				}

				if ( in_array(
					$assessment->provenance_class,
					array( ProvenanceClass::MISSING, ProvenanceClass::UNKNOWN ),
					true
				) ) {
					$eligible  = false;
					$reasons[] = PublicationReasonCodes::PROVENANCE_NOT_ALLOWED;
				}
			}
		}

		$reasons = array_values( array_unique( $reasons ) );
		if ( $eligible && array() === $reasons ) {
			$reasons[] = PublicationReasonCodes::ELIGIBLE;
		}

		return new PublicationDecision(
			self::VERSION,
			$eligible,
			$reasons,
			$mode,
			$assessment->assessment_version,
			$assessment->overall_category,
			$publish_status,
			$review_status,
			$assessment->evidence_completeness,
			$assessment->provenance_class,
			$source_public,
			$is_stale
		);
	}
}
