<?php
/**
 * Shared deterministic QA orchestrator (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

/**
 * Runs the admitted detector suite plus scaffolding leakage.
 */
final class DeterministicQA {

	/**
	 * Suite detector.
	 *
	 * @var DeterministicDetectorSuite
	 */
	private DeterministicDetectorSuite $suite;

	/**
	 * Leakage detector.
	 *
	 * @var ScaffoldingLeakageDetector
	 */
	private ScaffoldingLeakageDetector $leakage;

	/**
	 * Builds the orchestrator.
	 *
	 * @param DeterministicDetectorSuite|null $suite   Optional suite.
	 * @param ScaffoldingLeakageDetector|null $leakage Optional leakage detector.
	 */
	public function __construct(
		?DeterministicDetectorSuite $suite = null,
		?ScaffoldingLeakageDetector $leakage = null
	) {
		$this->suite   = $suite ?? new DeterministicDetectorSuite();
		$this->leakage = $leakage ?? new ScaffoldingLeakageDetector();
	}

	/**
	 * Runs all shared detectors.
	 *
	 * @param DetectionInput $input Detection input.
	 * @return list<RawFinding>
	 */
	public function detect( DetectionInput $input ): array {
		return array_merge(
			$this->suite->detect( $input ),
			$this->leakage->detect( $input )
		);
	}
}
