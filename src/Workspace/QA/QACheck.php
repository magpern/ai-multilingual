<?php
/**
 * Single QA check contract (ADR-F11-008).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA;

/**
 * Stateless, source-independent quality check.
 */
interface QACheck {

	/**
	 * Stable check id.
	 */
	public function get_id(): string;

	/**
	 * Default severity: error|warning|info.
	 */
	public function default_severity(): string;

	/**
	 * Evaluates source vs target.
	 *
	 * @param string $source_text Source text.
	 * @param string $target_text Target text.
	 * @param string $text_format Store text format.
	 * @return list<QAIssue>
	 */
	public function check( string $source_text, string $target_text, string $text_format ): array;
}
