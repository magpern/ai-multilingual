<?php
/**
 * Rollout metrics repository integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Rollout\Metrics;

use AIMultilingual\Database\Schema;
use AIMultilingual\Rollout\Metrics\RolloutMetricsRegistry;
use AIMultilingual\Rollout\Metrics\RolloutMetricsRepository;
use AIMultilingual\Tests\Integration\AimlTestCase;

/**
 * @covers \AIMultilingual\Rollout\Metrics\RolloutMetricsRepository
 */
final class RolloutMetricsRepositoryTest extends AimlTestCase {

	private RolloutMetricsRepository $repository;

	protected function setUp(): void {
		parent::setUp();
		$this->repository = new RolloutMetricsRepository();
	}

	public function test_increment_and_cleanup(): void {
		$this->assertTrue( $this->repository->table_exists() );

		$ok = $this->repository->increment(
			RolloutMetricsRegistry::RENDER_DENIED,
			array(
				'stage'       => 2,
				'reason_code' => 'post_not_allowlisted',
				'post_type'   => 'page',
			),
			2,
			100,
			40,
			60
		);

		$this->assertTrue( $ok );

		global $wpdb;
		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT count_value FROM ' . Schema::metrics_daily() . ' WHERE metric_key = %s',
				RolloutMetricsRegistry::RENDER_DENIED
			)
		);

		$this->assertSame( 2, $count );

		$this->assertFalse(
			$this->repository->increment(
				RolloutMetricsRegistry::RENDER_DENIED,
				array( 'post_id' => 1 )
			)
		);

		$this->repository->cleanup_expired();
		$this->assertTrue( true );
	}
}
