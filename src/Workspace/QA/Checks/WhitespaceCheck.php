<?php
/**
 * Leading/trailing whitespace anomaly check.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Warns when leading/trailing whitespace differs from source.
 */
final class WhitespaceCheck implements QACheck {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'whitespace_anomaly';
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
		if ( '' === $target_text ) {
			return array();
		}

		$source_lead  = strlen( $source_text ) - strlen( ltrim( $source_text ) );
		$target_lead  = strlen( $target_text ) - strlen( ltrim( $target_text ) );
		$source_trail = strlen( $source_text ) - strlen( rtrim( $source_text ) );
		$target_trail = strlen( $target_text ) - strlen( rtrim( $target_text ) );

		if ( $source_lead === $target_lead && $source_trail === $target_trail ) {
			return array();
		}

		return array(
			new QAIssue(
				$this->get_id(),
				$this->default_severity(),
				'Leading or trailing whitespace differs from source.',
				array(
					'source_leading'  => $source_lead,
					'target_leading'  => $target_lead,
					'source_trailing' => $source_trail,
					'target_trailing' => $target_trail,
				)
			),
		);
	}
}
