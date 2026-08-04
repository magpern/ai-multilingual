<?php
/**
 * Persistent daily rollout metrics repository.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Metrics;

use AIMultilingual\Database\Schema;

/**
 * Concurrency-safe aggregate increments for aiml_metrics_daily.
 */
final class RolloutMetricsRepository {

	/**
	 * Retention window in days.
	 */
	public const RETENTION_DAYS = 90;

	/**
	 * @param RolloutMetricsDimensionValidator|null $validator Dimension validator.
	 */
	public function __construct(
		private ?RolloutMetricsDimensionValidator $validator = null,
	) {
		$this->validator = $validator ?? new RolloutMetricsDimensionValidator();
	}

	/**
	 * Whether the metrics table exists.
	 */
	public function table_exists(): bool {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return false;
		}

		$table = Schema::metrics_daily();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Increments an aggregate counter safely.
	 *
	 * @param array<string, string|int> $dimensions Bounded dimensions.
	 * @param int                         $count      Count increment.
	 * @param int                         $sum        Sum increment (latency ms).
	 * @param int|null                    $min        Optional min sample.
	 * @param int|null                    $max        Optional max sample.
	 */
	public function increment(
		string $metric_key,
		array $dimensions,
		int $count = 1,
		int $sum = 0,
		?int $min = null,
		?int $max = null,
		bool $incomplete = false,
	): bool {
		if ( ! $this->table_exists() ) {
			return false;
		}

		$clean = $this->validator->sanitize( $metric_key, $dimensions );
		if ( null === $clean ) {
			return false;
		}

		global $wpdb;

		$day            = gmdate( 'Y-m-d' );
		$dimension_hash = RolloutMetricsRegistry::dimension_hash( $metric_key, $clean );
		$now            = gmdate( 'Y-m-d H:i:s' );
		$table          = Schema::metrics_daily();

		$stage         = (int) ( $clean['stage'] ?? 0 );
		$reason_code   = (string) ( $clean['reason_code'] ?? '' );
		$post_type     = (string) ( $clean['post_type'] ?? '' );
		$language_code = (string) ( $clean['language_code'] ?? '' );
		$result_class  = (string) ( $clean['result_class'] ?? '' );
		$cache_outcome = (string) ( $clean['cache_outcome'] ?? '' );

		$min_val = null !== $min ? $min : 0;
		$max_val = null !== $max ? $max : 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table}
				(day, metric_key, dimension_hash, stage, reason_code, post_type, language_code, result_class, cache_outcome,
				 count_value, sum_value, min_value, max_value, incomplete, registry_version, updated_at)
				VALUES (%s, %s, %s, %d, %s, %s, %s, %s, %s, %d, %d, %d, %d, %d, %d, %s)
				ON DUPLICATE KEY UPDATE
					count_value = count_value + VALUES(count_value),
					sum_value = sum_value + VALUES(sum_value),
					min_value = IF(min_value = 0, VALUES(min_value), LEAST(min_value, VALUES(min_value))),
					max_value = GREATEST(max_value, VALUES(max_value)),
					incomplete = GREATEST(incomplete, VALUES(incomplete)),
					updated_at = VALUES(updated_at)",
				$day,
				$metric_key,
				$dimension_hash,
				$stage,
				$reason_code,
				$post_type,
				$language_code,
				$result_class,
				$cache_outcome,
				max( 0, $count ),
				$sum,
				$min_val,
				$max_val,
				$incomplete ? 1 : 0,
				RolloutMetricsRegistry::VERSION,
				$now
			)
		);

		return false !== $result;
	}

	/**
	 * Deletes aggregates older than the retention window.
	 */
	public function cleanup_expired(): int {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		$cutoff = gmdate( 'Y-m-d', time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) );
		$table  = Schema::metrics_daily();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE day < %s",
				$cutoff
			)
		);
	}
}
