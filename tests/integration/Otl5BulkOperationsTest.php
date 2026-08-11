<?php
/**
 * OTL.5 Operations bulk REST integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Bounded bulk publish / unpublish / enqueue_retranslate.
 */
final class Otl5BulkOperationsTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_bulk_requires_auth(): void {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', '/aiml/v1/workspace/operations/bulk' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'action' => 'unpublish',
					'items'  => array( array( 'translation_id' => 1 ) ),
				)
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_bulk_rejects_over_limit(): void {
		$language = $this->add_language();
		wp_set_current_user( $this->create_translator() );

		$items = array();
		for ( $i = 1; $i <= 51; $i++ ) {
			$items[] = array( 'translation_id' => $i );
		}

		$request = new WP_REST_Request( 'POST', '/aiml/v1/workspace/operations/bulk' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'action' => 'unpublish',
					'items'  => $items,
				)
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'aiml_batch_too_large', $response->get_data()['code'] ?? null );
		unset( $language );
	}

	public function test_bulk_unpublish_partial_and_review_untouched(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Bulk unpub', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Titel' );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );
		$tid = (int) $row->translation_id;

		$this->store->update_publish_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			'post_title',
			array(
				'publish_status' => Store::PUBLISH_PUBLISHED,
				'published_at'   => current_time( 'mysql', true ),
				'published_by'   => 1,
			)
		);
		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			'post_title',
			array(
				'review_status' => Store::REVIEW_APPROVED,
			)
		);

		wp_set_current_user( $this->create_translator() );
		$request = new WP_REST_Request( 'POST', '/aiml/v1/workspace/operations/bulk' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'action' => 'unpublish',
					'items'  => array(
						array( 'translation_id' => $tid ),
						array( 'translation_id' => 999999991 ),
					),
				)
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'partial', $data['status'] );
		$this->assertCount( 2, $data['items'] );

		$by_id = array();
		foreach ( $data['items'] as $item ) {
			$by_id[ (int) $item['translation_id'] ] = $item;
		}
		$this->assertSame( 'unpublished', $by_id[ $tid ]['outcome'] );
		$this->assertContains( $by_id[999999991]['outcome'], array( 'blocked', 'failed' ) );

		$after = $this->store->get_by_translation_id( $tid );
		$this->assertNotNull( $after );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $after->publish_status );
		$this->assertSame( Store::REVIEW_APPROVED, (string) $after->review_status );
	}

	public function test_bulk_publish_skipped_when_stale(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Bulk pub', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_content', 'Inhalt' );

		$sources = array(
			'post_content' => array(
				'source_text' => '<p>Changed source</p>',
				'text_format' => Store::FORMAT_HTML,
			),
		);
		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $post->ID,
			(string) $post->post_type,
			$sources
		);

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_content' );
		$this->assertNotNull( $row );
		$this->assertTrue( (bool) $row->is_stale );
		$tid = (int) $row->translation_id;

		wp_set_current_user( $this->create_translator() );
		$request = new WP_REST_Request( 'POST', '/aiml/v1/workspace/operations/bulk' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'action' => 'publish',
					'items'  => array( array( 'translation_id' => $tid ) ),
				)
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'skipped', $data['items'][0]['outcome'] );
		$this->assertContains( 'translation_stale', $data['items'][0]['reason_codes'] ?? array() );
	}

	public function test_bulk_enqueue_returns_enqueued_and_operations(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Bulk enqueue', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Titel' );
		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );
		$tid = (int) $row->translation_id;

		global $wpdb;
		$wpdb->update(
			\AIMultilingual\Database\Schema::translations(),
			array( 'is_stale' => 1 ),
			array( 'translation_id' => $tid ),
			array( '%d' ),
			array( '%d' )
		);

		wp_set_current_user( $this->create_translator() );
		$request = new WP_REST_Request( 'POST', '/aiml/v1/workspace/operations/bulk' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'action' => 'enqueue_retranslate',
					'items'  => array( array( 'translation_id' => $tid ) ),
				)
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'enqueued', $data['items'][0]['outcome'] );
		$this->assertArrayHasKey( 'operations', $data );
		$this->assertNotEmpty( $data['operations'] );
		$this->assertSame( 'created', $data['operations'][0]['outcome'] );
		$this->assertGreaterThan( 0, (int) ( $data['operations'][0]['job_id'] ?? 0 ) );
		$this->assertSame( 'enqueued', $data['items'][0]['outcome'] );
		$this->assertNotSame( 'published', $data['items'][0]['outcome'] );
		$this->assertNotSame( 'translated', $data['items'][0]['outcome'] ?? '' );
	}

	public function test_bulk_rejects_retry_failed_action(): void {
		wp_set_current_user( $this->create_translator() );
		$request = new WP_REST_Request( 'POST', '/aiml/v1/workspace/operations/bulk' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'action' => 'retry_failed',
					'items'  => array( array( 'translation_id' => 1 ) ),
				)
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 422, $response->get_status() );
	}

	public function test_bulk_enqueue_groups_into_one_job_for_same_object(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Bulk group', '<p>Body text</p>' );
		$this->translate( $post, $language, 'post_title', 'Titel' );
		$this->translate( $post, $language, 'post_content', 'Inhalt' );

		$title = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$body  = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_content' );
		$this->assertNotNull( $title );
		$this->assertNotNull( $body );

		global $wpdb;
		foreach ( array( (int) $title->translation_id, (int) $body->translation_id ) as $tid ) {
			$wpdb->update(
				\AIMultilingual\Database\Schema::translations(),
				array( 'is_stale' => 1 ),
				array( 'translation_id' => $tid ),
				array( '%d' ),
				array( '%d' )
			);
		}

		wp_set_current_user( $this->create_translator() );
		$request = new WP_REST_Request( 'POST', '/aiml/v1/workspace/operations/bulk' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'action' => 'enqueue_retranslate',
					'items'  => array(
						array( 'translation_id' => (int) $title->translation_id ),
						array( 'translation_id' => (int) $body->translation_id ),
					),
				)
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 2, $data['items'] );
		$this->assertCount( 1, $data['operations'] );
		$this->assertSame( 'enqueued', $data['items'][0]['outcome'] );
		$this->assertSame( 'enqueued', $data['items'][1]['outcome'] );
		$this->assertSame(
			(int) $data['items'][0]['job_id'],
			(int) $data['items'][1]['job_id']
		);
	}

	public function test_bulk_response_omits_translation_bodies(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Bulk privacy', '<p>Secret body</p>' );
		$this->translate( $post, $language, 'post_title', 'Secret title' );
		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );

		wp_set_current_user( $this->create_translator() );
		$request = new WP_REST_Request( 'POST', '/aiml/v1/workspace/operations/bulk' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'action' => 'unpublish',
					'items'  => array( array( 'translation_id' => (int) $row->translation_id ) ),
				)
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$encoded = wp_json_encode( $response->get_data() );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'Secret title', $encoded );
		$this->assertStringNotContainsString( 'Secret body', $encoded );
		$this->assertStringNotContainsString( 'translated_text', $encoded );
		$this->assertStringNotContainsString( 'source_text', $encoded );
	}
}
