<?php
/**
 * Raised when the QA freshness check cannot be evaluated at approve time.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

/**
 * Maps to `aiml_review_qa_unavailable` / HTTP 503 (ADR-0015 §4.2). No state
 * transition happens when this is thrown.
 */
final class ReviewQAUnavailableException extends \RuntimeException {

	/**
	 * Builds the exception.
	 *
	 * @param string          $message  Human-readable message.
	 * @param \Throwable|null $previous Underlying failure, if any.
	 */
	public function __construct( string $message = 'Quality checks are temporarily unavailable.', ?\Throwable $previous = null ) {
		parent::__construct( $message, 503, $previous );
	}
}
