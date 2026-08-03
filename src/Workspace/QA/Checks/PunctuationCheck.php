<?php
/**
 * End punctuation delta check.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Warns when terminal punctuation differs between source and target.
 */
final class PunctuationCheck implements QACheck {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'punctuation_delta';
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
		$source = rtrim( $source_text );
		$target = rtrim( $target_text );
		if ( '' === $source || '' === $target ) {
			return array();
		}

		$source_end = substr( $source, -1 );
		$target_end = substr( $target, -1 );
		$marks      = array( '.', '!', '?', '…', '。', '！', '？' );

		$source_has = in_array( $source_end, $marks, true );
		$target_has = in_array( $target_end, $marks, true );

		if ( $source_has === $target_has && ( ! $source_has || $source_end === $target_end ) ) {
			return array();
		}

		return array(
			new QAIssue(
				$this->get_id(),
				$this->default_severity(),
				'Terminal punctuation differs from source.',
				array(
					'source_end' => $source_end,
					'target_end' => $target_end,
				)
			),
		);
	}
}
