<?php
/**
 * TI.5 AssessmentAssembler — one DeterministicQA pass.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\Assessment;

use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Assessment\AssessmentCategory;
use AIMultilingual\Translation\Assessment\AssessmentInput;
use AIMultilingual\Translation\Assessment\EvidenceCompleteness;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Assembler integration with shared detectors.
 */
final class AssessmentAssemblerTest extends TestCase {

	public function test_empty_target_blocks(): void {
		$assessment = ( new AssessmentAssembler() )->assess(
			new AssessmentInput(
				'Hello world',
				'',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_NOT_SUBMITTED
			)
		);
		$this->assertSame( AssessmentCategory::BLOCKED, $assessment->overall_category );
		$this->assertNotEmpty( $assessment->hard_blockers );
	}

	public function test_placeholder_loss_blocks(): void {
		$assessment = ( new AssessmentAssembler() )->assess(
			new AssessmentInput(
				'Hello %s',
				'Hello',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED
			)
		);
		$this->assertSame( AssessmentCategory::BLOCKED, $assessment->overall_category );
	}

	public function test_markers_unavailable_does_not_claim_clean_leakage(): void {
		$assessment = ( new AssessmentAssembler() )->assess(
			new AssessmentInput(
				'Hello world today',
				'Hej världen idag',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_APPROVED,
				'generic',
				array(),
				false
			)
		);
		$this->assertSame( EvidenceCompleteness::UNAVAILABLE, $assessment->evidence_completeness );
		$this->assertSame( 'not_applicable', $assessment->facets['leakage']->state );
	}

	public function test_assess_segment_dto_shape(): void {
		$assessment = ( new AssessmentAssembler() )->assess_segment(
			array(
				'source_text'     => 'Hello world today',
				'translated_text' => 'Hej världen idag',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
				'review_status'   => Store::REVIEW_APPROVED,
			)
		);
		$array      = $assessment->to_array();
		$this->assertSame( 'R1.0', $array['assessment_version'] );
		$this->assertTrue( $array['dimensions_visible'] );
		$this->assertArrayHasKey( 'facets', $array );
		$this->assertArrayHasKey( 'structural', $array['facets'] );
	}

	public function test_applicable_leakage_detects_marker(): void {
		$marker     = 'SCAFFOLD_MARKER_UNIQUE_XYZ';
		$assessment = ( new AssessmentAssembler() )->assess(
			new AssessmentInput(
				'Hello world today',
				$marker . ' Hej',
				Store::FORMAT_PLAIN,
				Store::STATUS_MACHINE_TRANSLATED,
				Store::REVIEW_NOT_SUBMITTED,
				'generic',
				array( $marker ),
				true
			)
		);
		$this->assertSame( AssessmentCategory::BLOCKED, $assessment->overall_category );
	}
}
