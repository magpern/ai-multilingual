<?php
/**
 * Frozen F12 rollout audit event names.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Additive-only audit event catalog.
 */
final class RolloutAuditEvents {

	public const CONFIGURATION_CHANGED = 'rollout_configuration_changed';
	public const STAGE_PROMOTED        = 'rollout_stage_promoted';
	public const STAGE_ROLLED_BACK     = 'rollout_stage_rolled_back';
	public const RENDER_ENABLED        = 'rollout_render_enabled';
	public const RENDER_DISABLED       = 'rollout_render_disabled';
	public const CACHE_ENABLED         = 'rollout_cache_enabled';
	public const CACHE_DISABLED        = 'rollout_cache_disabled';
	public const EMERGENCY_STOP        = 'rollout_emergency_stop';
	public const POLICY_EVALUATED      = 'rollout_policy_evaluated';
	public const METRICS_RESET         = 'rollout_metrics_reset';
	public const CACHE_PURGED          = 'rollout_cache_purged';

	/**
	 * Returns all registered audit event names.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::CONFIGURATION_CHANGED,
			self::STAGE_PROMOTED,
			self::STAGE_ROLLED_BACK,
			self::RENDER_ENABLED,
			self::RENDER_DISABLED,
			self::CACHE_ENABLED,
			self::CACHE_DISABLED,
			self::EMERGENCY_STOP,
			self::POLICY_EVALUATED,
			self::METRICS_RESET,
			self::CACHE_PURGED,
		);
	}
}
