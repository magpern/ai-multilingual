<?php
/**
 * Pure rollout policy decision engine.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Evaluates cohort policy and returns immutable decisions only.
 *
 * MUST NOT persist, audit, emit metrics, invalidate cache, log, or mutate config.
 */
final class RolloutPolicyService {

	/**
	 * Evaluates rollout policy for one request against one configuration.
	 *
	 * @param RolloutPolicyRequest  $request Frontend or diagnostic request facts.
	 * @param RolloutConfiguration  $config  Current validated configuration.
	 */
	public function evaluate( RolloutPolicyRequest $request, RolloutConfiguration $config ): RolloutPolicyDecision {
		try {
			return $this->evaluate_inner( $request, $config );
		} catch ( \Throwable $e ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::POLICY_ERROR,
				$config->rollout_stage,
				$config->policy_version,
			);
		}
	}

	/**
	 * @throws \InvalidArgumentException When configuration is structurally invalid for evaluation.
	 */
	private function evaluate_inner( RolloutPolicyRequest $request, RolloutConfiguration $config ): RolloutPolicyDecision {
		if ( RolloutConfiguration::SCHEMA_VERSION !== $config->schema_version ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::INVALID_CONFIGURATION,
				$config->rollout_stage,
				$config->policy_version,
			);
		}

		if ( ! $request->is_frontend ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::UNSUPPORTED_REQUEST,
				$config->rollout_stage,
				$config->policy_version,
			);
		}

		if ( 0 === $config->rollout_stage && ! $config->rollout_render_enabled ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::STAGE_DISABLED,
				$config->rollout_stage,
				$config->policy_version,
			);
		}

		if ( ! $config->rollout_render_enabled ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::ROLLOUT_DISABLED,
				$config->rollout_stage,
				$config->policy_version,
			);
		}

		$post_type_match = $this->matches_post_type( $request->post_type, $config );
		if ( ! $post_type_match ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::POST_TYPE_NOT_ALLOWED,
				$config->rollout_stage,
				$config->policy_version,
				false,
				false,
				false,
			);
		}

		$language_match = $this->matches_language( $request->language_code, $config );
		if ( ! $language_match ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::LANGUAGE_NOT_ALLOWED,
				$config->rollout_stage,
				$config->policy_version,
				false,
				false,
				false,
			);
		}

		if ( $config->requires_post_allowlist() && array() === $config->allowed_post_ids ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::POST_NOT_ALLOWLISTED,
				$config->rollout_stage,
				$config->policy_version,
				false,
				$language_match,
				$post_type_match,
			);
		}

		$cohort_match = $this->matches_post_id( $request->post_id, $config );

		if ( ! $cohort_match ) {
			return RolloutPolicyDecision::deny(
				RolloutReasonCodes::POST_NOT_ALLOWLISTED,
				$config->rollout_stage,
				$config->policy_version,
				false,
				$language_match,
				$post_type_match,
			);
		}

		return RolloutPolicyDecision::allow(
			$config->rollout_stage,
			$config->policy_version,
			true,
			$language_match,
			$post_type_match,
		);
	}

	/**
	 * Whether the post ID is allowlisted.
	 */
	private function matches_post_id( int $post_id, RolloutConfiguration $config ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		if ( array() === $config->allowed_post_ids ) {
			return ! $config->requires_post_allowlist();
		}

		return in_array( $post_id, $config->allowed_post_ids, true );
	}

	/**
	 * Whether the post type passes filters.
	 */
	private function matches_post_type( string $post_type, RolloutConfiguration $config ): bool {
		$post_type = strtolower( trim( $post_type ) );

		if ( '' === $post_type ) {
			return false;
		}

		if ( array() === $config->allowed_post_types ) {
			return in_array( $post_type, RolloutConfiguration::APPROVED_POST_TYPES, true );
		}

		return in_array( $post_type, $config->allowed_post_types, true );
	}

	/**
	 * Whether the language passes filters.
	 */
	private function matches_language( string $language_code, RolloutConfiguration $config ): bool {
		$language_code = strtolower( trim( $language_code ) );

		if ( '' === $language_code ) {
			return false;
		}

		if ( array() === $config->allowed_language_codes ) {
			return true;
		}

		return in_array( $language_code, $config->allowed_language_codes, true );
	}
}
