<?php
/**
 * Rollout emergency stop service.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Ordered emergency rollback without deleting UUIDs, Store, or TM rows.
 */
final class RolloutEmergencyService {

	/**
	 * @param RolloutConfigurationRepository $repository Config store.
	 * @param RolloutAuditLogger             $audit      Audit logger.
	 */
	public function __construct(
		private RolloutConfigurationRepository $repository,
		private RolloutAuditLogger $audit,
	) {
	}

	/**
	 * Disables rollout rendering and cache immediately.
	 */
	public function stop( int $user_id, string $source = 'cli', string $reason = 'emergency_stop' ): RolloutConfigurationValidationResult {
		if ( ! RolloutAccess::user_can( $user_id, RolloutCapabilities::EMERGENCY_ROLLBACK ) ) {
			return RolloutConfigurationValidationResult::fail( array( 'unauthorized' ) );
		}

		$result = $this->repository->apply_change(
			array(
				'rollout_render_enabled' => false,
				'render_cache_enabled'   => false,
			),
			$user_id
		);

		if ( $result->valid ) {
			$this->audit->log(
				RolloutAuditEvents::EMERGENCY_STOP,
				array(
					'policy_version' => $result->config?->policy_version ?? 0,
					'user_id'        => $user_id,
					'source'         => $source,
					'reason'         => $reason,
				)
			);
		}

		return $result;
	}
}
