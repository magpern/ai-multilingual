<?php
/**
 * Aggregated QA evaluation for one segment.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA;

/**
 * Issues plus summary counts for meta.qa.
 */
final class QAResult {

	/**
	 * Builds a result.
	 *
	 * @param array<int, QAIssue> $issues Issues found.
	 */
	public function __construct(
		public readonly array $issues
	) {
	}

	/**
	 * Summary counts.
	 *
	 * @return array{errors: int, warnings: int, info: int}
	 */
	public function summary(): array {
		$errors   = 0;
		$warnings = 0;
		$info     = 0;
		foreach ( $this->issues as $issue ) {
			switch ( $issue->severity ) {
				case QAIssue::SEVERITY_ERROR:
					++$errors;
					break;
				case QAIssue::SEVERITY_WARNING:
					++$warnings;
					break;
				default:
					++$info;
					break;
			}
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
			'info'     => $info,
		);
	}

	/**
	 * Whether any error-severity issues exist.
	 */
	public function has_errors(): bool {
		return $this->summary()['errors'] > 0;
	}

	/**
	 * Builds the meta.qa array shape.
	 *
	 * @return array{issues: list<array<string, mixed>>, summary: array{errors: int, warnings: int, info: int}}
	 */
	public function to_array(): array {
		return array(
			'issues'  => array_map(
				static fn( QAIssue $issue ): array => $issue->to_array(),
				$this->issues
			),
			'summary' => $this->summary(),
		);
	}
}
