<?php
/**
 * Strategy F block identity migration integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\BlockIdentityMigration;
use AIMultilingual\Block\BlockMigrationEligibility;
use AIMultilingual\Block\BlockMigrationLogger;
use AIMultilingual\Block\BlockMigrationOptions;
use AIMultilingual\Block\BlockMigrationResult;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SavePipeline;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\UuidInjector;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

/**
 * Block identity migration and backfill tooling.
 */
final class BlockIdentityMigrationTest extends AimlTestCase {

	private const UUID_A = '550e8400-e29b-41d4-a716-446655440000';
	private const UUID_B = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';

	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( 1 );
		BlockIdentityMigration::reset_for_tests();
	}

	protected function tearDown(): void {
		BlockIdentityMigration::reset_for_tests();
		remove_all_filters( 'pre_wp_update_post' );
		remove_all_filters( 'aiml_block_migration_persist_post' );
		remove_all_actions( 'aiml_block_migration_before_persist' );

		parent::tearDown();
	}

	public function test_missing_uuid_migration(): void {
		$post   = $this->create_page( 'Missing UUID', $this->paragraph( 'Hello migration' ) );
		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_COMPLETE, $result->status );
		$this->assertTrue( $result->content_changed );
		$this->assertGreaterThan( 0, $result->created_count );
		$this->assertStringContainsString( '"aimlBlockId"', (string) get_post( $post->ID )->post_content );
	}

	public function test_malformed_uuid_migration(): void {
		$content = '<!-- wp:paragraph {"aimlBlockId":"not-valid","className":"intro"} --><p>Fix me</p><!-- /wp:paragraph -->';
		$post    = $this->create_page( 'Malformed', $content );
		$result  = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_COMPLETE, $result->status );
		$this->assertGreaterThan( 0, $result->malformed_replaced_count );
		$this->assertMatchesRegularExpression(
			'/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/',
			(string) get_post( $post->ID )->post_content
		);
	}

	public function test_duplicate_uuid_migration_first_wins(): void {
		$content = $this->paragraph( 'One', self::UUID_A )
			. $this->paragraph( 'Two', self::UUID_A );
		$post    = $this->create_page( 'Duplicate', $content );
		$result  = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_COMPLETE, $result->status );
		$this->assertGreaterThan( 0, $result->duplicate_repaired_count );
		$this->assertSame( 1, substr_count( (string) get_post( $post->ID )->post_content, self::UUID_A ) );
	}

	public function test_nested_block_migration(): void {
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. $this->paragraph( 'Nested' )
			. '</div><!-- /wp:group -->';
		$post    = $this->create_page( 'Nested', $content );
		$result  = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_COMPLETE, $result->status );
		$this->assertStringContainsString( '"aimlBlockId"', (string) get_post( $post->ID )->post_content );
	}

	public function test_fully_compliant_post_writes_nothing(): void {
		$post   = $this->create_page( 'Compliant', $this->paragraph( 'Stable', self::UUID_A ) );
		$before = (string) get_post( $post->ID )->post_content;
		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );
		$after  = (string) get_post( $post->ID )->post_content;

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result->status );
		$this->assertSame( BlockMigrationEligibility::REASON_ALREADY_COMPLIANT, $result->skip_reason );
		$this->assertSame( $before, $after );
	}

	public function test_extraction_backfill_on_compliant_post(): void {
		$post   = $this->create_page( 'Refresh', $this->paragraph( 'Stable', self::UUID_A ) );
		$result = $this->migration()->migrate_post(
			(int) $post->ID,
			new BlockMigrationOptions( false, true )
		);

		$this->assertSame( BlockMigrationResult::STATUS_COMPLETE, $result->status );
		$this->assertFalse( $result->content_changed );
		$this->assertTrue( $result->extraction_synced );
		$this->assertGreaterThan( 0, $result->segment_count );
	}

	public function test_second_run_is_idempotent(): void {
		$post      = $this->create_page( 'Idempotent', $this->paragraph( 'Once' ) );
		$migration = $this->migration();
		$first     = $migration->migrate_post( (int) $post->ID, new BlockMigrationOptions() );
		$stored    = (string) get_post( $post->ID )->post_content;
		$second    = $migration->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_COMPLETE, $first->status );
		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $second->status );
		$this->assertSame( $stored, (string) get_post( $post->ID )->post_content );
	}

	public function test_unsupported_post_type_is_skipped(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'attachment',
				'post_content' => $this->paragraph( 'Nope' ),
			)
		);
		$result  = $this->migration()->migrate_post( $post_id, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result->status );
		$this->assertSame( BlockMigrationEligibility::REASON_UNSUPPORTED_POST_TYPE, $result->skip_reason );
	}

	public function test_revision_is_skipped(): void {
		$post     = $this->create_page( 'Parent', $this->paragraph( 'Parent', self::UUID_A ) );
		$revision = wp_save_post_revision( $post );
		$this->assertNotFalse( $revision );

		$result = $this->migration()->migrate_post( (int) $revision, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result->status );
		$this->assertSame( BlockMigrationEligibility::REASON_REVISION, $result->skip_reason );
	}

	public function test_autosave_is_skipped(): void {
		$post = $this->create_page( 'Autosave parent', $this->paragraph( 'Parent', self::UUID_A ) );
		$id   = wp_create_post_autosave(
			array(
				'post_ID'      => $post->ID,
				'post_type'    => 'page',
				'post_content' => $this->paragraph( 'Autosave', self::UUID_B ),
			)
		);
		$this->assertNotFalse( $id );

		$result = $this->migration()->migrate_post( (int) $id, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result->status );
		$this->assertSame( BlockMigrationEligibility::REASON_AUTOSAVE, $result->skip_reason );
	}

	public function test_trashed_post_is_skipped(): void {
		$post = $this->create_page( 'Trash', $this->paragraph( 'Trash me' ) );
		wp_trash_post( $post->ID );

		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result->status );
		$this->assertSame( BlockMigrationEligibility::REASON_TRASHED, $result->skip_reason );
	}

	public function test_elementor_body_is_skipped(): void {
		$post = $this->create_page( 'Elementor', $this->paragraph( 'Body', self::UUID_A ) );
		update_post_meta( $post->ID, '_elementor_data', '[{"id":"abc"}]' );

		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result->status );
		$this->assertSame( BlockMigrationEligibility::REASON_ELEMENTOR_BODY, $result->skip_reason );
	}

	public function test_non_block_content_is_skipped(): void {
		$post   = $this->create_page( 'Classic', '<p>Classic only</p>' );
		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result->status );
		$this->assertSame( BlockMigrationEligibility::REASON_NON_BLOCK_CONTENT, $result->skip_reason );
	}

	public function test_empty_content_is_skipped(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '',
			)
		);
		$result  = $this->migration()->migrate_post( $post_id, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result->status );
		$this->assertSame( BlockMigrationEligibility::REASON_EMPTY_CONTENT, $result->skip_reason );
	}

	public function test_dry_run_performs_no_writes(): void {
		global $wpdb;

		$post = $this->create_page( 'Dry run', $this->paragraph( 'Dry', self::UUID_A ) );
		$key  = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $this->languages->default()->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Dry</p>',
				'translated_text' => 'Should stay',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$before_content = (string) get_post( $post->ID )->post_content;
		$before_rows    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aiml_translations" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result         = $this->migration()->migrate_post(
			(int) $post->ID,
			new BlockMigrationOptions( true, true )
		);
		$after_rows     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aiml_translations" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->assertSame( BlockMigrationResult::STATUS_DRY_RUN, $result->status );
		$this->assertSame( $before_content, (string) get_post( $post->ID )->post_content );
		$this->assertSame( $before_rows, $after_rows );
	}

	public function test_dry_run_reports_expected_changes(): void {
		$post   = $this->create_page( 'Dry report', $this->paragraph( 'Needs uuid' ) );
		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions( true ) );

		$this->assertSame( BlockMigrationResult::STATUS_DRY_RUN, $result->status );
		$this->assertTrue( $result->content_changed );
		$this->assertGreaterThan( 0, $result->created_count );
		$this->assertGreaterThan( 0, $result->segment_count );
		$this->assertNotSame( $result->original_hash, $result->migrated_hash );
	}

	public function test_injector_failure_leaves_post_unchanged(): void {
		$content   = $this->paragraph( 'One', self::UUID_A ) . $this->paragraph( 'Two', self::UUID_A );
		$post      = $this->create_page( 'Fail inject', $content );
		$migration = new BlockIdentityMigration(
			new UuidInjector(
				new BlockRegistry(),
				new \AIMultilingual\Block\BlockIdentityLogger(),
				static fn(): string => self::UUID_A
			),
			$this->extractor,
			$this->extraction_enabled_extractor(),
			$this->store,
			new BlockMigrationLogger()
		);

		$before = (string) get_post( $post->ID )->post_content;
		$result = $migration->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_FAILED, $result->status );
		$this->assertSame( $before, (string) get_post( $post->ID )->post_content );
	}

	public function test_database_update_failure_reports_failure(): void {
		$post = $this->create_page( 'Update fail', $this->paragraph( 'Needs uuid' ) );
		add_filter(
			'aiml_block_migration_persist_post',
			static function (): \WP_Error {
				return new \WP_Error( 'test_update_failed', 'Blocked for test.' );
			}
		);

		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_FAILED, $result->status );
		$this->assertSame( 'test_update_failed', $result->failure_reason );
	}

	public function test_concurrent_modification_prevents_overwrite(): void {
		$post = $this->create_page( 'Concurrent', $this->paragraph( 'Race' ) );

		add_action(
			'aiml_block_migration_before_persist',
			static function ( int $post_id ): void {
				global $wpdb;

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->posts,
					array( 'post_content' => '<!-- wp:paragraph --><p>Changed elsewhere</p><!-- /wp:paragraph -->' ),
					array( 'ID' => $post_id )
				);
				clean_post_cache( $post_id );
			}
		);

		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_FAILED, $result->status );
		$this->assertSame( 'concurrent_modification', $result->failure_reason );
		$this->assertStringContainsString( 'Changed elsewhere', (string) get_post( $post->ID )->post_content );
	}

	public function test_source_segments_available_after_migration(): void {
		$post   = $this->create_page( 'Segments', $this->paragraph( 'Segment me' ) );
		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );
		$fresh  = get_post( $post->ID );
		$this->assertInstanceOf( \WP_Post::class, $fresh );

		$segments = $this->extraction_enabled_extractor()->extract( $fresh );

		$this->assertSame( BlockMigrationResult::STATUS_COMPLETE, $result->status );
		$this->assertGreaterThan( 0, $result->segment_count );
		$this->assertNotEmpty(
			array_filter(
				array_keys( $segments ),
				static fn( string $key ): bool => str_starts_with( $key, 'b:' )
			)
		);
	}

	public function test_existing_source_segments_are_reconciled(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Reconcile', $this->paragraph( 'Original', self::UUID_A ) );
		$key      = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );

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
				'source_text'     => '<p>Old hash</p>',
				'translated_text' => 'Translated stays',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => $this->paragraph( 'Changed text', self::UUID_A ),
			)
		);

		$this->migration()->migrate_post(
			(int) $post->ID,
			new BlockMigrationOptions( false, true )
		);

		$row = $this->store->get(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key
		);

		$this->assertNotNull( $row );
		$this->assertSame( 1, (int) $row->is_stale );
		$this->assertSame( 'Translated stays', $row->translated_text );
	}

	public function test_no_translations_created_by_migration(): void {
		global $wpdb;

		$post = $this->create_page( 'No translate', $this->paragraph( 'Source only' ) );
		$this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$created = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aiml_translations WHERE source_id = %d AND translated_text <> '' AND status <> %s",
				$post->ID,
				Store::STATUS_MISSING
			)
		);

		$this->assertSame( 0, $created );
	}

	public function test_frontend_rendering_flag_remains_unchanged(): void {
		$before = ( new Settings() )->block_frontend_rendering_enabled();
		$post   = $this->create_page( 'Flags', $this->paragraph( 'Flag check' ) );
		$this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions() );

		$this->assertSame( $before, ( new Settings() )->block_frontend_rendering_enabled() );
		$this->assertFalse( ( new Settings() )->block_frontend_rendering_enabled() );
	}

	public function test_batch_ordering_is_deterministic(): void {
		$first  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $this->paragraph( 'First-' . wp_generate_password( 6, false ) ),
			)
		);
		$second = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $this->paragraph( 'Second-' . wp_generate_password( 6, false ) ),
			)
		);
		$low    = min( $first, $second );
		$high   = max( $first, $second );

		$batch = $this->migration()->migrate_batch( 'page', 100, 0, new BlockMigrationOptions( true ) );
		$ids   = array_map(
			static fn( BlockMigrationResult $result ): int => $result->post_id,
			$batch->results
		);

		$first_index  = array_search( $low, $ids, true );
		$second_index = array_search( $high, $ids, true );

		$this->assertNotFalse( $first_index );
		$this->assertNotFalse( $second_index );
		$this->assertLessThan( $second_index, $first_index );
	}

	public function test_batch_size_is_bounded(): void {
		$batch = $this->migration()->migrate_batch( 'page', 500, 0, new BlockMigrationOptions( true ) );

		$this->assertLessThanOrEqual( BlockIdentityMigration::MAX_BATCH_SIZE, count( $batch->results ) );
	}

	public function test_one_failed_post_does_not_stop_batch(): void {
		$good = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $this->paragraph( 'Good' ),
			)
		);
		$bad  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '',
			)
		);

		$batch = $this->migration()->migrate_batch( 'page', 50, 0, new BlockMigrationOptions( true ) );
		$by_id = array_column(
			array_map(
				static fn( BlockMigrationResult $result ): array => $result->to_array(),
				$batch->results
			),
			null,
			'post_id'
		);

		$this->assertArrayHasKey( $good, $by_id );
		$this->assertArrayHasKey( $bad, $by_id );
		$this->assertSame( BlockMigrationResult::STATUS_DRY_RUN, $by_id[ $good ]['status'] );
		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $by_id[ $bad ]['status'] );
	}

	public function test_json_output_structure(): void {
		$post   = $this->create_page( 'JSON', $this->paragraph( 'Json', self::UUID_A ) );
		$result = $this->migration()->migrate_post( (int) $post->ID, new BlockMigrationOptions( true ) );
		$array  = $result->to_array();

		foreach ( array( 'post_id', 'status', 'dry_run', 'original_hash', 'segment_count' ) as $key ) {
			$this->assertArrayHasKey( $key, $array );
		}
	}

	public function test_same_uuid_in_different_posts_remains_valid(): void {
		$post_a = $this->create_page( 'Post A', $this->paragraph( 'A', self::UUID_A ) );
		$post_b = $this->create_page( 'Post B', $this->paragraph( 'B', self::UUID_A ) );

		$result_a = $this->migration()->migrate_post( (int) $post_a->ID, new BlockMigrationOptions() );
		$result_b = $this->migration()->migrate_post( (int) $post_b->ID, new BlockMigrationOptions() );

		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result_a->status );
		$this->assertSame( BlockMigrationResult::STATUS_SKIPPED, $result_b->status );
		$this->assertStringContainsString( self::UUID_A, (string) get_post( $post_a->ID )->post_content );
		$this->assertStringContainsString( self::UUID_A, (string) get_post( $post_b->ID )->post_content );
	}

	public function test_no_duplicate_store_rows_after_rerun(): void {
		global $wpdb;

		$language = $this->add_language();
		$post     = $this->create_page( 'Store rows', $this->paragraph( 'Original', self::UUID_A ) );
		$key      = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
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
				'source_text'     => '<p>Original</p>',
				'translated_text' => 'Stored once',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$migration = $this->migration();
		$migration->migrate_post( (int) $post->ID, new BlockMigrationOptions( false, true ) );
		$count_after_first = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aiml_translations WHERE source_id = %d AND segment_key = %s",
				$post->ID,
				$key
			)
		);
		$migration->migrate_post( (int) $post->ID, new BlockMigrationOptions( false, true ) );
		$count_after_second = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}aiml_translations WHERE source_id = %d AND segment_key = %s",
				$post->ID,
				$key
			)
		);

		$this->assertSame( 1, $count_after_first );
		$this->assertSame( $count_after_first, $count_after_second );
	}

	private function migration(): BlockIdentityMigration {
		return new BlockIdentityMigration(
			new UuidInjector( new BlockRegistry(), new \AIMultilingual\Block\BlockIdentityLogger() ),
			$this->extractor,
			$this->extraction_enabled_extractor(),
			$this->store,
			new BlockMigrationLogger()
		);
	}

	private function extraction_enabled_extractor(): Extractor {
		$settings = new Settings(
			array(
				'block_attr_registration_enabled' => true,
				'block_uuid_injection_enabled'    => true,
				'block_extraction_enabled'        => true,
			)
		);
		$registry = new \AIMultilingual\Block\AdapterRegistry();

		return new Extractor(
			$settings,
			new BlockExtractor(
				$registry,
				new BlockRegistry( $registry ),
				new \AIMultilingual\Block\BlockExtractionLogger()
			)
		);
	}

	private function paragraph( string $text, ?string $uuid = null ): string {
		if ( null === $uuid ) {
			return '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
		}

		return sprintf(
			'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
			Contract::ATTR_NAME,
			$uuid,
			$text
		);
	}
}
