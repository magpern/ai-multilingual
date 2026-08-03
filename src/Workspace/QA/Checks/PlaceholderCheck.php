<?php
/**
 * Placeholder token preservation check.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Ensures source placeholders appear in the target.
 */
final class PlaceholderCheck implements QACheck {

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
		return 'placeholder_mismatch';
	}

	/**
	 * {@inheritdoc}
	 */
	public function default_severity(): string {
		return QAIssue::SEVERITY_ERROR;
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
		foreach ( $this->analyzer->extract_placeholders( $source_text ) as $token ) {
			if ( ! str_contains( $target_text, $token ) ) {
				$missing[] = $token;
			}
		}

		if ( array() === $missing ) {
			return array();
		}

		return array(
			new QAIssue(
				$this->get_id(),
				$this->default_severity(),
				'Placeholder tokens missing in target.',
				array( 'missing' => $missing )
			),
		);
	}
}
