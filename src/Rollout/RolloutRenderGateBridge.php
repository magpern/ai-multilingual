<?php
/**
 * Rollout evaluation bridge for the render gate.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Translation\RenderGateContext;
use AIMultilingual\Translation\RenderGateDecision;
use WP_Post;

/**
 * Applies rollout policy after base Strategy F gate checks pass.
 *
 * Side effects (metrics) occur outside via {@see RolloutPolicyDecision} on the decision object.
 */
final class RolloutRenderGateBridge {

	public const REASON_SHADOW_MODE = 'rollout_shadow_mode';

	/**
	 * Builds the rollout render gate bridge.
	 *
	 * @param RolloutPolicyService           $policy      Pure policy engine.
	 * @param RolloutConfigurationRepository $config_repo Configuration store.
	 */
	public function __construct(
		private RolloutPolicyService $policy,
		private RolloutConfigurationRepository $config_repo,
	) {
	}

	/**
	 * Evaluates rollout policy for an allowed base-gate context.
	 *
	 * @param RenderGateContext $context Render gate context.
	 */
	public function evaluate( RenderGateContext $context ): RenderGateDecision {
		if ( ! $context->post instanceof WP_Post ) {
			return RenderGateDecision::deny( RolloutReasonCodes::UNSUPPORTED_REQUEST );
		}

		$config = $this->config_repo->get();

		if ( ! $config->rollout_render_enabled ) {
			$decision = $this->policy->evaluate(
				$this->build_request( $context ),
				$config
			);

			return RenderGateDecision::deny(
				RolloutReasonCodes::ROLLOUT_DISABLED,
				$decision
			);
		}

		$decision = $this->policy->evaluate(
			$this->build_request( $context ),
			$config
		);

		if ( ! $decision->allowed ) {
			return RenderGateDecision::deny( $decision->reason_code, $decision );
		}

		if ( $config->is_shadow_stage() ) {
			return RenderGateDecision::deny( self::REASON_SHADOW_MODE, $decision );
		}

		return RenderGateDecision::allow( $decision );
	}

	/**
	 * Builds a rollout request from render gate context.
	 *
	 * @param RenderGateContext $context Render gate context.
	 */
	private function build_request( RenderGateContext $context ): RolloutPolicyRequest {
		$post = $context->post;
		assert( $post instanceof WP_Post );

		return new RolloutPolicyRequest(
			(int) $post->ID,
			(string) $post->post_type,
			$this->language_code( $context->language ),
			true,
		);
	}

	/**
	 * Resolves the current language code for policy evaluation.
	 *
	 * @param LanguageContext $language Language context.
	 */
	private function language_code( LanguageContext $language ): string {
		$current = $language->current();

		return null !== $current ? strtolower( (string) $current->code ) : '';
	}
}
