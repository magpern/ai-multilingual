<?php
/**
 * Strategy F block render gate decision.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Rollout\RolloutPolicyDecision;

/**
 * Explicit allow/deny outcome from {@see BlockRenderGate}.
 */
final class RenderGateDecision {

	/**
	 * Builds a render gate decision.
	 *
	 * @param bool                       $allowed  Whether frontend block rendering may proceed.
	 * @param string                     $reason   Denial reason when not allowed.
	 * @param RolloutPolicyDecision|null $rollout  Rollout policy decision when evaluated.
	 */
	public function __construct(
		public readonly bool $allowed,
		public readonly string $reason = '',
		public readonly ?RolloutPolicyDecision $rollout = null,
	) {
	}

	/**
	 * Creates an allow decision.
	 *
	 * @param RolloutPolicyDecision|null $rollout Optional rollout decision.
	 */
	public static function allow( ?RolloutPolicyDecision $rollout = null ): self {
		return new self( true, '', $rollout );
	}

	/**
	 * Creates a deny decision.
	 *
	 * @param string                     $reason  Denial reason code.
	 * @param RolloutPolicyDecision|null $rollout Optional rollout decision.
	 */
	public static function deny( string $reason, ?RolloutPolicyDecision $rollout = null ): self {
		return new self( false, $reason, $rollout );
	}
}
