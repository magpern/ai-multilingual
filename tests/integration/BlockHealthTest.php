<?php
/**
 * Strategy F block health service integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\BlockHealthScanOptions;
use AIMultilingual\Block\BlockHealthService;
use AIMultilingual\Block\BlockHealthSnapshot;
use AIMultilingual\Block\BlockIdentityAnalyzer;
use AIMultilingual\Block\BlockMigrationEligibility;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * BlockHealthService read-only scans.
 */
final class BlockHealthTest extends AimlTestCase {

	private BlockHealthService $health;

	protected function setUp(): void {
		parent::setUp();

		$this->health = new BlockHealthService(
			$this->store,
			$this->extractor,
			new BlockIdentityAnalyzer( new BlockRegistry() )
		);
	}

	public function test_empty_dataset_returns_zero_snapshot(): void {
		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				post_type: 'page',
				sample_size: 10
			)
		);

		$this->assertSame( 0, $snapshot->scanned_post_count );
		$this->assertSame( 0, $snapshot->compliant_post_count );
		$this->assertSame( 0, $snapshot->non_compliant_post_count );
		$this->assertNotSame( '', $snapshot->generated_at );
		$this->assertGreaterThanOrEqual( 0, $snapshot->elapsed_ms );
	}

	public function test_default_sample_is_bounded_and_sampled(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->create_compliant_page( 'Sample ' . $i );
		}

		$snapshot = $this->health->scan( new BlockHealthScanOptions( sample_size: 3, post_type: 'page' ) );

		$this->assertSame( BlockHealthSnapshot::SCAN_MODE_SAMPLE, $snapshot->scan_mode );
		$this->assertTrue( $snapshot->sampled );
		$this->assertLessThanOrEqual( 3, $snapshot->scanned_post_count );
		$this->assertSame( 3, $snapshot->requested_sample_size );
	}

	public function test_full_scan_requires_explicit_opt_in(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->create_compliant_page( 'Full ' . $i );
		}

		$sample = $this->health->scan(
			new BlockHealthScanOptions(
				sample_size: 2,
				post_type: 'page'
			)
		);
		$full   = $this->health->scan(
			new BlockHealthScanOptions(
				sample_size: 2,
				full_scan: true,
				post_type: 'page'
			)
		);

		$this->assertLessThan( $full->scanned_post_count, $sample->scanned_post_count );
		$this->assertSame( BlockHealthSnapshot::SCAN_MODE_FULL, $full->scan_mode );
	}

	public function test_deterministic_post_ordering(): void {
		$first  = $this->create_compliant_page( 'First' );
		$second = $this->create_compliant_page( 'Second' );

		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				full_scan: true,
				post_type: 'page'
			)
		);

		$ids = array_map(
			static fn( $result ) => $result->post_id,
			$snapshot->post_results
		);

		$this->assertContains( (int) $first->ID, $ids );
		$this->assertContains( (int) $second->ID, $ids );
		$sorted = $ids;
		sort( $sorted );
		$this->assertSame( $sorted, $ids );
	}

	public function test_compliant_post_classification(): void {
		$this->create_compliant_page( 'Compliant' );

		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				full_scan: true,
				post_type: 'page'
			)
		);

		$this->assertGreaterThanOrEqual( 1, $snapshot->compliant_post_count );
		$this->assertSame( 0, $snapshot->non_compliant_post_count );
	}

	public function test_missing_malformed_and_duplicate_uuid_detection(): void {
		$this->create_page(
			'Missing UUID',
			'<!-- wp:paragraph --><p>No uuid</p><!-- /wp:paragraph -->'
		);
		$this->create_page(
			'Malformed UUID',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"bad"} --><p>Bad uuid</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME
			)
		);
		$this->create_page(
			'Duplicate UUID',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"550e8400-e29b-41d4-a716-446655440000"} --><p>One</p><!-- /wp:paragraph -->'
				. '<!-- wp:paragraph {"%1$s":"550e8400-e29b-41d4-a716-446655440000"} --><p>Two</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME
			)
		);

		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				full_scan: true,
				post_type: 'page'
			)
		);

		$this->assertGreaterThanOrEqual( 1, $snapshot->posts_with_missing_uuids );
		$this->assertGreaterThanOrEqual( 1, $snapshot->posts_with_malformed_uuids );
		$this->assertGreaterThanOrEqual( 1, $snapshot->posts_with_duplicate_uuids );
		$this->assertGreaterThanOrEqual( 3, $snapshot->non_compliant_post_count );
	}

	public function test_eligibility_skip_reasons_are_aggregated(): void {
		$this->create_page( 'Classic', '<p>Classic only.</p>' );
		$trashed = $this->create_compliant_page( 'Trashed' );
		wp_trash_post( (int) $trashed->ID );

		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				full_scan: true,
				post_type: 'page'
			)
		);

		$this->assertArrayHasKey( BlockMigrationEligibility::REASON_NON_BLOCK_CONTENT, $snapshot->skip_reason_counts );

		$trashed_scan = $this->health->scan(
			new BlockHealthScanOptions(
				source_id: (int) $trashed->ID
			)
		);

		$this->assertArrayHasKey( BlockMigrationEligibility::REASON_TRASHED, $trashed_scan->skip_reason_counts );
	}

	public function test_optional_source_id_scope(): void {
		$post = $this->create_compliant_page( 'Scoped' );

		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				source_id: (int) $post->ID
			)
		);

		$this->assertSame( 1, $snapshot->scanned_post_count );
		$this->assertSame( 1, $snapshot->eligible_post_count );
		$this->assertSame( 1, $snapshot->compliant_post_count );
	}

	public function test_store_counts_are_included(): void {
		$language = $this->add_language();
		$post     = $this->create_compliant_page( 'Store counts' );
		$key      = SegmentKey::build( '550e8400-e29b-41d4-a716-446655440000', Contract::FIELD_CONTENT );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Store counts</p>',
				'translated_text' => 'Store counts SV',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				source_id: (int) $post->ID
			)
		);

		$this->assertSame( 1, $snapshot->total_block_segments );
		$this->assertSame( 1, $snapshot->translated_block_segments );
		$this->assertSame( 1, $snapshot->renderable_block_segments );
	}

	public function test_snapshot_excludes_post_and_translation_bodies(): void {
		$this->create_compliant_page( 'Secret body text' );

		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				full_scan: true,
				post_type: 'page'
			)
		);

		$encoded = wp_json_encode( $snapshot->to_array() );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'Secret body text', $encoded );
	}

	public function test_invalid_sample_size_is_recorded(): void {
		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				sample_size: 5000,
				post_type: 'page'
			)
		);

		$this->assertContains( BlockHealthService::ERROR_INVALID_SAMPLE_SIZE, $snapshot->errors );
		$this->assertSame( BlockHealthScanOptions::MAX_SAMPLE_SIZE, $snapshot->requested_sample_size );
	}

	public function test_service_performs_no_writes(): void {
		global $wpdb;

		$this->create_compliant_page( 'No writes' );
		$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . \AIMultilingual\Database\Schema::translations() ); // phpcs:ignore WordPress.DB

		$this->health->scan(
			new BlockHealthScanOptions(
				full_scan: true,
				post_type: 'page'
			)
		);

		$after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . \AIMultilingual\Database\Schema::translations() ); // phpcs:ignore WordPress.DB

		$this->assertSame( $before, $after );
	}

	public function test_missing_post_source_marks_skip_reason(): void {
		$snapshot = $this->health->scan(
			new BlockHealthScanOptions(
				source_id: 999999999
			)
		);

		$this->assertSame( 1, $snapshot->skipped_post_count );
		$this->assertArrayHasKey( BlockMigrationEligibility::REASON_MISSING_POST, $snapshot->skip_reason_counts );
	}

	/**
	 * @param string $label Paragraph label.
	 */
	private function create_compliant_page( string $label ): \WP_Post {
		static $index = 0;
		++$index;

		$uuids = array(
			'550e8400-e29b-41d4-a716-446655440000',
			'6ba7b810-9dad-41d1-80b4-00c04fd430c8',
			'7c9e6679-7425-40de-944b-e07fc1f90ae7',
			'9b2c3d4e-5f60-4781-9a2b-3c4d5e6f7081',
			'a1b2c3d4-e5f6-4789-8abc-0123456789ab',
		);
		$uuid  = $uuids[ ( $index - 1 ) % count( $uuids ) ];

		return $this->create_page(
			$label,
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid,
				$label
			)
		);
	}
}
