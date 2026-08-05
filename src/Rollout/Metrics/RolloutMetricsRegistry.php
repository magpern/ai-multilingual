<?php
/**
 * Frozen F12 rollout metrics registry.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Metrics;

/**
 * Append-only metric keys and bounded dimensions.
 */
final class RolloutMetricsRegistry {

	public const VERSION = 1;

	public const RENDER_ATTEMPT    = 'rollout_render_attempt';
	public const RENDER_ALLOWED    = 'rollout_render_allowed';
	public const RENDER_DENIED     = 'rollout_render_denied';
	public const RENDER_COMPLETED  = 'rollout_render_completed';
	public const RENDER_FAILED     = 'rollout_render_failed';
	public const RENDER_FALLBACK   = 'rollout_render_fallback';
	public const POLICY_EVALUATION = 'rollout_policy_evaluation';
	public const METRICS_FLUSH     = 'rollout_metrics_flush';
	public const METRICS_ROLLUP    = 'rollout_metrics_rollup';

	/**
	 * Returns all registered metric keys.
	 *
	 * @return list<string>
	 */
	public static function metric_keys(): array {
		return array(
			self::RENDER_ATTEMPT,
			self::RENDER_ALLOWED,
			self::RENDER_DENIED,
			self::RENDER_COMPLETED,
			self::RENDER_FAILED,
			self::RENDER_FALLBACK,
			self::POLICY_EVALUATION,
			self::METRICS_FLUSH,
			self::METRICS_ROLLUP,
		);
	}

	/**
	 * Allowed dimension keys per metric (bounded).
	 *
	 * @return array<string, list<string>>
	 */
	public static function allowed_dimensions(): array {
		$policy = array( 'stage', 'reason_code', 'post_type', 'language_code', 'result_class' );
		$render = array( 'stage', 'reason_code', 'post_type', 'language_code', 'cache_outcome' );

		return array(
			self::RENDER_ATTEMPT    => $render,
			self::RENDER_ALLOWED    => $render,
			self::RENDER_DENIED     => $render,
			self::RENDER_COMPLETED  => $render,
			self::RENDER_FAILED     => $render,
			self::RENDER_FALLBACK   => $render,
			self::POLICY_EVALUATION => $policy,
			self::METRICS_FLUSH     => array( 'result_class' ),
			self::METRICS_ROLLUP    => array( 'result_class' ),
		);
	}

	/**
	 * Whether a metric key is registered.
	 *
	 * @param string $key Metric key.
	 */
	public static function is_valid_key( string $key ): bool {
		return in_array( $key, self::metric_keys(), true );
	}

	/**
	 * Builds a canonical dimension hash from bounded dimensions.
	 *
	 * @param string                    $metric_key Metric key.
	 * @param array<string, string|int> $dimensions Bounded dimensions only.
	 */
	public static function dimension_hash( string $metric_key, array $dimensions ): string {
		$allowed  = self::allowed_dimensions()[ $metric_key ] ?? array();
		$filtered = array();

		foreach ( $allowed as $dim ) {
			if ( isset( $dimensions[ $dim ] ) ) {
				$filtered[ $dim ] = (string) $dimensions[ $dim ];
			}
		}

		ksort( $filtered );

		return hash( 'sha1', $metric_key . '|' . json_encode( $filtered, JSON_THROW_ON_ERROR ) );
	}
}
