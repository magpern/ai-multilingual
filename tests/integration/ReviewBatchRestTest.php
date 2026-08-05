<?php
/**
 * Review batch REST integration tests (R4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Review\ReviewBatchCoordinator;

/**
 * Bounded, per-item, partial-success batch review actions (ADR-0015 §11.1).
 */
final class ReviewBatchRestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_batch_submit_all_succeed(): void {
		$this->add_language();
		$post = $this->create_two_block_page();
		$keys = $this->block_segment_keys();

		wp_set_current_user( $this->create_translator() );
		$this->translate_block_segments( $post, $keys );

		$response = rest_do_request(
			$this->batch_review_request(
				(int) $post->ID,
				ReviewBatchCoordinator::ACTION_SUBMIT,
				array_map(
					static fn( string $key ): array => array( 'segment_key' => $key ),
					$keys
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'completed', $data['status'] );
		$this->assertCount( 2, $data['segments'] );
		$this->assertCount( 0, $data['errors'] );
		foreach ( $data['segments'] as $segment ) {
			$this->assertSame( Store::REVIEW_PENDING, $segment['review_status'] );
		}
	}

	public function test_batch_approve_partial_success_when_one_segment_not_pending(): void {
		$this->add_language();
		$post = $this->create_two_block_page();
		$keys = $this->block_segment_keys();

		wp_set_current_user( $this->create_translator() );
		$this->translate_block_segments( $post, $keys );
		// Only submit the first segment; the second stays not_submitted.
		rest_do_request( $this->submit_review_request( (int) $post->ID, $keys[0] ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->batch_review_request(
				(int) $post->ID,
				ReviewBatchCoordinator::ACTION_APPROVE,
				array(
					array( 'segment_key' => $keys[0] ),
					array( 'segment_key' => $keys[1] ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'partial', $data['status'] );
		$this->assertCount( 1, $data['segments'] );
		$this->assertSame( Store::REVIEW_APPROVED, $data['segments'][0]['review_status'] );
		$this->assertCount( 1, $data['errors'] );
		$this->assertSame( $keys[1], $data['errors'][0]['segment_key'] );
		$this->assertNotEmpty( $data['errors'][0]['code'] );
	}

	public function test_batch_reject_uses_shared_reason_when_item_omits_one(): void {
		$this->add_language();
		$post = $this->create_two_block_page();
		$keys = $this->block_segment_keys();

		wp_set_current_user( $this->create_translator() );
		$this->translate_block_segments( $post, $keys );
		rest_do_request( $this->submit_review_request( (int) $post->ID, $keys[0] ) );
		rest_do_request( $this->submit_review_request( (int) $post->ID, $keys[1] ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->batch_review_request_with_reason(
				(int) $post->ID,
				array(
					array( 'segment_key' => $keys[0] ),
					array( 'segment_key' => $keys[1] ),
				),
				'Shared rejection reason'
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'completed', $data['status'] );
		$this->assertCount( 2, $data['segments'] );
		foreach ( $data['segments'] as $segment ) {
			$this->assertSame( Store::REVIEW_REJECTED, $segment['review_status'] );
			$this->assertSame( 'Shared rejection reason', $segment['rejection_reason'] );
		}
	}

	public function test_batch_exceeding_limit_is_rejected_with_422(): void {
		$this->add_language();
		$post = $this->create_two_block_page();

		$items = array();
		for ( $i = 0; $i <= ReviewBatchCoordinator::BATCH_LIMIT; $i++ ) {
			$items[] = array( 'segment_key' => "b:filler-{$i}:content" );
		}

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->batch_review_request( (int) $post->ID, ReviewBatchCoordinator::ACTION_SUBMIT, $items )
		);

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'aiml_batch_too_large', $response->get_data()['code'] ?? '' );
	}

	public function test_batch_missing_segment_key_reports_explicit_error_not_silent_skip(): void {
		$this->add_language();
		$post = $this->create_two_block_page();

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->batch_review_request(
				(int) $post->ID,
				ReviewBatchCoordinator::ACTION_SUBMIT,
				array( array( 'translated_text' => 'no segment_key here' ) )
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'failed', $data['status'] );
		$this->assertCount( 0, $data['segments'] );
		$this->assertCount( 1, $data['errors'] );
		$this->assertSame( 'aiml_invalid_segment', $data['errors'][0]['code'] );
	}

	public function test_batch_submit_requires_translate_capability(): void {
		$this->add_language();
		$post = $this->create_two_block_page();
		$keys = $this->block_segment_keys();

		wp_set_current_user( $this->create_translator() );
		$this->translate_block_segments( $post, $keys );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->batch_review_request(
				(int) $post->ID,
				ReviewBatchCoordinator::ACTION_SUBMIT,
				array( array( 'segment_key' => $keys[0] ) )
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_batch_approve_requires_review_capability(): void {
		$this->add_language();
		$post = $this->create_two_block_page();
		$keys = $this->block_segment_keys();

		wp_set_current_user( $this->create_translator() );
		$this->translate_block_segments( $post, $keys );
		rest_do_request( $this->submit_review_request( (int) $post->ID, $keys[0] ) );

		$response = rest_do_request(
			$this->batch_review_request(
				(int) $post->ID,
				ReviewBatchCoordinator::ACTION_APPROVE,
				array( array( 'segment_key' => $keys[0] ) )
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_batch_unknown_action_returns_422(): void {
		$this->add_language();
		$post = $this->create_two_block_page();

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->batch_review_request( (int) $post->ID, 'bogus-action', array() )
		);

		$this->assertSame( 422, $response->get_status() );
	}

	/**
	 * Builds a batch-review request carrying a shared rejection reason.
	 *
	 * @param int                              $post_id Post id.
	 * @param array<int, array<string, mixed>> $items   Per-segment payloads.
	 * @param string                           $reason  Shared rejection reason.
	 */
	private function batch_review_request_with_reason( int $post_id, array $items, string $reason ): \WP_REST_Request {
		$request = $this->batch_review_request( $post_id, ReviewBatchCoordinator::ACTION_REJECT, $items );
		$request->set_param( 'reason', $reason );

		return $request;
	}

	/**
	 * Saves a manual translation for each given block segment key.
	 *
	 * Segments only exist in the Store once something has translated them;
	 * a freshly created block page has no Store rows yet, so review actions
	 * on its segment keys would otherwise fail with "no translation".
	 *
	 * @param \WP_Post           $post Canonical post.
	 * @param array<int, string> $keys Segment keys to translate.
	 */
	private function translate_block_segments( \WP_Post $post, array $keys ): void {
		$load = new \WP_REST_Request( 'GET', '/aiml/v1/workspace/' . (int) $post->ID . '/segments' );
		$load->set_param( 'language', 'sv' );
		$segments = rest_do_request( $load )->get_data()['segments'];

		$by_key = array();
		foreach ( $segments as $row ) {
			$by_key[ (string) ( $row['segment_key'] ?? '' ) ] = $row;
		}

		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $by_key, "Segment {$key} not found in workspace response." );
			$segment = $by_key[ $key ];

			$save     = $this->workspace_save_request(
				(int) $post->ID,
				$segment,
				array(
					'translated_text' => 'Translated ' . $key,
					'source_hash'     => $segment['source_hash'],
				)
			);
			$response = rest_do_request( $save );
			$this->assertSame( 200, $response->get_status(), "Failed to save segment {$key}." );
		}
	}

	/**
	 * Returns the two fixed block segment keys used by `create_two_block_page()`.
	 *
	 * Built directly from the known UUIDs rather than via a REST round trip,
	 * so callers can compute keys before deciding which user to authenticate
	 * as for the actual request under test.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function block_segment_keys(): array {
		return array(
			SegmentKey::build( '550e8400-e29b-41d4-a716-446655440000', Contract::FIELD_CONTENT ),
			SegmentKey::build( '660e8400-e29b-41d4-a716-446655440001', Contract::FIELD_CONTENT ),
		);
	}
}
