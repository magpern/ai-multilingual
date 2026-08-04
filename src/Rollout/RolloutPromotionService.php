<?php
/**
 * Rollout stage promotion service.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Explicit operator stage promotion — never automatic.
 */
final class RolloutPromotionService {

	/**
	 * Builds the promotion service.
	 *
	 * @param RolloutConfigurationRepository $repository Config store.
	 * @param RolloutAuditLogger             $audit      Audit logger.
	 */
	public function __construct(
		private RolloutConfigurationRepository $repository,
		private RolloutAuditLogger $audit,
	) {
	}

	/**
	 * Promotes to a target stage when authorized.
	 *
	 * @param int    $target_stage New stage 0–5.
	 * @param int    $user_id      Acting user.
	 * @param string $source    Origin label.
	 */
	public function promote( int $target_stage, int $user_id, string $source = 'cli' ): RolloutConfigurationValidationResult {
		if ( ! RolloutAccess::user_can( $user_id, RolloutCapabilities::PROMOTE_ROLLOUT ) ) {
			return RolloutConfigurationValidationResult::fail( array( 'unauthorized' ) );
		}

		if ( $target_stage < 0 || $target_stage > 5 ) {
			return RolloutConfigurationValidationResult::fail( array( 'invalid_stage' ) );
		}

		$before = $this->repository->get();

		$result = $this->repository->apply_change(
			array( 'rollout_stage' => $target_stage ),
			$user_id
		);

		if ( $result->valid && null !== $result->config ) {
			$this->audit->log(
				RolloutAuditEvents::STAGE_PROMOTED,
				array(
					'old_stage'      => $before->rollout_stage,
					'new_stage'      => $result->config->rollout_stage,
					'policy_version' => $result->config->policy_version,
					'user_id'        => $user_id,
					'source'         => $source,
					'reason'         => 'stage_promoted',
				)
			);
		}

		return $result;
	}

	/**
	 * Rolls back to a snapshot policy version.
	 *
	 * @param int    $policy_version Snapshot policy version.
	 * @param int    $user_id        Acting user.
	 * @param string $source         Origin label.
	 */
	public function rollback( int $policy_version, int $user_id, string $source = 'cli' ): RolloutConfigurationValidationResult {
		if ( ! RolloutAccess::user_can( $user_id, RolloutCapabilities::EMERGENCY_ROLLBACK ) ) {
			return RolloutConfigurationValidationResult::fail( array( 'unauthorized' ) );
		}

		$before = $this->repository->get();
		$result = $this->repository->restore( $policy_version, $user_id );

		if ( $result->valid && null !== $result->config ) {
			$this->audit->log(
				RolloutAuditEvents::STAGE_ROLLED_BACK,
				array(
					'old_stage'      => $before->rollout_stage,
					'new_stage'      => $result->config->rollout_stage,
					'policy_version' => $result->config->policy_version,
					'user_id'        => $user_id,
					'source'         => $source,
					'reason'         => 'snapshot_restore',
				)
			);
		}

		return $result;
	}
}
