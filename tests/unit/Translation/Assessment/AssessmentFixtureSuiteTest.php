<?php
/**
 * TI.5 additive assessment fixture suite (does not mutate C1.x / H1.x).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\Assessment;

use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Assessment\AssessmentInput;
use AIMultilingual\Translation\Assessment\RiskAssessmentPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Loads tests/assessment/fixtures/cases.json through the shared assessment core.
 */
final class AssessmentFixtureSuiteTest extends TestCase {

	/**
	 * Runs every fixture case through AssessmentAssembler.
	 *
	 * @dataProvider fixture_cases
	 * @param array<string, mixed> $fixture Fixture case.
	 */
	public function test_fixture_case( array $fixture ): void {
		$input = new AssessmentInput(
			(string) $fixture['source'],
			(string) $fixture['target'],
			(string) $fixture['format'],
			(string) $fixture['status'],
			(string) $fixture['review_status'],
			(string) ( $fixture['field_semantic'] ?? 'generic' ),
			isset( $fixture['markers'] ) && is_array( $fixture['markers'] ) ? $fixture['markers'] : array(),
			(bool) ( $fixture['markers_applicable'] ?? false ),
			isset( $fixture['glossary_terms'] ) && is_array( $fixture['glossary_terms'] ) ? $fixture['glossary_terms'] : null,
			isset( $fixture['tm_outcome_code'] ) ? (string) $fixture['tm_outcome_code'] : null
		);

		$assessment = ( new AssessmentAssembler() )->assess( $input );
		$array      = $assessment->to_array();

		if ( isset( $fixture['expect_category'] ) ) {
			$this->assertSame(
				(string) $fixture['expect_category'],
				$assessment->overall_category,
				(string) $fixture['id']
			);
		}
		if ( isset( $fixture['expect_not_category'] ) ) {
			$this->assertNotSame(
				(string) $fixture['expect_not_category'],
				$assessment->overall_category,
				(string) $fixture['id']
			);
		}
		if ( isset( $fixture['expect_completeness'] ) ) {
			$this->assertSame(
				(string) $fixture['expect_completeness'],
				$assessment->evidence_completeness,
				(string) $fixture['id']
			);
		}
		if ( isset( $fixture['expect_leakage_state'] ) ) {
			$this->assertSame(
				(string) $fixture['expect_leakage_state'],
				$assessment->facets['leakage']->state,
				(string) $fixture['id']
			);
		}
		if ( ! empty( $fixture['forbid_leakage_clean'] ) ) {
			$this->assertNotSame( 'clean', $assessment->facets['leakage']->state, (string) $fixture['id'] );
		}
		if ( isset( $fixture['expect_conflict'] ) ) {
			$this->assertContains( (string) $fixture['expect_conflict'], $assessment->conflicts, (string) $fixture['id'] );
		}
		if ( isset( $fixture['expect_provenance'] ) ) {
			$this->assertSame(
				(string) $fixture['expect_provenance'],
				$assessment->provenance_class,
				(string) $fixture['id']
			);
		}
		if ( ! empty( $fixture['forbid_publish_decision'] ) ) {
			$this->assertArrayNotHasKey( 'publish_decision', $array, (string) $fixture['id'] );
			$this->assertArrayNotHasKey( 'score', $array, (string) $fixture['id'] );
		}

		$this->assertArrayNotHasKey( 'publish_decision', $array );
		$this->assertArrayNotHasKey( 'llm_confidence', $array );
		$this->assertArrayNotHasKey( 'quality_score', $array );
		$this->assertSame( 'R1.0', $assessment->assessment_version );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public function fixture_cases(): array {
		$path = dirname( __DIR__, 3 ) . '/assessment/fixtures/cases.json';
		$raw  = file_get_contents( $path );
		$this->assertNotFalse( $raw );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );

		$out = array();
		foreach ( $decoded as $fixture ) {
			if ( ! is_array( $fixture ) || ! isset( $fixture['id'] ) ) {
				continue;
			}
			$out[ (string) $fixture['id'] ] = array( $fixture );
		}

		return $out;
	}

	public function test_false_authority_structurally_clean_is_not_publish_claim(): void {
		$input  = new AssessmentInput(
			'Hello world today',
			'Hej världen idag',
			'plain',
			'machine_translated',
			'approved',
			'generic',
			array( 'SYSTEM_INSTRUCTION_MARKER_XYZ' ),
			true
		);
		$result = ( new AssessmentAssembler() )->assess( $input )->to_array();
		$this->assertSame( 'structurally_clean', $result['overall_category'] );
		$this->assertArrayNotHasKey( 'publish_decision', $result );
		$this->assertArrayNotHasKey( 'should_publish', $result );
		$this->assertArrayNotHasKey( 'score', $result );
		$this->assertTrue( $result['dimensions_visible'] );
	}

	public function test_false_authority_many_warnings_cannot_cancel_hard(): void {
		$policy = new RiskAssessmentPolicy();
		$this->assertContains(
			\AIMultilingual\Translation\QA\DeterministicDetectorSuite::CHECK_PLACEHOLDER_LOSS,
			$policy::hard_check_ids()
		);
	}
}
