<?php
/**
 * Workspace QAIssue severity policy over raw findings (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Maps check_id → Workspace severity.
 */
final class WorkspaceQAPolicy {

	/**
	 * Check id → Workspace severity.
	 *
	 * @var array<string, string>
	 */
	private const SEVERITY_MAP = array(
		DeterministicDetectorSuite::CHECK_EMPTY_TARGET     => QAIssue::SEVERITY_ERROR,
		DeterministicDetectorSuite::CHECK_PLACEHOLDER_LOSS => QAIssue::SEVERITY_ERROR,
		DeterministicDetectorSuite::CHECK_HTML_TAG_LOSS    => QAIssue::SEVERITY_ERROR,
		DeterministicDetectorSuite::CHECK_FORBIDDEN_MARKUP => QAIssue::SEVERITY_ERROR,
		DeterministicDetectorSuite::CHECK_URL_LOSS         => QAIssue::SEVERITY_ERROR,
		'qd3_scaffolding_leakage'                          => QAIssue::SEVERITY_ERROR,
		DeterministicDetectorSuite::CHECK_NUMBER_CORRUPTION => QAIssue::SEVERITY_WARNING,
		DeterministicDetectorSuite::CHECK_SOURCE_EQUALS_TARGET => QAIssue::SEVERITY_WARNING,
		DeterministicDetectorSuite::CHECK_LENGTH_RATIO     => QAIssue::SEVERITY_WARNING,
		DeterministicDetectorSuite::CHECK_DUPLICATE_PARAGRAPH => QAIssue::SEVERITY_WARNING,
		DeterministicDetectorSuite::CHECK_WHITESPACE_ANOMALY => QAIssue::SEVERITY_WARNING,
		DeterministicDetectorSuite::CHECK_GLOSSARY_TERM_MISSING => QAIssue::SEVERITY_WARNING,
		DeterministicDetectorSuite::CHECK_PLACEHOLDER_ADDITION => QAIssue::SEVERITY_WARNING,
		DeterministicDetectorSuite::CHECK_UNICODE_DAMAGE   => QAIssue::SEVERITY_INFO,
		DeterministicDetectorSuite::CHECK_ENTITY_DAMAGE    => QAIssue::SEVERITY_INFO,
	);

	/**
	 * Applies Workspace severity to raw findings.
	 *
	 * @param array<int, RawFinding> $findings Raw findings.
	 * @return array<int, QAIssue>
	 */
	public function apply( array $findings ): array {
		$issues = array();

		foreach ( $findings as $finding ) {
			if ( ! $finding instanceof RawFinding ) {
				continue;
			}
			$severity = self::SEVERITY_MAP[ $finding->check_id ] ?? QAIssue::SEVERITY_WARNING;
			$issues[] = new QAIssue(
				$finding->check_id,
				$severity,
				$finding->message,
				array(
					'evidence'      => $finding->evidence,
					'detector_meta' => $finding->detector_meta,
					'dimension'     => $finding->dimension,
					'check_version' => $finding->check_version,
				)
			);
		}

		return $issues;
	}
}
