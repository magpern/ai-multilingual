<?php
/**
 * Unsupported markup warning check.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Warns when target introduces script/style tags not present in source.
 */
final class UnsupportedMarkupCheck implements QACheck {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'unsupported_markup';
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
		if ( Store::FORMAT_HTML !== $text_format && Store::FORMAT_PLAIN !== $text_format ) {
			return array();
		}

		$dangerous = array( 'script', 'iframe', 'object', 'embed' );
		$found     = array();
		foreach ( $dangerous as $tag ) {
			$pattern = '/<\s*' . preg_quote( $tag, '/' ) . '\b/i';
			if ( preg_match( $pattern, $target_text ) && ! preg_match( $pattern, $source_text ) ) {
				$found[] = $tag;
			}
		}

		if ( array() === $found ) {
			return array();
		}

		return array(
			new QAIssue(
				$this->get_id(),
				$this->default_severity(),
				'Target introduces unsupported markup.',
				array( 'tags' => $found )
			),
		);
	}
}
