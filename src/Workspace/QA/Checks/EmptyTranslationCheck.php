<?php
/**
 * Empty translation check.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Flags empty target when source is non-empty.
 */
final class EmptyTranslationCheck implements QACheck {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'empty_translation';
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
		if ( '' === trim( $source_text ) || '' !== trim( $target_text ) ) {
			return array();
		}

		return array(
			new QAIssue(
				$this->get_id(),
				$this->default_severity(),
				'Translation is empty while source is not.',
				array()
			),
		);
	}
}
