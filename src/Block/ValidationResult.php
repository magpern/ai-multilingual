<?php
/**
 * Block structure validation result.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Outcome of adapter block-structure validation.
 */
final class ValidationResult {

	/**
	 * Builds a validation result.
	 *
	 * @param bool     $valid   Whether the block structure is valid.
	 * @param string[] $reasons Failure reasons when invalid.
	 */
	public function __construct(
		public readonly bool $valid,
		public readonly array $reasons = array(),
	) {
	}

	/**
	 * Creates a successful validation result.
	 */
	public static function valid(): self {
		return new self( true );
	}

	/**
	 * Creates a failed validation result.
	 *
	 * @param string[] $reasons Failure reasons.
	 */
	public static function invalid( array $reasons ): self {
		return new self( false, $reasons );
	}
}
