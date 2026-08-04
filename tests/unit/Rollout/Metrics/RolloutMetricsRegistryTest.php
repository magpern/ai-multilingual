<?php
/**
 * Rollout metrics registry unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Rollout\Metrics;

use AIMultilingual\Rollout\Metrics\RolloutMetricsDimensionValidator;
use AIMultilingual\Rollout\Metrics\RolloutMetricsRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Rollout\Metrics\RolloutMetricsRegistry
 * @covers \AIMultilingual\Rollout\Metrics\RolloutMetricsDimensionValidator
 */
final class RolloutMetricsRegistryTest extends TestCase {

	public function test_append_only_metric_keys(): void {
		$this->assertTrue( RolloutMetricsRegistry::is_valid_key( RolloutMetricsRegistry::RENDER_DENIED ) );
		$this->assertFalse( RolloutMetricsRegistry::is_valid_key( 'custom_metric' ) );
		$this->assertSame( 1, RolloutMetricsRegistry::VERSION );
	}

	public function test_dimension_hash_is_deterministic(): void {
		$a = RolloutMetricsRegistry::dimension_hash(
			RolloutMetricsRegistry::RENDER_DENIED,
			array( 'stage' => 2, 'reason_code' => 'post_not_allowlisted' )
		);
		$b = RolloutMetricsRegistry::dimension_hash(
			RolloutMetricsRegistry::RENDER_DENIED,
			array( 'reason_code' => 'post_not_allowlisted', 'stage' => 2 )
		);

		$this->assertSame( $a, $b );
	}

	public function test_prohibited_dimensions_rejected(): void {
		$validator = new RolloutMetricsDimensionValidator();
		$result    = $validator->sanitize(
			RolloutMetricsRegistry::RENDER_DENIED,
			array( 'post_id' => 123 )
		);

		$this->assertNull( $result );
	}

	public function test_allowed_dimensions_accepted(): void {
		$validator = new RolloutMetricsDimensionValidator();
		$result    = $validator->sanitize(
			RolloutMetricsRegistry::RENDER_DENIED,
			array(
				'stage'       => 2,
				'reason_code' => 'post_not_allowlisted',
				'post_type'   => 'page',
			)
		);

		$this->assertSame(
			array(
				'stage'       => '2',
				'reason_code' => 'post_not_allowlisted',
				'post_type'   => 'page',
			),
			$result
		);
	}
}
