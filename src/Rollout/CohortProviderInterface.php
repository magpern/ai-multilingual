<?php
/**
 * Reserved cohort provider interface for post-F12 expansion.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Architectural reservation only — no F12 implementation.
 *
 * Future percentage, visitor, tenant, or organization cohorts must plug in
 * here without changing {@see RolloutPolicyDecision}.
 */
interface CohortProviderInterface {

	/**
	 * Whether the request matches the cohort for the given configuration.
	 *
	 * @param RolloutPolicyRequest $request       Request facts.
	 * @param RolloutConfiguration $configuration Active configuration.
	 */
	public function matches( RolloutPolicyRequest $request, RolloutConfiguration $configuration ): bool;
}
