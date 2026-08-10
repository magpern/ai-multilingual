<?php
/**
 * Policy asymmetry: same raw finding → different consumer severities (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\QA;

use AIMultilingual\Translation\AI\ResponseValidator;
use AIMultilingual\Translation\QA\DetectionInput;
use AIMultilingual\Translation\QA\DeterministicDetectorSuite;
use AIMultilingual\Translation\QA\MeasurementH11Policy;
use AIMultilingual\Translation\QA\PersistSafetyPolicy;
use AIMultilingual\Translation\QA\RawFinding;
use AIMultilingual\Translation\QA\WorkspaceQAPolicy;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\QA\QAIssue;
use PHPUnit\Framework\TestCase;

/**
 * Documents intentional Persist / Workspace / H1.1 asymmetries.
 */
final class PolicyAsymmetryTest extends TestCase {

	public function test_empty_target_maps_differently_across_policies(): void {
		$finding  = new RawFinding(
			DeterministicDetectorSuite::CHECK_EMPTY_TARGET,
			'1',
			RawFinding::DIMENSION_STRUCTURAL,
			'Translation target is empty while source is not.'
		);
		$findings = array( $finding );
		$input    = new DetectionInput( 'Hello', '', Store::FORMAT_PLAIN, array(), false );

		$persist = ( new PersistSafetyPolicy() )->evaluate( $findings );
		$this->assertFalse( $persist->valid );
		$this->assertSame( ResponseValidator::CODE_EMPTY_TARGET, $persist->code );

		$workspace = ( new WorkspaceQAPolicy() )->apply( $findings );
		$this->assertCount( 1, $workspace );
		// Documented asymmetry: Workspace clear-on-save keeps empty as WARNING.
		$this->assertSame( QAIssue::SEVERITY_WARNING, $workspace[0]->severity );
		$this->assertSame( 'empty_translation', $workspace[0]->code );

		$h11 = ( new MeasurementH11Policy() )->score( $findings, $input );
		$this->assertSame( 1, $h11['critical_count'] );
		$this->assertFalse( $h11['pass'] );
		$this->assertGreaterThanOrEqual( 1, $h11['not_applicable_count'] );

		$critical = null;
		foreach ( $h11['findings'] as $f ) {
			if ( DeterministicDetectorSuite::CHECK_EMPTY_TARGET === $f['code'] ) {
				$critical = $f;
			}
		}
		$this->assertNotNull( $critical );
		$this->assertSame( 'critical', $critical['severity'] );
	}

	public function test_number_corruption_not_persist_block(): void {
		$finding = new RawFinding(
			DeterministicDetectorSuite::CHECK_NUMBER_CORRUPTION,
			'1',
			RawFinding::DIMENSION_STRUCTURAL,
			'Normalized number signatures from source are missing in target.',
			array( 'missing_signatures' => array( '42' ) )
		);

		$persist = ( new PersistSafetyPolicy() )->evaluate( array( $finding ) );
		$this->assertTrue( $persist->valid );

		$workspace = ( new WorkspaceQAPolicy() )->apply( array( $finding ) );
		$this->assertSame( QAIssue::SEVERITY_WARNING, $workspace[0]->severity );

		$h11 = ( new MeasurementH11Policy() )->score(
			array( $finding ),
			new DetectionInput( 'Order 42', 'Order 99', Store::FORMAT_PLAIN, array(), true )
		);
		$this->assertSame( 0, $h11['critical_count'] );
		$this->assertSame( 1, $h11['error_count'] );
		$this->assertTrue( $h11['pass'] );
	}
}
