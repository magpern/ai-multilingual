<?php
/**
 * Strategy F block metrics snapshot unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\BlockMetricsAggregator;
use AIMultilingual\Block\BlockMetricsSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * Metrics snapshot serialization tests.
 */
final class BlockMetricsSnapshotTest extends TestCase {

	public function test_to_array_exposes_stable_keys(): void {
		$snapshot = new BlockMetricsSnapshot(
			'2026-07-27T12:00:00+00:00',
			array(
				BlockMetricsAggregator::COUNTER_UUID_CREATED => 2,
			),
			1,
			12,
			12,
			12,
			0,
			false
		);

		$array = $snapshot->to_array();

		$this->assertSame( '2026-07-27T12:00:00+00:00', $array['generated_at'] );
		$this->assertSame( 2, $array['counters'][ BlockMetricsAggregator::COUNTER_UUID_CREATED ] );
		$this->assertSame( 1, $array['render_count'] );
		$this->assertSame( 12, $array['render_total_elapsed_ms'] );
		$this->assertSame( 12, $array['render_average_elapsed_ms'] );
		$this->assertSame( 12, $array['render_max_elapsed_ms'] );
		$this->assertSame( 0, $array['ignored_event_count'] );
		$this->assertFalse( $array['incomplete'] );
	}
}
