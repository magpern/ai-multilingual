<?php
/**
 * Strategy F block metrics aggregator unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockIdentityLogger;
use AIMultilingual\Block\BlockMetricsAggregator;
use AIMultilingual\Block\BlockMigrationEligibility;
use AIMultilingual\Block\BlockMigrationLogger;
use AIMultilingual\Translation\BlockFrontendRenderLogger;
use PHPUnit\Framework\TestCase;

/**
 * Hook-driven metrics aggregation without WordPress bootstrap.
 */
final class BlockMetricsAggregatorTest extends TestCase {

	private BlockMetricsAggregator $metrics;

	protected function setUp(): void {
		parent::setUp();

		$this->metrics = new BlockMetricsAggregator();
	}

	public function test_register_is_idempotent_in_unit_suite(): void {
		$this->metrics->register();
		$this->metrics->register();

		$this->addToAssertionCount( 1 );
	}

	public function test_uuid_counters_accumulate(): void {
		$this->metrics->on_identity_log( BlockIdentityLogger::EVENT_UUID_CREATED, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->on_identity_log( BlockIdentityLogger::EVENT_UUID_REPLACED_INVALID, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->on_identity_log( BlockIdentityLogger::EVENT_UUID_DUPLICATE_DETECTED, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->on_identity_log( BlockIdentityLogger::EVENT_UUID_DUPLICATE_REPAIRED, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->on_identity_log( BlockIdentityLogger::EVENT_UUID_REPAIR_FAILED, array( 'failure_reason' => 'inject_failed' ) );

		$snapshot = $this->metrics->snapshot();

		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_UUID_CREATED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_MALFORMED_UUID_DETECTED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_DUPLICATE_UUID_DETECTED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_UUID_REPAIRED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_UUID_REPAIR_FAILED ] );
	}

	public function test_extraction_counters_accumulate(): void {
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_BLOCK_EXTRACTED, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_FIELD_SKIPPED, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_ADAPTER_MISSING, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_STRUCTURAL_CONTAINER_SEEN, array( 'block_name' => 'core/group' ) );
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_NESTED_SUPPORTED_LEAF, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_NESTED_UNSUPPORTED_LEAF, array( 'block_name' => 'core/separator' ) );
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_DUPLICATE_UNIT_PREVENTED, array( 'segment_key' => 'b:x:content' ) );
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_NESTED_SOURCE_FALLBACK, array( 'block_name' => 'core/list-item' ) );

		$snapshot = $this->metrics->snapshot();

		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_FIELDS_EXTRACTED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_FIELDS_SKIPPED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_EXTRACTION_FAILED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_STRUCTURAL_CONTAINER_SEEN ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_NESTED_SUPPORTED_LEAF ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_NESTED_UNSUPPORTED_LEAF ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_DUPLICATE_UNIT_PREVENTED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_NESTED_SOURCE_FALLBACK ] );
		$this->assertSame( 0, $snapshot->counters[ BlockMetricsAggregator::COUNTER_EXTRACTION_STARTED ] );
	}

	public function test_rendering_counters_and_timing_aggregate(): void {
		$this->metrics->on_frontend_render_log( BlockFrontendRenderLogger::EVENT_GATE_ALLOWED, array( 'post_id' => 1 ) );
		$this->metrics->on_frontend_render_log( BlockFrontendRenderLogger::EVENT_GATE_DENIED, array( 'post_id' => 1 ) );
		$this->metrics->on_frontend_render_log(
			BlockFrontendRenderLogger::EVENT_RENDER_COMPLETE,
			array(
				'post_id'    => 1,
				'elapsed_ms' => 10,
			)
		);
		$this->metrics->on_frontend_render_log(
			BlockFrontendRenderLogger::EVENT_RENDER_FAILED,
			array(
				'post_id'    => 1,
				'elapsed_ms' => 30,
			)
		);

		$snapshot = $this->metrics->snapshot();

		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_RENDER_ATTEMPTED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_RENDER_SKIPPED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_RENDER_COMPLETED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_RENDER_FAILED ] );
		$this->assertSame( 2, $snapshot->render_count );
		$this->assertSame( 40, $snapshot->render_total_elapsed_ms );
		$this->assertSame( 20, $snapshot->render_average_elapsed_ms );
		$this->assertSame( 30, $snapshot->render_max_elapsed_ms );
	}

	public function test_migration_counters_accumulate(): void {
		$this->metrics->on_migration_log( BlockMigrationLogger::EVENT_STARTED, array( 'post_id' => 1 ) );
		$this->metrics->on_migration_log(
			BlockMigrationLogger::EVENT_BATCH_COMPLETE,
			array(
				'processed' => 3,
			)
		);
		$this->metrics->on_migration_log( BlockMigrationLogger::EVENT_POST_COMPLETE, array( 'post_id' => 1 ) );
		$this->metrics->on_migration_log(
			BlockMigrationLogger::EVENT_SKIPPED,
			array(
				'post_id'     => 2,
				'skip_reason' => BlockMigrationEligibility::REASON_ALREADY_COMPLIANT,
			)
		);
		$this->metrics->on_migration_log(
			BlockMigrationLogger::EVENT_SKIPPED,
			array(
				'post_id'     => 3,
				'skip_reason' => BlockMigrationEligibility::REASON_NON_BLOCK_CONTENT,
			)
		);
		$this->metrics->on_migration_log( BlockMigrationLogger::EVENT_POST_FAILED, array( 'post_id' => 4 ) );
		$this->metrics->on_migration_log( BlockMigrationLogger::EVENT_CONCURRENT_MODIFICATION, array( 'post_id' => 5 ) );

		$snapshot = $this->metrics->snapshot();

		$this->assertSame( 4, $snapshot->counters[ BlockMetricsAggregator::COUNTER_POSTS_SCANNED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_POSTS_MIGRATED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_POSTS_ALREADY_COMPLIANT ] );
		$this->assertSame( 2, $snapshot->counters[ BlockMetricsAggregator::COUNTER_POSTS_SKIPPED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_MIGRATIONS_FAILED ] );
		$this->assertSame( 1, $snapshot->counters[ BlockMetricsAggregator::COUNTER_CONCURRENT_MODIFICATIONS ] );
	}

	public function test_settings_flag_change_counter(): void {
		$this->metrics->on_flag_changed(
			array(
				'flag'      => 'block_extraction_enabled',
				'old'       => false,
				'new'       => true,
				'user_id'   => 1,
				'timestamp' => 1,
				'source'    => 'admin_settings',
			)
		);

		$this->assertSame( 1, $this->metrics->snapshot()->counters[ BlockMetricsAggregator::COUNTER_FEATURE_FLAGS_CHANGED ] );
	}

	public function test_flag_combinations_rejected_counter(): void {
		$this->metrics->on_settings_operational_log(
			\AIMultilingual\SettingsOperationalLogger::EVENT_FLAG_COMBO_REJECTED,
			array(
				'event'         => 'flag_combo_rejected',
				'dropped_flags' => array( 'block_uuid_injection_enabled' ),
			)
		);

		$this->assertSame(
			1,
			$this->metrics->snapshot()->counters[ BlockMetricsAggregator::COUNTER_FLAG_COMBINATIONS_REJECTED ]
		);
	}

	public function test_malformed_rejected_event_is_ignored(): void {
		$this->metrics->on_settings_operational_log( 123, array() );

		$snapshot = $this->metrics->snapshot();

		$this->assertSame( 0, $snapshot->counters[ BlockMetricsAggregator::COUNTER_FLAG_COMBINATIONS_REJECTED ] );
		$this->assertSame( 1, $snapshot->ignored_event_count );
	}

	public function test_malformed_payloads_are_ignored_safely(): void {
		$this->metrics->on_identity_log( 123, array() );
		$this->metrics->on_extraction_log( BlockExtractionLogger::EVENT_BLOCK_EXTRACTED, 'bad' );
		$this->metrics->on_frontend_render_log(
			BlockFrontendRenderLogger::EVENT_RENDER_COMPLETE,
			array(
				'elapsed_ms' => -5,
			)
		);
		$this->metrics->on_flag_changed( 'bad' );

		$snapshot = $this->metrics->snapshot();

		$this->assertSame( 4, $snapshot->ignored_event_count );
		$this->assertTrue( $snapshot->incomplete );
		$this->assertSame( 0, $snapshot->render_count );
	}

	public function test_snapshot_contains_no_raw_payload_fields(): void {
		$this->metrics->on_identity_log(
			BlockIdentityLogger::EVENT_UUID_CREATED,
			array(
				'block_name' => 'core/paragraph',
				'uuid'       => '550e8400-e29b-41d4-a716-446655440000',
			)
		);

		$encoded = json_encode( $this->metrics->snapshot()->to_array() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( '550e8400-e29b-41d4-a716-446655440000', $encoded );
		$this->assertStringNotContainsString( 'core/paragraph', $encoded );
	}

	public function test_reset_isolates_counters_between_tests(): void {
		$this->metrics->on_identity_log( BlockIdentityLogger::EVENT_UUID_CREATED, array( 'block_name' => 'core/paragraph' ) );
		$this->metrics->reset();

		$snapshot = $this->metrics->snapshot();

		$this->assertSame( 0, $snapshot->counters[ BlockMetricsAggregator::COUNTER_UUID_CREATED ] );
		$this->assertSame( 0, $snapshot->ignored_event_count );
		$this->assertFalse( $snapshot->incomplete );
	}
}
