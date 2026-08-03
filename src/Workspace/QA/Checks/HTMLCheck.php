<?php
/**
 * HTML structure preservation check.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Ensures HTML tag inventory is preserved for html format.
 */
final class HTMLCheck implements QACheck {

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
		return 'html_tag_mismatch';
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
		if ( Store::FORMAT_HTML !== $text_format ) {
			return array();
		}

		$source_tags = $this->analyzer->extract_html_tags( $source_text );
		$target_tags = $this->analyzer->extract_html_tags( $target_text );
		$missing     = array_values( array_diff( $source_tags, $target_tags ) );

		$issues = array();
		if ( array() !== $missing ) {
			// Plain-text targets for HTML sources are common in the workspace
			// editor — warn rather than hard-block; incomplete HTML still errors.
			$severity = array() === $target_tags
				? QAIssue::SEVERITY_WARNING
				: $this->default_severity();

			$issues[] = new QAIssue(
				'html_tag_mismatch',
				$severity,
				'HTML tags missing in target.',
				array( 'missing' => $missing )
			);
		}

		if ( preg_match( '/<[^>]*$/', $target_text ) || preg_match( '/^[^<]*>/', $target_text ) ) {
			$issues[] = new QAIssue(
				'broken_formatting',
				$this->default_severity(),
				'Target appears to contain broken HTML markup.',
				array()
			);
		}

		return $issues;
	}
}
