<?php
/**
 * OTL.1 attention REST / auth parity integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Attention filter, counts, and list/count visibility parity.
 */
final class OperationsAttentionRestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_list_includes_attention_reasons_without_heavy_payloads(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Attention row', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Uppmärksamhet' );

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request( $this->operations_request( array( 'language' => 'sv' ) ) );
		$this->assertSame( 200, $response->get_status() );
		$item = $response->get_data()['items'][0];
		$this->assertArrayHasKey( 'attention_reasons', $item );
		$this->assertContains( 'unpublished', $item['attention_reasons'] );
		$this->assertNotContains( 'needs_review', $item['attention_reasons'] );
		$this->assertArrayNotHasKey( 'assessment', $item );
		$this->assertArrayNotHasKey( 'qa', $item );
		$this->assertArrayNotHasKey( 'publication', $item );
	}

	public function test_attention_filter_review_pending(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Pending', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Väntar' );
		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );
		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			'post_title',
			array( 'review_status' => Store::REVIEW_PENDING )
		);

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->operations_request(
				array(
					'language'  => 'sv',
					'attention' => 'review_pending',
				)
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$ids = array_column( $response->get_data()['items'], 'translation_id' );
		$this->assertContains( (int) $row->translation_id, $ids );
		foreach ( $response->get_data()['items'] as $item ) {
			$this->assertContains( 'review_pending', $item['attention_reasons'] );
			$this->assertSame( Store::REVIEW_PENDING, $item['review_status'] );
		}
	}

	public function test_needs_review_attention_rejected(): void {
		$this->add_language();
		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->operations_request(
				array(
					'language'  => 'sv',
					'attention' => 'needs_review',
				)
			)
		);
		$this->assertSame( 422, $response->get_status() );
	}

	public function test_attention_and_contradictory_axis_returns_empty(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Conflict', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Konflikt' );
		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );
		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			'post_title',
			array( 'review_status' => Store::REVIEW_PENDING )
		);

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->operations_request(
				array(
					'language'      => 'sv',
					'attention'     => 'review_pending',
					'review_status' => Store::REVIEW_REJECTED,
				)
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 0, (int) $response->get_data()['total'] );
		$this->assertSame( array(), $response->get_data()['items'] );
	}

	public function test_attention_counts_require_language_and_auth(): void {
		wp_set_current_user( 0 );
		$denied = rest_do_request( $this->counts_request( array( 'language' => 'sv' ) ) );
		$this->assertSame( 403, $denied->get_status() );

		wp_set_current_user( $this->create_translator() );
		$missing = rest_do_request( $this->counts_request( array() ) );
		$this->assertSame( 422, $missing->get_status() );
	}

	public function test_attention_counts_parity_with_list_totals(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Counts', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Räkna' );
		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );
		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			'post_title',
			array( 'review_status' => Store::REVIEW_PENDING )
		);
		$this->assertTrue(
			$this->store->save_translation(
				array(
					'source_type'     => Store::SOURCE_POST,
					'source_id'       => (int) $post->ID,
					'source_subtype'  => 'page',
					'language_id'     => (int) $language->language_id,
					'field_key'       => 'post_content',
					'segment_key'     => 'failed-seg',
					'segment_order'   => 2,
					'text_format'     => Store::FORMAT_PLAIN,
					'source_text'     => 'Fail',
					'translated_text' => 'Miss',
					'status'          => Store::STATUS_FAILED,
				)
			)
		);

		wp_set_current_user( $this->create_translator() );
		$counts = rest_do_request( $this->counts_request( array( 'language' => 'sv' ) ) )->get_data();
		$list   = rest_do_request( $this->operations_request( array( 'language' => 'sv' ) ) )->get_data();

		$this->assertSame( (int) $list['total'], (int) $counts['total'] );
		$this->assertArrayHasKey( 'stale', $counts );
		$this->assertArrayHasKey( 'review_pending', $counts );
		$this->assertArrayHasKey( 'review_rejected', $counts );
		$this->assertArrayHasKey( 'unpublished', $counts );
		$this->assertArrayHasKey( 'translation_failed', $counts );

		$pending_list = rest_do_request(
			$this->operations_request(
				array(
					'language'  => 'sv',
					'attention' => 'review_pending',
				)
			)
		)->get_data();
		$this->assertSame( (int) $pending_list['total'], (int) $counts['review_pending'] );

		$failed_list = rest_do_request(
			$this->operations_request(
				array(
					'language'  => 'sv',
					'attention' => 'translation_failed',
				)
			)
		)->get_data();
		$this->assertSame( (int) $failed_list['total'], (int) $counts['translation_failed'] );

		$sum_buckets = (int) $counts['stale']
			+ (int) $counts['review_pending']
			+ (int) $counts['review_rejected']
			+ (int) $counts['unpublished']
			+ (int) $counts['translation_failed'];
		// Overlap is intentional — bucket sum is not the inventory total.
		$this->assertGreaterThan( (int) $counts['total'], $sum_buckets );
	}

	public function test_subscriber_cannot_see_counts(): void {
		$this->add_language();
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$response = rest_do_request( $this->counts_request( array( 'language' => 'sv' ) ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_reviewer_can_access_counts(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Reviewer counts', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Granskare' );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->counts_request( array( 'language' => 'sv' ) ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThanOrEqual( 1, (int) $response->get_data()['total'] );
	}

	/**
	 * @param array<string, mixed> $params Query params.
	 */
	private function operations_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/aiml/v1/workspace/operations' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	/**
	 * @param array<string, mixed> $params Query params.
	 */
	private function counts_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/aiml/v1/workspace/operations/attention-counts' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}
}
