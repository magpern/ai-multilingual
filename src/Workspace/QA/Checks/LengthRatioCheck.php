<?php
/**
 * Length ratio warning check.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Warns when target length diverges sharply from source.
 */
final class LengthRatioCheck implements QACheck {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'length_ratio';
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
		$source_len = strlen( trim( $source_text ) );
		$target_len = strlen( trim( $target_text ) );
		if ( $source_len < 20 || 0 === $target_len ) {
			return array();
		}

		$ratio = $target_len / $source_len;
		if ( $ratio >= 0.4 && $ratio <= 2.5 ) {
			return array();
		}

		return array(
			new QAIssue(
				$this->get_id(),
				$this->default_severity(),
				'Target length ratio is outside the expected range.',
				array(
					'ratio'      => round( $ratio, 2 ),
					'source_len' => $source_len,
					'target_len' => $target_len,
				)
			),
		);
	}
}
