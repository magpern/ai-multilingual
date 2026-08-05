<?php
/**
 * General availability cohort provider (F13).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Matches approved post types and languages when general_rollout_enabled is on.
 *
 * Ignores {@see RolloutConfiguration::$allowed_post_ids}. Does not implement
 * percentage, hash, visitor, tenant, or organization cohorts.
 */
final class GeneralAvailabilityCohortProvider implements CohortProviderInterface {

	/**
	 * Whether the request matches the general-availability cohort.
	 *
	 * @param RolloutPolicyRequest $request       Request facts.
	 * @param RolloutConfiguration $configuration Active configuration.
	 */
	public function matches( RolloutPolicyRequest $request, RolloutConfiguration $configuration ): bool {
		if ( ! $configuration->general_rollout_enabled ) {
			return false;
		}

		if ( $request->post_id <= 0 ) {
			return false;
		}

		$post_type = strtolower( trim( $request->post_type ) );
		if ( '' === $post_type ) {
			return false;
		}

		$allowed_types = array() !== $configuration->allowed_post_types
			? $configuration->allowed_post_types
			: RolloutConfiguration::APPROVED_POST_TYPES;

		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			return false;
		}

		$language_code = strtolower( trim( $request->language_code ) );
		if ( '' === $language_code ) {
			return false;
		}

		if ( array() === $configuration->allowed_language_codes ) {
			return true;
		}

		return in_array( $language_code, $configuration->allowed_language_codes, true );
	}
}
