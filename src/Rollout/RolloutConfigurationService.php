<?php
/**
 * Audited rollout configuration mutation service.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Shared service for config changes — not direct option writes.
 */
final class RolloutConfigurationService {

	/**
	 * Builds the configuration service.
	 *
	 * @param RolloutConfigurationRepository $repository Configuration store.
	 * @param RolloutAuditLogger             $audit        Audit logger.
	 */
	public function __construct(
		private RolloutConfigurationRepository $repository,
		private RolloutAuditLogger $audit,
	) {
	}

	/**
	 * Applies a validated configuration change with audit.
	 *
	 * @param array<string, mixed> $proposed Proposed fields.
	 * @param int                  $user_id  Acting user.
	 * @param string               $source   Change origin.
	 * @param list<string>|null    $language_codes Known language codes.
	 */
	public function apply(
		array $proposed,
		int $user_id,
		string $source = 'operator',
		?array $language_codes = null,
	): RolloutConfigurationValidationResult {
		if ( ! RolloutAccess::user_can( $user_id, RolloutCapabilities::MANAGE_ROLLOUT ) ) {
			return RolloutConfigurationValidationResult::fail( array( 'unauthorized' ) );
		}

		$before = $this->repository->get( $language_codes );
		$result = $this->repository->apply_change( $proposed, $user_id, $language_codes );

		if ( $result->valid && null !== $result->config ) {
			$this->audit->log(
				RolloutAuditEvents::CONFIGURATION_CHANGED,
				array(
					'old_stage'      => $before->rollout_stage,
					'new_stage'      => $result->config->rollout_stage,
					'policy_version' => $result->config->policy_version,
					'user_id'        => $user_id,
					'source'         => $source,
					'reason'         => 'configuration_changed',
				)
			);

			$this->audit_flag_transitions( $before, $result->config, $user_id, $source );
		}

		return $result;
	}

	/**
	 * Audits rollout render and cache flag transitions.
	 *
	 * @param RolloutConfiguration $before Previous config.
	 * @param RolloutConfiguration $after  New config.
	 * @param int                  $user_id Acting user.
	 * @param string               $source Change origin.
	 */
	private function audit_flag_transitions(
		RolloutConfiguration $before,
		RolloutConfiguration $after,
		int $user_id,
		string $source,
	): void {
		if ( $before->rollout_render_enabled !== $after->rollout_render_enabled ) {
			$this->audit->log(
				$after->rollout_render_enabled
					? RolloutAuditEvents::RENDER_ENABLED
					: RolloutAuditEvents::RENDER_DISABLED,
				array(
					'policy_version' => $after->policy_version,
					'user_id'        => $user_id,
					'source'         => $source,
					'reason'         => 'rollout_render_toggle',
				)
			);
		}

		if ( $before->render_cache_enabled !== $after->render_cache_enabled ) {
			$this->audit->log(
				$after->render_cache_enabled
					? RolloutAuditEvents::CACHE_ENABLED
					: RolloutAuditEvents::CACHE_DISABLED,
				array(
					'policy_version' => $after->policy_version,
					'user_id'        => $user_id,
					'source'         => $source,
					'reason'         => 'render_cache_toggle',
				)
			);
		}
	}
}
