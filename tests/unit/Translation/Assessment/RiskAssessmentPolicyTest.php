<?php
/**
 * TI.5 RiskAssessmentPolicy precedence and authority boundaries.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\Assessment;

use AIMultilingual\Translation\AI\FieldSemantic;
use AIMultilingual\Translation\Assessment\AssessmentCategory;
use AIMultilingual\Translation\Assessment\AssessmentInput;
use AIMultilingual\Translation\Assessment\EvidenceCompleteness;
use AIMultilingual\Translation\Assessment\ProvenanceClass;
use AIMultilingual\Translation\Assessment\RiskAssessmentPolicy;
use AIMultilingual\Translation\Assessment\TranslationAssessment;
use AIMultilingual\Translation\Memory\TMGenerationOutcome;
use AIMultilingual\Translation\QA\DeterministicDetectorSuite;
use AIMultilingual\Translation\QA\RawFinding;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * False-authority and precedence matrix for TI.5.
 */
final class RiskAssessmentPolicyTest extends TestCase {

	private RiskAssessmentPolicy $policy;

	protected function setUp(): void {
		$this->policy = new RiskAssessmentPolicy();
	}

	public function test_hard_blocker_forces_blocked(): void {
		$finding = new RawFinding(
			DeterministicDetectorSuite::CHECK_PLACEHOLDER_LOSS,
			'1',
			RawFinding::DIMENSION_STRUCTURAL,
			'Placeholder missing.',
			array( 'token' => '%s' )
		);
		$result  = $this->policy->assess(
			new AssessmentInput( 'Hello %s', 'Hello', Store::FORMAT_PLAIN, Store::STATUS_MACHINE_TRANSLATED ),
			array( $finding )
		);
		$this->assertSame( AssessmentCategory::BLOCKED, $result->overall_category );
		$this->assertCount( 1, $result->hard_blockers );
		$this->assertArrayNotHasKey( 'publish_decision', $result->to_array() );
		$this->assertArrayNotHasKey( 'score', $result->to_array() );
		$this->assertSame( TranslationAssessment::VERSION, $result->assessment_version );
	}

	public function test_warning_only_is_review_recommended_not_blocked(): void {
		$finding = new RawFinding(
			DeterministicDetectorSuite::CHECK_LENGTH_RATIO,
			'1',
			RawFinding::DIMENSION_SOFT,
			'Length anomaly.'
		);
		$result  = $this->policy->assess(
			new AssessmentInput(
				str_repeat( 'a', 40 ),
				'x',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_APPROVED
			),
			array( $finding )
		);
		$this->assertSame( AssessmentCategory::REVIEW_RECOMMENDED, $result->overall_category );
		$this->assertSame( array(), $result->hard_blockers );
	}

	public function test_many_warnings_cannot_cancel_hard(): void {
		$hard   = new RawFinding(
			DeterministicDetectorSuite::CHECK_URL_LOSS,
			'1',
			RawFinding::DIMENSION_STRUCTURAL,
			'URL missing.',
			array( 'url' => 'https://example.com' )
		);
		$soft   = new RawFinding(
			DeterministicDetectorSuite::CHECK_LENGTH_RATIO,
			'1',
			RawFinding::DIMENSION_SOFT,
			'Length anomaly.'
		);
		$result = $this->policy->assess(
			new AssessmentInput( 'See https://example.com', 'See site', Store::FORMAT_PLAIN, Store::STATUS_MACHINE_TRANSLATED ),
			array( $soft, $soft, $soft, $hard )
		);
		$this->assertSame( AssessmentCategory::BLOCKED, $result->overall_category );
	}

	public function test_approved_with_hard_emits_conflict_and_stays_blocked(): void {
		$finding = new RawFinding(
			DeterministicDetectorSuite::CHECK_FORBIDDEN_MARKUP,
			'1',
			RawFinding::DIMENSION_STRUCTURAL,
			'Dangerous markup.'
		);
		$result  = $this->policy->assess(
			new AssessmentInput(
				'Safe',
				'<script>x</script>',
				Store::FORMAT_HTML,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_APPROVED
			),
			array( $finding )
		);
		$this->assertSame( AssessmentCategory::BLOCKED, $result->overall_category );
		$this->assertContains( RiskAssessmentPolicy::CONFLICT_APPROVED_WITH_HARD, $result->conflicts );
	}

	public function test_leakage_not_applicable_when_markers_unavailable(): void {
		$result = $this->policy->assess(
			new AssessmentInput(
				'Hello',
				'Hej',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_NOT_SUBMITTED,
				FieldSemantic::GENERIC,
				array(),
				false
			),
			array()
		);
		$this->assertSame( EvidenceCompleteness::UNAVAILABLE, $result->evidence_completeness );
		$this->assertSame( 'not_applicable', $result->facets['leakage']->state );
		$this->assertNotSame( 'clean', $result->facets['leakage']->state );
	}

	public function test_leakage_applicable_critical_is_hard(): void {
		$finding = new RawFinding(
			'qd3_scaffolding_leakage',
			'1',
			RawFinding::DIMENSION_LEAKAGE,
			'Marker leaked.',
			array( 'marker' => 'SYSTEM_INSTRUCTION_MARKER' )
		);
		$result  = $this->policy->assess(
			new AssessmentInput(
				'Hello',
				'SYSTEM_INSTRUCTION_MARKER Hej',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_NOT_SUBMITTED,
				FieldSemantic::GENERIC,
				array( 'SYSTEM_INSTRUCTION_MARKER' ),
				true
			),
			array( $finding )
		);
		$this->assertSame( AssessmentCategory::BLOCKED, $result->overall_category );
		$this->assertSame( EvidenceCompleteness::COMPLETE, $result->evidence_completeness );
	}

	public function test_unreviewed_clean_machine_is_review_recommended(): void {
		$result = $this->policy->assess(
			new AssessmentInput(
				'Hello world today',
				'Hej världen idag',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_NOT_SUBMITTED
			),
			array()
		);
		$this->assertSame( AssessmentCategory::REVIEW_RECOMMENDED, $result->overall_category );
		$this->assertSame( ProvenanceClass::AI_GENERATED, $result->provenance_class );
	}

	public function test_approved_clean_is_structurally_clean(): void {
		$result = $this->policy->assess(
			new AssessmentInput(
				'Hello world today',
				'Hej världen idag',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_APPROVED
			),
			array()
		);
		$this->assertSame( AssessmentCategory::STRUCTURALLY_CLEAN, $result->overall_category );
	}

	public function test_error_lane_needs_review(): void {
		$finding = new RawFinding(
			DeterministicDetectorSuite::CHECK_NUMBER_CORRUPTION,
			'1',
			RawFinding::DIMENSION_STRUCTURAL,
			'Number missing.'
		);
		$result  = $this->policy->assess(
			new AssessmentInput( 'Order 42', 'Order', Store::FORMAT_PLAIN, Store::STATUS_MACHINE_TRANSLATED, Store::REVIEW_APPROVED ),
			array( $finding )
		);
		$this->assertSame( AssessmentCategory::NEEDS_REVIEW, $result->overall_category );
	}

	public function test_tm_direct_reuse_provenance_not_publish_claim(): void {
		$result = $this->policy->assess(
			new AssessmentInput(
				'Hello world today',
				'Hej världen idag',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_APPROVED,
				FieldSemantic::GENERIC,
				array(),
				false,
				null,
				TMGenerationOutcome::DIRECT_REUSE
			),
			array()
		);
		$this->assertSame( ProvenanceClass::TM_DIRECT_REUSE, $result->provenance_class );
		$this->assertSame( AssessmentCategory::STRUCTURALLY_CLEAN, $result->overall_category );
		$this->assertArrayNotHasKey( 'publish_decision', $result->to_array() );
	}

	public function test_ui_label_source_equals_does_not_escalate(): void {
		$finding = new RawFinding(
			DeterministicDetectorSuite::CHECK_SOURCE_EQUALS_TARGET,
			'1',
			RawFinding::DIMENSION_SOFT,
			'Source equals target.'
		);
		$result  = $this->policy->assess(
			new AssessmentInput(
				'Biopentra',
				'Biopentra',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_APPROVED,
				FieldSemantic::UI_LABEL
			),
			array( $finding )
		);
		$this->assertSame( AssessmentCategory::STRUCTURALLY_CLEAN, $result->overall_category );
		$this->assertNotEmpty( $result->warnings );
	}

	public function test_glossary_missing_is_error_lane(): void {
		$finding = new RawFinding(
			DeterministicDetectorSuite::CHECK_GLOSSARY_TERM_MISSING,
			'1',
			RawFinding::DIMENSION_TERMINOLOGY,
			'Preferred term missing.'
		);
		$result  = $this->policy->assess(
			new AssessmentInput( 'Use collagen', 'Använd stuff', Store::FORMAT_PLAIN, Store::STATUS_MACHINE_TRANSLATED, Store::REVIEW_APPROVED ),
			array( $finding )
		);
		$this->assertSame( AssessmentCategory::NEEDS_REVIEW, $result->overall_category );
		$this->assertSame( 'finding', $result->facets['terminology']->state );
	}

	public function test_dimensions_visible_always_true(): void {
		$result = $this->policy->assess(
			new AssessmentInput( 'a', 'b', Store::FORMAT_PLAIN, Store::STATUS_MANUALLY_EDITED, Store::REVIEW_APPROVED ),
			array()
		);
		$this->assertTrue( $result->dimensions_visible );
		$this->assertSame( ProvenanceClass::MANUALLY_EDITED, $result->provenance_class );
	}

	public function test_rejected_review_is_review_recommended_when_clean(): void {
		$result = $this->policy->assess(
			new AssessmentInput(
				'Hello world today',
				'Hej världen idag',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_REJECTED
			),
			array()
		);
		$this->assertSame( AssessmentCategory::REVIEW_RECOMMENDED, $result->overall_category );
	}

	public function test_no_llm_confidence_or_score_fields(): void {
		$result = $this->policy->assess(
			new AssessmentInput( 'Hello', 'Hej', Store::FORMAT_PLAIN, Store::STATUS_MACHINE_TRANSLATED, Store::REVIEW_APPROVED ),
			array()
		)->to_array();
		$this->assertArrayNotHasKey( 'confidence', $result );
		$this->assertArrayNotHasKey( 'llm_confidence', $result );
		$this->assertArrayNotHasKey( 'score', $result );
		$this->assertArrayNotHasKey( 'quality_score', $result );
		$this->assertArrayNotHasKey( 'publish_decision', $result );
		$this->assertArrayNotHasKey( 'should_publish', $result );
	}
}
