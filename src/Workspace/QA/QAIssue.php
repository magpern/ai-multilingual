<?php
/**
 * One QA issue (content-only; no origin).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA;

/**
 * Immutable QA finding.
 */
final class QAIssue {

	public const SEVERITY_ERROR   = 'error';
	public const SEVERITY_WARNING = 'warning';
	public const SEVERITY_INFO    = 'info';

	/**
	 * Builds an issue.
	 *
	 * @param string               $code     Issue code.
	 * @param string               $severity Severity.
	 * @param string               $message  Human message.
	 * @param array<string, mixed> $details  Structured details.
	 */
	public function __construct(
		public readonly string $code,
		public readonly string $severity,
		public readonly string $message,
		public readonly array $details = array()
	) {
	}

	/**
	 * REST/ViewModel array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'code'     => $this->code,
			'severity' => $this->severity,
			'message'  => $this->message,
			'details'  => $this->details,
		);
	}
}
