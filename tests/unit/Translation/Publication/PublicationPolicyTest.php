<?php
/**
 * TI.7 PublicationPolicy false-authority and eligibility guards.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\Publication;

use AIMultilingual\Translation\Assessment\AssessmentCategory;
use AIMultilingual\Translation\Assessment\EvidenceCompleteness;
use AIMultilingual\Translation\Assessment\ProvenanceClass;
use AIMultilingual\Translation\Assessment\TranslationAssessment;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationReasonCodes;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Mandatory negative cases — structurally_clean alone is never authority.
 */
final class PublicationPolicyTest extends TestCase {

	private PublicationPolicy $policy;

	protected function setUp(): void {
		$this->policy = new PublicationPolicy();
	}

	public function test_blocked_never_auto_publishes(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::BLOCKED ),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_APPROVED,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::ASSESSMENT_BLOCKED, $decision->reason_codes );
	}

	public function test_needs_review_never_auto_publishes(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::NEEDS_REVIEW ),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_NOT_SUBMITTED,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::ASSESSMENT_NEEDS_REVIEW, $decision->reason_codes );
	}

	public function test_review_recommended_never_auto_publishes(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::REVIEW_RECOMMENDED ),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_NOT_SUBMITTED,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::ASSESSMENT_REVIEW_RECOMMENDED, $decision->reason_codes );
	}

	public function test_structurally_clean_alone_is_insufficient_for_auto(): void {
		$decision = $this->policy->evaluate(
			$this->assessment(
				AssessmentCategory::STRUCTURALLY_CLEAN,
				EvidenceCompleteness::PARTIAL,
				ProvenanceClass::AI_GENERATED
			),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_NOT_SUBMITTED,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::EVIDENCE_INCOMPLETE, $decision->reason_codes );
	}

	public function test_incomplete_evidence_blocks_auto(): void {
		$decision = $this->policy->evaluate(
			$this->assessment(
				AssessmentCategory::STRUCTURALLY_CLEAN,
				EvidenceCompleteness::UNAVAILABLE,
				ProvenanceClass::AI_GENERATED
			),
			PublicationMode::APPROVED_ONLY,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_APPROVED,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::EVIDENCE_INCOMPLETE, $decision->reason_codes );
	}

	public function test_manual_mode_disables_automation(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::STRUCTURALLY_CLEAN ),
			PublicationMode::MANUAL,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_APPROVED,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::AUTOMATION_DISABLED, $decision->reason_codes );
	}

	public function test_approved_only_requires_human_approval(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::STRUCTURALLY_CLEAN ),
			PublicationMode::APPROVED_ONLY,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_PENDING,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::HUMAN_APPROVAL_REQUIRED, $decision->reason_codes );
	}

	public function test_controlled_auto_blocks_missing_provenance(): void {
		$decision = $this->policy->evaluate(
			$this->assessment(
				AssessmentCategory::STRUCTURALLY_CLEAN,
				EvidenceCompleteness::COMPLETE,
				ProvenanceClass::MISSING
			),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_NOT_SUBMITTED,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::PROVENANCE_NOT_ALLOWED, $decision->reason_codes );
	}

	public function test_controlled_auto_blocks_unknown_provenance(): void {
		$decision = $this->policy->evaluate(
			$this->assessment(
				AssessmentCategory::STRUCTURALLY_CLEAN,
				EvidenceCompleteness::COMPLETE,
				ProvenanceClass::UNKNOWN
			),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_NOT_SUBMITTED,
			true,
			false,
			true
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::PROVENANCE_NOT_ALLOWED, $decision->reason_codes );
	}

	public function test_rejected_review_blocks_manual_and_auto(): void {
		$manual = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::STRUCTURALLY_CLEAN ),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_REJECTED,
			true,
			false,
			false
		);
		$auto   = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::STRUCTURALLY_CLEAN ),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_REJECTED,
			true,
			false,
			true
		);

		$this->assertFalse( $manual->eligible );
		$this->assertFalse( $auto->eligible );
		$this->assertContains( PublicationReasonCodes::REVIEW_REJECTED, $manual->reason_codes );
		$this->assertContains( PublicationReasonCodes::REVIEW_REJECTED, $auto->reason_codes );
	}

	public function test_non_public_source_blocks_publish(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::STRUCTURALLY_CLEAN ),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_APPROVED,
			false,
			false,
			false
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::SOURCE_NOT_PUBLIC, $decision->reason_codes );
	}

	public function test_stale_translation_blocks_publish(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::STRUCTURALLY_CLEAN ),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_APPROVED,
			true,
			true,
			false
		);

		$this->assertFalse( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::TRANSLATION_STALE, $decision->reason_codes );
	}

	public function test_already_published_is_eligible_noop_signal(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::BLOCKED ),
			PublicationMode::MANUAL,
			Store::PUBLISH_PUBLISHED,
			Store::REVIEW_REJECTED,
			false,
			true,
			true
		);

		$this->assertTrue( $decision->eligible );
		$this->assertContains( PublicationReasonCodes::PUBLICATION_ALREADY_ACTIVE, $decision->reason_codes );
	}

	public function test_decision_exposes_policy_version_and_no_score(): void {
		$decision = $this->policy->evaluate(
			$this->assessment( AssessmentCategory::STRUCTURALLY_CLEAN ),
			PublicationMode::CONTROLLED_AUTO,
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_NOT_SUBMITTED,
			true,
			false,
			true
		);
		$array    = $decision->to_array();

		$this->assertSame( 'P1.0', $decision->policy_version );
		$this->assertArrayNotHasKey( 'score', $array );
		$this->assertArrayNotHasKey( 'confidence', $array );
		$this->assertArrayHasKey( 'reason_codes', $array );
	}

	/**
	 * Builds a minimal TranslationAssessment for policy input.
	 *
	 * @param string $category             AssessmentCategory value.
	 * @param string $evidence_completeness EvidenceCompleteness value.
	 * @param string $provenance           ProvenanceClass value.
	 */
	private function assessment(
		string $category,
		string $evidence_completeness = EvidenceCompleteness::COMPLETE,
		string $provenance = ProvenanceClass::AI_GENERATED
	): TranslationAssessment {
		return new TranslationAssessment(
			TranslationAssessment::VERSION,
			TranslationAssessment::METHODOLOGY_REF,
			$category,
			array(),
			array(),
			array(),
			array(),
			Store::REVIEW_NOT_SUBMITTED,
			$provenance,
			array(),
			$evidence_completeness,
			true
		);
	}
}
