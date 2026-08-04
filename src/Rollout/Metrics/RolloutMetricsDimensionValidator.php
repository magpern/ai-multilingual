<?php
/**
 * Prohibited and bounded dimension validation for rollout metrics.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Metrics;

/**
 * Enforces registry-only keys and cardinality rules.
 */
final class RolloutMetricsDimensionValidator {

	/**
	 * Prohibited dimension keys in long-term storage.
	 *
	 * @var list<string>
	 */
	private const PROHIBITED = array(
		'post_id',
		'segment_key',
		'uuid',
		'source_text',
		'translated_text',
		'prompt',
		'email',
		'api_key',
		'error_body',
	);

	/**
	 * Validates dimensions for a metric key.
	 *
	 * @param array<string, string|int> $dimensions Raw dimensions.
	 * @return array<string, string>|null Sanitized dimensions or null when invalid.
	 */
	public function sanitize( string $metric_key, array $dimensions ): ?array {
		if ( ! RolloutMetricsRegistry::is_valid_key( $metric_key ) ) {
			return null;
		}

		$allowed = RolloutMetricsRegistry::allowed_dimensions()[ $metric_key ] ?? array();
		$clean   = array();

		foreach ( $dimensions as $key => $value ) {
			$key = (string) $key;
			if ( in_array( $key, self::PROHIBITED, true ) ) {
				return null;
			}
			if ( ! in_array( $key, $allowed, true ) ) {
				return null;
			}
			$clean[ $key ] = substr( (string) $value, 0, 64 );
		}

		return $clean;
	}
}
