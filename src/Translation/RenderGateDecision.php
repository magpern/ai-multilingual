<?php
/**
 * Strategy F block render gate decision.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

/**
 * Explicit allow/deny outcome from {@see BlockRenderGate}.
 */
final class RenderGateDecision {

	/**
	 * Builds a render gate decision.
	 *
	 * @param bool   $allowed Whether frontend block rendering may proceed.
	 * @param string $reason  Denial reason when not allowed.
	 */
	public function __construct(
		public readonly bool $allowed,
		public readonly string $reason = '',
	) {
	}

	/**
	 * Creates an allow decision.
	 */
	public static function allow(): self {
		return new self( true );
	}

	/**
	 * Creates a deny decision.
	 *
	 * @param string $reason Denial reason code.
	 */
	public static function deny( string $reason ): self {
		return new self( false, $reason );
	}
}
