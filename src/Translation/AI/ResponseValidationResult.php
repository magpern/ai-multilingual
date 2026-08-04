<?php
/**
 * Result of structural provider-response validation (F11 §4.6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

/**
 * Success/failure payload for ResponseValidator — not a WP_Error wrapper.
 */
final class ResponseValidationResult {

	/**
	 * Builds a result.
	 *
	 * @param bool                 $valid   Whether validation passed.
	 * @param string|null          $code    Failure code when invalid.
	 * @param string               $message Human-readable message.
	 * @param array<string, mixed> $data    Optional context.
	 */
	public function __construct(
		public readonly bool $valid,
		public readonly ?string $code = null,
		public readonly string $message = '',
		public readonly array $data = array()
	) {
	}

	/**
	 * Successful validation.
	 */
	public static function ok(): self {
		return new self( true );
	}

	/**
	 * Failed validation.
	 *
	 * @param string               $code    Failure code.
	 * @param string               $message Message.
	 * @param array<string, mixed> $data    Context.
	 */
	public static function fail( string $code, string $message, array $data = array() ): self {
		return new self( false, $code, $message, $data );
	}
}
