<?php
/**
 * TI.7 PublicationService integration (manual/auto, guards, dry-run).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Settings;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationMode;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationReasonCodes;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;

/**
 * Canonical PublicationService mutation + explain behaviour.
 */
final class PublicationServiceTest extends AimlTestCase {

	private PublicationService $publication;

	protected function setUp(): void {
		parent::setUp();

		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array_merge(
					Settings::defaults(),
					array(
						'segment_publication_gate_enabled' => false,
						'auto_publication_mode'            => PublicationMode::MANUAL,
					)
				)
			)
		);

		$this->publication = new PublicationService(
			$this->store,
			new AssessmentAssembler(),
			new PublicationPolicy(),
			new PublicationAuditLogger(),
			new Settings()
		);
	}

	/**
	 * @param \WP_Post             $post          Canonical post.
	 * @param object               $language      Target language row.
	 * @param string               $key           Segment key.
	 * @param array<string, mixed> $extra         Extra save_translation fields.
	 * @param string|null          $review_status Optional review_status applied after save.
	 */
	private function seed_segment( \WP_Post $post, object $language, string $key, array $extra = array(), ?string $review_status = Store::REVIEW_APPROVED ): void {
		$ok = $this->store->save_translation(
			array_merge(
				array(
					'source_type'     => Store::SOURCE_POST,
					'source_id'       => (int) $post->ID,
					'source_subtype'  => (string) $post->post_type,
					'language_id'     => (int) $language->language_id,
					'field_key'       => 'post_title',
					'segment_key'     => $key,
					'segment_order'   => 10,
					'text_format'     => Store::FORMAT_PLAIN,
					'source_text'     => 'Hello world',
					'translated_text' => 'Hej världen',
					'status'          => Store::STATUS_MACHINE_TRANSLATED,
					'provider'        => 'openai',
					'model'           => 'gpt-test',
					'prompt_profile'  => 'default',
					'prompt_version'  => '1',
				),
				$extra
			)
		);
		$this->assertTrue( $ok );

		if ( null !== $review_status ) {
			$updated = $this->store->update_review_metadata(
				Store::SOURCE_POST,
				(int) $post->ID,
				(int) $language->language_id,
				$key,
				array( 'review_status' => $review_status )
			);
			$this->assertTrue( $updated );
		}
	}

	public function test_explain_does_not_mutate(): void {
		$post     = $this->create_page();
		$language = $this->add_language();
		$key      = 'pub-explain';
		$this->seed_segment( $post, $language, $key );

		$before = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $before );

		$decision = $this->publication->explain(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			false
		);
		$this->assertNotInstanceOf( \WP_Error::class, $decision );

		$after = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $after );
		$this->assertSame( (string) $before->publish_status, (string) $after->publish_status );
		$this->assertSame( (string) $before->review_status, (string) $after->review_status );
		$this->assertSame( (string) $before->translated_text, (string) $after->translated_text );
		$this->assertSame( 'P1.0', $decision->policy_version );
	}

	public function test_manual_publish_and_unpublish(): void {
		$post     = $this->create_page();
		$language = $this->add_language();
		$key      = 'pub-manual';
		$this->seed_segment( $post, $language, $key );

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'published', $result['status'] );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $row->publish_status );

		$noop = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $noop );
		$this->assertSame( 'noop', $noop['status'] );
		$this->assertContains( PublicationReasonCodes::PUBLICATION_ALREADY_ACTIVE, $noop['reason_codes'] );

		$unpub = $this->publication->unpublish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			1
		);
		$this->assertIsArray( $unpub );
		$this->assertSame( 'unpublished', $unpub['status'] );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
		$this->assertSame( Store::REVIEW_APPROVED, (string) $row->review_status );
		$this->assertSame( 'Hej världen', (string) $row->translated_text );
	}

	public function test_draft_source_blocks_publish(): void {
		$post = $this->create_page();
		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'draft',
			)
		);
		$language = $this->add_language();
		$key      = 'pub-draft';
		$this->seed_segment( $post, $language, $key );

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'skipped', $result['status'] );
		$this->assertContains( PublicationReasonCodes::SOURCE_NOT_PUBLIC, $result['reason_codes'] );
	}

	public function test_rejected_blocks_manual_publish(): void {
		$post     = $this->create_page();
		$language = $this->add_language();
		$key      = 'pub-rejected';
		$this->seed_segment(
			$post,
			$language,
			$key,
			array(),
			Store::REVIEW_REJECTED
		);

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'skipped', $result['status'] );
		$this->assertContains( PublicationReasonCodes::REVIEW_REJECTED, $result['reason_codes'] );
	}

	public function test_maybe_auto_publish_skipped_in_manual_mode(): void {
		$post     = $this->create_page();
		$language = $this->add_language();
		$key      = 'pub-auto-manual';
		$this->seed_segment( $post, $language, $key );

		$result = $this->publication->maybe_auto_publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key
		);

		$this->assertSame( 'skipped', $result['status'] );
		$this->assertContains( PublicationReasonCodes::AUTOMATION_DISABLED, $result['reason_codes'] );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
	}

	public function test_approved_only_auto_publishes_when_approved(): void {
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array_merge(
					Settings::defaults(),
					array( 'auto_publication_mode' => PublicationMode::APPROVED_ONLY )
				)
			)
		);
		$this->publication = new PublicationService(
			$this->store,
			new AssessmentAssembler(),
			new PublicationPolicy(),
			new PublicationAuditLogger(),
			new Settings()
		);

		$post     = $this->create_page();
		$language = $this->add_language();
		$key      = 'pub-approved-only';
		$this->seed_segment( $post, $language, $key );

		$result = $this->publication->maybe_auto_publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			array( 'Source locale: ', 'Target locale: ' ),
			true
		);

		$this->assertSame(
			'published',
			$result['status'],
			'Unexpected auto result: ' . wp_json_encode( $result )
		);
	}

	public function test_three_axes_remain_independent(): void {
		$post     = $this->create_page();
		$language = $this->add_language();
		$key      = 'pub-axes';
		$this->seed_segment(
			$post,
			$language,
			$key,
			array(
				'status' => Store::STATUS_MACHINE_TRANSLATED,
			),
			Store::REVIEW_PENDING
		);

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( Store::STATUS_MACHINE_TRANSLATED, (string) $row->status );
		$this->assertSame( Store::REVIEW_PENDING, (string) $row->review_status );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );

		$this->store->update_publish_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			array(
				'publish_status' => Store::PUBLISH_PUBLISHED,
				'published_at'   => current_time( 'mysql', true ),
				'published_by'   => 1,
			)
		);

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( Store::REVIEW_PENDING, (string) $row->review_status );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $row->publish_status );
		$this->assertNotSame( Store::REVIEW_APPROVED, (string) $row->publish_status );
	}

	public function test_source_change_staleness_does_not_auto_unpublish(): void {
		$post     = $this->create_page( 'About', '<p>Original</p>' );
		$language = $this->add_language();
		$key      = 'post_content';
		$this->translate( $post, $language, $key, 'Översatt' );

		$this->store->update_publish_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			array(
				'publish_status' => Store::PUBLISH_PUBLISHED,
				'published_at'   => current_time( 'mysql', true ),
				'published_by'   => 1,
			)
		);

		$sources = $this->extractor->extract( $post );
		$this->assertArrayHasKey( $key, $sources );
		$sources[ $key ]['source_text'] = '<p>Changed source</p>';
		$this->store->sync_source( Store::SOURCE_POST, (int) $post->ID, (string) $post->post_type, $sources );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertTrue( (bool) $row->is_stale );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $row->publish_status );

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $result );
		// Already published → noop eligibility path even if stale for new publish.
		$this->assertSame( 'noop', $result['status'] );
	}

	public function test_stale_unpublished_cannot_newly_publish(): void {
		$post     = $this->create_page( 'About', '<p>Original</p>' );
		$language = $this->add_language();
		$key      = 'post_content';
		$this->translate( $post, $language, $key, 'Översatt' );

		$sources                        = $this->extractor->extract( $post );
		$sources[ $key ]['source_text'] = '<p>Changed source</p>';
		$this->store->sync_source( Store::SOURCE_POST, (int) $post->ID, (string) $post->post_type, $sources );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertTrue( (bool) $row->is_stale );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'skipped', $result['status'] );
		$this->assertContains( PublicationReasonCodes::TRANSLATION_STALE, $result['reason_codes'] );
	}

	public function test_publish_rechecks_when_source_becomes_private(): void {
		$post     = $this->create_page();
		$language = $this->add_language();
		$key      = 'pub-race-private';
		$this->seed_segment( $post, $language, $key );

		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'private',
			)
		);

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$key,
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'skipped', $result['status'] );
		$this->assertContains( PublicationReasonCodes::SOURCE_NOT_PUBLIC, $result['reason_codes'] );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $key );
		$this->assertNotNull( $row );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
	}
}
