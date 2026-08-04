<?php
/**
 * Immutable rollout policy decision value object.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Pure decision DTO — no body text, secrets, UUIDs, or segment keys.
 */
final class RolloutPolicyDecision {

	/**
	 * Builds a decision.
	 *
	 * @param bool   $allowed           Whether active translated render is permitted.
	 * @param string $reason_code       Frozen reason code.
	 * @param int    $stage             Rollout stage (0–5).
	 * @param int    $policy_version    Config version that produced the decision.
	 * @param bool   $cohort_match      Post ID allowlist match.
	 * @param bool   $language_match    Language allowlist match.
	 * @param bool   $post_type_match   Post-type allowlist match.
	 */
	public function __construct(
		public readonly bool $allowed,
		public readonly string $reason_code,
		public readonly int $stage,
		public readonly int $policy_version,
		public readonly bool $cohort_match,
		public readonly bool $language_match,
		public readonly bool $post_type_match,
	) {
	}

	/**
	 * Creates a deny decision.
	 *
	 * @param string $reason_code     Frozen reason code.
	 * @param int    $stage           Rollout stage.
	 * @param int    $policy_version  Config version.
	 * @param bool   $cohort_match    Post ID allowlist match.
	 * @param bool   $language_match  Language allowlist match.
	 * @param bool   $post_type_match Post-type allowlist match.
	 */
	public static function deny(
		string $reason_code,
		int $stage,
		int $policy_version,
		bool $cohort_match = false,
		bool $language_match = false,
		bool $post_type_match = false,
	): self {
		return new self(
			false,
			$reason_code,
			$stage,
			$policy_version,
			$cohort_match,
			$language_match,
			$post_type_match,
		);
	}

	/**
	 * Creates an allow decision.
	 *
	 * @param int  $stage           Rollout stage.
	 * @param int  $policy_version  Config version.
	 * @param bool $cohort_match    Post ID allowlist match.
	 * @param bool $language_match  Language allowlist match.
	 * @param bool $post_type_match Post-type allowlist match.
	 */
	public static function allow(
		int $stage,
		int $policy_version,
		bool $cohort_match,
		bool $language_match,
		bool $post_type_match,
	): self {
		return new self(
			true,
			RolloutReasonCodes::ALLOWED,
			$stage,
			$policy_version,
			$cohort_match,
			$language_match,
			$post_type_match,
		);
	}
}
