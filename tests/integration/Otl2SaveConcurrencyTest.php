<?php
/**
 * OTL.2 target concurrency + save invalidation integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Target hash optimistic concurrency and material-edit invalidation.
 */
final class Otl2SaveConcurrencyTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_missing_expected_translation_hash_fails_closed(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$segment = $this->load_first_block_segment( (int) $post->ID );
		$request = new WP_REST_Request(
			'POST',
			sprintf( '/aiml/v1/workspace/%d/segments/%s', (int) $post->ID, $segment['segment_key'] )
		);
		$request->set_url_params(
			array(
				'post_id'     => (int) $post->ID,
				'segment_key' => (string) $segment['segment_key'],
			)
		);
		$request->set_param( 'language', 'sv' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'translated_text' => 'No hash field',
					'source_hash'     => $segment['source_hash'],
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			)
		);

		$response = rest_do_request( $request );
		$this->assertSame( 422, $response->get_status() );
		$error = $response->as_error();
		$this->assertNotNull( $error );
		$this->assertSame( 'aiml_invalid_segment', $error->get_error_code() );
	}

	public function test_stale_source_hash_returns_distinct_code(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$segment  = $this->load_first_block_segment( (int) $post->ID );
		$response = rest_do_request(
			$this->workspace_save_request(
				(int) $post->ID,
				$segment,
				array(
					'translated_text' => 'Stale source attempt',
					'source_hash'     => 'deadbeef',
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			)
		);

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'aiml_source_hash_mismatch', $response->get_data()['code'] );
	}

	public function test_concurrent_target_save_returns_translation_hash_mismatch(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$a = $this->load_first_block_segment( (int) $post->ID );

		$b_save = rest_do_request(
			$this->workspace_save_request(
				(int) $post->ID,
				$a,
				array(
					'translated_text' => 'Target from B',
					'source_hash'     => $a['source_hash'],
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			)
		);
		$this->assertSame( 200, $b_save->get_status(), wp_json_encode( $b_save->get_data() ) );
		$b_data = $b_save->get_data();
		$this->assertSame( 'Target from B', $b_data['translated_text'] );
		$this->assertNotSame( (string) ( $a['translation_hash'] ?? '' ), (string) $b_data['translation_hash'] );

		$a_conflict = rest_do_request(
			$this->workspace_save_request(
				(int) $post->ID,
				$a,
				array(
					'translated_text'           => 'Target from A',
					'source_hash'               => $a['source_hash'],
					'status'                    => Store::STATUS_MANUALLY_EDITED,
					'expected_translation_hash' => (string) ( $a['translation_hash'] ?? '' ),
				)
			)
		);
		$this->assertSame( 409, $a_conflict->get_status() );
		$this->assertSame( 'aiml_translation_hash_mismatch', $a_conflict->get_data()['code'] );
		$this->assertSame( 'Target from B', $a_conflict->get_data()['segments'][0]['translated_text'] );

		$persisted = $this->load_first_block_segment( (int) $post->ID );
		$this->assertSame( 'Target from B', $persisted['translated_text'] );
	}

	public function test_material_edit_clears_review_and_publish(): void {
		$language = $this->add_language();
		$post     = $this->create_block_page();
		$uid      = $this->create_translator();
		wp_set_current_user( $uid );

		$segment = $this->load_first_block_segment( (int) $post->ID );
		$save1   = rest_do_request(
			$this->workspace_save_request(
				(int) $post->ID,
				$segment,
				array(
					'translated_text' => 'Approved text',
					'source_hash'     => $segment['source_hash'],
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			)
		);
		$this->assertSame( 200, $save1->get_status() );
		$saved = $save1->get_data();

		$submit = rest_do_request(
			$this->submit_review_request( (int) $post->ID, (string) $saved['segment_key'] )
		);
		$this->assertSame( 200, $submit->get_status(), wp_json_encode( $submit->get_data() ) );

		$reviewer = $this->create_reviewer();
		wp_set_current_user( $reviewer );
		$approve = rest_do_request(
			$this->review_action_request(
				(int) $post->ID,
				(string) $saved['segment_key'],
				'approve',
				array(
					'submitted_translation_hash' => (string) $submit->get_data()['submitted_translation_hash'],
				)
			)
		);
		$this->assertSame( 200, $approve->get_status(), wp_json_encode( $approve->get_data() ) );
		$this->assertSame( Store::REVIEW_APPROVED, $approve->get_data()['review_status'] );

		$store = $this->store;
		$store->update_publish_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			(string) $saved['segment_key'],
			array(
				'publish_status' => Store::PUBLISH_PUBLISHED,
				'published_at'   => current_time( 'mysql', true ),
				'published_by'   => $uid,
			)
		);

		wp_set_current_user( $uid );
		$after_approve = $this->load_first_block_segment( (int) $post->ID );
		$this->assertSame( Store::REVIEW_APPROVED, $after_approve['review_status'] );
		$this->assertSame( Store::PUBLISH_PUBLISHED, $after_approve['publish_status'] );

		$edit = rest_do_request(
			$this->workspace_save_request(
				(int) $post->ID,
				$after_approve,
				array(
					'translated_text' => 'Edited after approval',
					'source_hash'     => $after_approve['source_hash'],
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			)
		);
		$this->assertSame( 200, $edit->get_status(), wp_json_encode( $edit->get_data() ) );
		$this->assertSame( Store::REVIEW_NOT_SUBMITTED, $edit->get_data()['review_status'] );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, $edit->get_data()['publish_status'] );
	}

	public function test_noop_save_preserves_review(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$segment = $this->load_first_block_segment( (int) $post->ID );
		$save1   = rest_do_request(
			$this->workspace_save_request(
				(int) $post->ID,
				$segment,
				array(
					'translated_text' => 'Stable text',
					'source_hash'     => $segment['source_hash'],
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			)
		);
		$saved   = $save1->get_data();
		rest_do_request( $this->submit_review_request( (int) $post->ID, (string) $saved['segment_key'] ) );

		$pending = $this->load_first_block_segment( (int) $post->ID );
		$this->assertSame( Store::REVIEW_PENDING, $pending['review_status'] );

		$noop = rest_do_request(
			$this->workspace_save_request(
				(int) $post->ID,
				$pending,
				array(
					'translated_text' => 'Stable text',
					'source_hash'     => $pending['source_hash'],
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			)
		);
		$this->assertSame( 200, $noop->get_status() );
		$this->assertSame( Store::REVIEW_PENDING, $noop->get_data()['review_status'] );
	}

	public function test_round_trip_preserves_html_entities_placeholders_unicode(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$fixtures = array(
			'Plain text',
			'Unicode café — 日本語',
			'Hello {{name}} and %s',
			'<p>Bold <strong>x</strong></p>',
			'A &amp; B &lt; C',
			"Line one\nLine two\nLine three",
		);

		foreach ( $fixtures as $text ) {
			$segment  = $this->load_first_block_segment( (int) $post->ID );
			$response = rest_do_request(
				$this->workspace_save_request(
					(int) $post->ID,
					$segment,
					array(
						'translated_text' => $text,
						'source_hash'     => $segment['source_hash'],
						'status'          => Store::STATUS_MANUALLY_EDITED,
					)
				)
			);
			$this->assertSame( 200, $response->get_status(), $text );
			$this->assertSame( $text, $response->get_data()['translated_text'] );
		}
	}

	public function test_detail_exposes_translation_hash(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$segment = $this->load_first_block_segment( (int) $post->ID );
		$saved   = rest_do_request(
			$this->workspace_save_request(
				(int) $post->ID,
				$segment,
				array(
					'translated_text' => 'Detail hash',
					'source_hash'     => $segment['source_hash'],
					'status'          => Store::STATUS_MANUALLY_EDITED,
				)
			)
		)->get_data();

		global $wpdb;
		$row_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT translation_id FROM ' . $wpdb->prefix . 'aiml_translations WHERE source_id = %d AND segment_key = %s LIMIT 1',
				(int) $post->ID,
				(string) $saved['segment_key']
			)
		);
		$this->assertGreaterThan( 0, $row_id );

		$detail = rest_do_request( new WP_REST_Request( 'GET', '/aiml/v1/workspace/operations/' . $row_id ) );
		$this->assertSame( 200, $detail->get_status() );
		$data = $detail->get_data();
		$this->assertArrayHasKey( 'translation_hash', $data );
		$this->assertSame( $saved['translation_hash'], $data['translation_hash'] );
		$this->assertArrayHasKey( 'qa', $data );
		$this->assertArrayHasKey( 'assessment', $data );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load_first_block_segment( int $post_id ): array {
		$load = new WP_REST_Request( 'GET', '/aiml/v1/workspace/' . $post_id . '/segments' );
		$load->set_param( 'language', 'sv' );
		return $this->first_block_segment( rest_do_request( $load )->get_data()['segments'] );
	}
}
