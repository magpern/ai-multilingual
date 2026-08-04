<?php
/**
 * Rollout diagnostics readers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Metrics;

use AIMultilingual\Rollout\RolloutConfigurationRepository;

/**
 * Read-only diagnostics summaries for operators.
 */
final class RolloutDiagnosticsService {

	/**
	 * @param RolloutConfigurationRepository $config Rollout config.
	 * @param RolloutHotMetricsStore         $hot    Hot metrics.
	 */
	public function __construct(
		private RolloutConfigurationRepository $config,
		private RolloutHotMetricsStore $hot,
	) {
	}

	/**
	 * Returns a sanitized rollout status summary.
	 *
	 * @return array<string, mixed>
	 */
	public function status_summary(): array {
		$configuration = $this->config->export();

		return array(
			'policy_version'        => $configuration['policy_version'] ?? 0,
			'rollout_stage'         => $configuration['rollout_stage'] ?? 0,
			'rollout_render_enabled' => ! empty( $configuration['rollout_render_enabled'] ),
			'render_cache_enabled'  => ! empty( $configuration['render_cache_enabled'] ),
			'block_diagnostics_enabled' => ! empty( $configuration['block_diagnostics_enabled'] ),
			'hot_counters'          => $this->hot->counters(),
			'telemetry_incomplete'  => $this->hot->is_incomplete(),
			'metrics_registry_version' => RolloutMetricsRegistry::VERSION,
		);
	}
}
