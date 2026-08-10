<?php
/**
 * ScaffoldingLeakageDetector unit tests (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\QA;

use AIMultilingual\Translation\QA\DetectionInput;
use AIMultilingual\Translation\QA\ScaffoldingLeakageDetector;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Marker leak detection — no gut_01 phrase special-case.
 */
final class ScaffoldingLeakageDetectorTest extends TestCase {

	private ScaffoldingLeakageDetector $detector;

	protected function setUp(): void {
		$this->detector = new ScaffoldingLeakageDetector();
	}

	public function test_detects_marker_leak(): void {
		$marker   = 'Glossary instructions (terminology guidance only — do not copy into the translation output):';
		$findings = $this->detector->detect(
			new DetectionInput(
				'Natural product description',
				'Ordlista: ' . $marker . ' leftover',
				Store::FORMAT_PLAIN,
				array( $marker ),
				true
			)
		);

		$this->assertCount( 1, $findings );
		$this->assertSame( 'qd3_scaffolding_leakage', $findings[0]->check_id );
	}

	public function test_markers_applicable_false_yields_empty(): void {
		$marker   = 'Glossary instructions (terminology guidance only — do not copy into the translation output):';
		$findings = $this->detector->detect(
			new DetectionInput(
				'Source',
				$marker,
				Store::FORMAT_PLAIN,
				array( $marker ),
				false
			)
		);

		$this->assertSame( array(), $findings );
	}

	public function test_does_not_special_case_gut_01_swedish_phrase(): void {
		// Without markers, Swedish glossary prose alone must not produce a finding.
		$swedish  = 'Ordlista över terminologi som ska användas konsekvent:';
		$findings = $this->detector->detect(
			new DetectionInput(
				'Ashwagandha root extract',
				$swedish . ' Ashwagandha',
				Store::FORMAT_PLAIN,
				array(),
				true
			)
		);

		$this->assertSame( array(), $findings );
	}
}
