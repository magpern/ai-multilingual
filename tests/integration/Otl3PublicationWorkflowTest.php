<?php
/**
 * OTL.3 publication workflow + stale + admissions integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Operator\ActionReasonCodes;
use AIMultilingual\Workspace\Operator\AllowedActionsResolver;
use WP_REST_Request;

/**
 * Manual publish/unpublish, stale behavior, retranslate admission.
 */
final class Otl3PublicationWorkflowTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private PublicationService $publication;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
		$this->publication = new PublicationService(
			$this->store,
			new AssessmentAssembler(),
			new PublicationPolicy(),
			new PublicationAuditLogger(),
			new Settings()
		);
	}

	/**
	 * @return array{post:\WP_Post,language_id:int,key:string}
	 */
	private function seed_translated_post_content(): array {
		$language = $this->add_language();
		$post     = $this->create_page( 'About', '<p>Original English</p>' );
		$key      = 'post_content';
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => (int) $language->language_id,
				'field_key'       => $key,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'segment_order'   => 0,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Original English</p>',
				'translated_text' => '<p>Översatt</p>',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		return array(
			'post'        => $post,
			'language_id' => (int) $language->language_id,
			'key'         => $key,
		);
	}

	public function test_manual_publish_and_unpublish(): void {
		$seed = $this->seed_translated_post_content();
		wp_set_current_user( $this->create_translator() );

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key'],
			false,
			get_current_user_id(),
			'workspace'
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'published', $result['status'] );

		$row = $this->store->get( Store::SOURCE_POST, (int) $seed['post']->ID, $seed['language_id'], $seed['key'] );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $row->publish_status );

		$un = $this->publication->unpublish(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key'],
			get_current_user_id()
		);
		$this->assertIsArray( $un );
		$this->assertSame( 'unpublished', $un['status'] );
		$row = $this->store->get( Store::SOURCE_POST, (int) $seed['post']->ID, $seed['language_id'], $seed['key'] );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
	}

	public function test_stale_published_remains_and_stale_unpublished_cannot_publish(): void {
		$seed = $this->seed_translated_post_content();
		$this->publication->publish(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key'],
			false,
			1,
			'manual'
		);

		$sources = array(
			$seed['key'] => array(
				'source_text' => '<p>Changed source</p>',
				'text_format' => Store::FORMAT_HTML,
			),
		);
		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			(string) $seed['post']->post_type,
			$sources
		);

		$row = $this->store->get( Store::SOURCE_POST, (int) $seed['post']->ID, $seed['language_id'], $seed['key'] );
		$this->assertTrue( (bool) $row->is_stale );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $row->publish_status );

		$this->publication->unpublish(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key'],
			1
		);

		$result = $this->publication->publish(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key'],
			false,
			1,
			'manual'
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'skipped', $result['status'] );
		$row = $this->store->get( Store::SOURCE_POST, (int) $seed['post']->ID, $seed['language_id'], $seed['key'] );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
	}

	public function test_retranslate_stale_admission_no_longer_deferred(): void {
		$user = $this->create_translator();
		wp_set_current_user( $user );
		$post = $this->create_block_page();

		$row = (object) array(
			'source_type'    => Store::SOURCE_POST,
			'source_id'      => (int) $post->ID,
			'is_stale'       => 1,
			'publish_status' => Store::PUBLISH_UNPUBLISHED,
			'status'         => Store::STATUS_MACHINE_TRANSLATED,
			'review_status'  => Store::REVIEW_NOT_SUBMITTED,
		);

		$resolver = new AllowedActionsResolver();
		$caps     = AllowedActionsResolver::capability_flags( $user, $post );
		$actions  = $resolver->resolve_for_list( $row, $caps );
		$found    = null;
		foreach ( $actions as $action ) {
			if ( 'retranslate_stale' === $action['id'] ) {
				$found = $action;
				break;
			}
		}
		$this->assertNotNull( $found );
		$this->assertTrue( $found['allowed'] );
		$this->assertNull( $found['reason_code'] );
		$this->assertNotSame( ActionReasonCodes::DEFERRED_MILESTONE, $found['reason_code'] );
	}

	public function test_detail_includes_publication_settings(): void {
		$seed = $this->seed_translated_post_content();
		wp_set_current_user( $this->create_translator() );

		$row = $this->store->get(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key']
		);
		$this->assertNotNull( $row );
		$translation_id = (int) ( $row->translation_id ?? 0 );
		$this->assertGreaterThan( 0, $translation_id );

		$request  = new WP_REST_Request( 'GET', '/aiml/v1/workspace/operations/' . $translation_id );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'publication_settings', $data );
		$this->assertArrayHasKey( 'segment_publication_gate_enabled', $data['publication_settings'] );
		$this->assertArrayHasKey( 'auto_publication_mode', $data['publication_settings'] );
	}

	public function test_gate_setting_does_not_mutate_publish_status(): void {
		$seed = $this->seed_translated_post_content();
		$this->publication->publish(
			Store::SOURCE_POST,
			(int) $seed['post']->ID,
			$seed['language_id'],
			$seed['key'],
			false,
			1,
			'manual'
		);

		$before = $this->store->get( Store::SOURCE_POST, (int) $seed['post']->ID, $seed['language_id'], $seed['key'] );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $before->publish_status );

		$current = get_option( Settings::OPTION, Settings::defaults() );
		if ( ! is_array( $current ) ) {
			$current = Settings::defaults();
		}
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array_merge(
					$current,
					array( 'segment_publication_gate_enabled' => true )
				)
			)
		);
		Plugin::instance()->reload_settings();

		$after = $this->store->get( Store::SOURCE_POST, (int) $seed['post']->ID, $seed['language_id'], $seed['key'] );
		$this->assertSame( Store::PUBLISH_PUBLISHED, (string) $after->publish_status );
		$this->assertTrue( Store::is_publicly_overlay_eligible( $after, true ) );

		$unpublished                 = clone $after;
		$unpublished->publish_status = Store::PUBLISH_UNPUBLISHED;
		$this->assertFalse( Store::is_publicly_overlay_eligible( $unpublished, true ) );
		$this->assertTrue( Store::is_publicly_overlay_eligible( $unpublished, false ) );
	}
}
