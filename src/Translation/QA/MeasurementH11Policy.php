<?php
/**
 * H1.1 measurement severity / applicability policy (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

/**
 * Maps raw findings to H1.1 score_case-shaped results (+ not_applicable_count).
 */
final class MeasurementH11Policy {

	public const APPLICABILITY_APPLICABLE     = 'applicable';
	public const APPLICABILITY_NOT_APPLICABLE = 'not_applicable';

	public const CODE_LEAKAGE_NOT_APPLICABLE = 'leakage_not_applicable';

	/**
	 * Check id → H1.1 severity.
	 *
	 * @var array<string, string>
	 */
	private const SEVERITY_MAP = array(
		DeterministicDetectorSuite::CHECK_EMPTY_TARGET     => 'critical',
		DeterministicDetectorSuite::CHECK_PLACEHOLDER_LOSS => 'critical',
		DeterministicDetectorSuite::CHECK_PLACEHOLDER_ADDITION => 'critical',
		DeterministicDetectorSuite::CHECK_HTML_TAG_LOSS    => 'critical',
		DeterministicDetectorSuite::CHECK_FORBIDDEN_MARKUP => 'critical',
		DeterministicDetectorSuite::CHECK_URL_LOSS         => 'critical',
		'qd3_scaffolding_leakage'                          => 'critical',
		DeterministicDetectorSuite::CHECK_NUMBER_CORRUPTION => 'error',
		DeterministicDetectorSuite::CHECK_GLOSSARY_TERM_MISSING => 'error',
		DeterministicDetectorSuite::CHECK_UNICODE_DAMAGE   => 'error',
		DeterministicDetectorSuite::CHECK_ENTITY_DAMAGE    => 'error',
		DeterministicDetectorSuite::CHECK_SOURCE_EQUALS_TARGET => 'warning',
		DeterministicDetectorSuite::CHECK_LENGTH_RATIO     => 'warning',
		DeterministicDetectorSuite::CHECK_DUPLICATE_PARAGRAPH => 'warning',
		DeterministicDetectorSuite::CHECK_WHITESPACE_ANOMALY => 'warning',
	);

	/**
	 * Scores raw findings for H1.1 measurement.
	 *
	 * @param array<int, RawFinding> $findings Raw findings.
	 * @param DetectionInput         $input    Detection input (applicability).
	 * @return array{
	 *     findings: list<array<string,mixed>>,
	 *     critical_count: int,
	 *     error_count: int,
	 *     warning_count: int,
	 *     not_applicable_count: int,
	 *     pass: bool
	 * }
	 */
	public function score( array $findings, DetectionInput $input ): array {
		$out = array();

		if ( ! $input->markers_applicable ) {
			$out[] = array(
				'code'          => self::CODE_LEAKAGE_NOT_APPLICABLE,
				'severity'      => 'info',
				'message'       => 'Scaffolding leakage checks are not applicable (marker evidence unavailable).',
				'data'          => array(),
				'applicability' => self::APPLICABILITY_NOT_APPLICABLE,
			);
		}

		foreach ( $findings as $finding ) {
			if ( ! $finding instanceof RawFinding ) {
				continue;
			}
			$severity = self::SEVERITY_MAP[ $finding->check_id ] ?? 'warning';
			$out[]    = array(
				'code'          => $finding->check_id,
				'severity'      => $severity,
				'message'       => $finding->message,
				'data'          => $finding->evidence,
				'applicability' => self::APPLICABILITY_APPLICABLE,
			);
		}

		$critical = 0;
		$error    = 0;
		$warning  = 0;
		$na       = 0;

		foreach ( $out as $f ) {
			if ( self::APPLICABILITY_NOT_APPLICABLE === ( $f['applicability'] ?? '' ) ) {
				++$na;
				continue;
			}
			if ( 'critical' === $f['severity'] ) {
				++$critical;
			} elseif ( 'error' === $f['severity'] ) {
				++$error;
			} elseif ( 'warning' === $f['severity'] ) {
				++$warning;
			}
		}

		return array(
			'findings'             => $out,
			'critical_count'       => $critical,
			'error_count'          => $error,
			'warning_count'        => $warning,
			'not_applicable_count' => $na,
			'pass'                 => 0 === $critical,
		);
	}
}
