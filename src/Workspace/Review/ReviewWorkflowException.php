<?php
/**
 * Review workflow domain error.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

/**
 * Raised when a review transition is illegal or conflicts with current state.
 */
final class ReviewWorkflowException extends \RuntimeException {

	/**
	 * Stable machine-readable error code.
	 *
	 * @var string
	 */
	private string $error_code;

	/**
	 * Optional structured context (e.g. refreshed segment review fields for 409).
	 *
	 * @var array<string, mixed>
	 */
	private array $context;

	/**
	 * Builds the exception.
	 *
	 * @param string               $error_code Stable error code.
	 * @param string               $message    Human-readable message.
	 * @param array<string, mixed> $context    Optional structured context.
	 * @param int                  $code       Suggested HTTP status (0 = unspecified).
	 */
	public function __construct(
		string $error_code,
		string $message,
		array $context = array(),
		int $code = 0
	) {
		parent::__construct( $message, $code );
		$this->error_code = $error_code;
		$this->context    = $context;
	}

	/**
	 * Returns the stable error code.
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * Returns optional structured context.
	 *
	 * @return array<string, mixed>
	 */
	public function get_context(): array {
		return $this->context;
	}
}
