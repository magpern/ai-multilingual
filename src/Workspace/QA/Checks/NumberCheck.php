<?php
/**
 * Number preservation check.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Warns when source numbers are missing from the target.
 */
final class NumberCheck implements QACheck {

	/**
	 * Analyzer.
	 *
	 * @var SegmentConstraintAnalyzer
	 */
	private SegmentConstraintAnalyzer $analyzer;

	/**
	 * Builds the check.
	 *
	 * @param SegmentConstraintAnalyzer|null $analyzer Analyzer.
	 */
	public function __construct( ?SegmentConstraintAnalyzer $analyzer = null ) {
		$this->analyzer = $analyzer ?? new SegmentConstraintAnalyzer();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'number_mismatch';
	}

	/**
	 * {@inheritdoc}
	 */
	public function default_severity(): string {
		return QAIssue::SEVERITY_WARNING;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $source_text Source text.
	 * @param string $target_text Target text.
	 * @param string $text_format Text format.
	 * @return list<QAIssue>
	 */
	public function check( string $source_text, string $target_text, string $text_format ): array {
		unset( $text_format );
		$missing = array();
		foreach ( $this->analyzer->extract_numbers( $source_text ) as $number ) {
			if ( ! str_contains( $target_text, $number ) ) {
				$missing[] = $number;
			}
		}

		if ( array() === $missing ) {
			return array();
		}

		return array(
			new QAIssue(
				$this->get_id(),
				$this->default_severity(),
				'Numbers from source are missing in target.',
				array( 'missing' => $missing )
			),
		);
	}
}
