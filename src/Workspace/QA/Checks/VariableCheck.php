<?php
/**
 * Variable-style token check (%s, %1$s).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Ensures printf-style variables are preserved.
 */
final class VariableCheck implements QACheck {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'variable_mismatch';
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
		if ( ! preg_match_all( '/%(?:\d+\$)?[sd]/', $source_text, $m ) ) {
			return array();
		}

		$missing = array();
		foreach ( array_unique( $m[0] ) as $token ) {
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
				'Variable tokens missing in target.',
				array( 'missing' => $missing )
			),
		);
	}
}
