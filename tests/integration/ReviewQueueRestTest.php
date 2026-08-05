<?php
/**
 * Review queue REST integration tests (R4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;

/**
 * Store-derived, paginated, filterable review queue (ADR-0015 §5, §11).
 */
final class ReviewQueueRestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_queue_route_requires_review_capability(): void {
		wp_set_current_user( $this->create_translator() );

		$response = rest_do_request( $this->review_queue_request() );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_anonymous_cannot_view_queue(): void {
		wp_set_current_user( 0 );

		$response = rest_do_request( $this->review_queue_request() );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_queue_defaults_to_pending_only(): void {
		$language = $this->add_language();
		$pending  = $this->create_reviewable_post( $language, 'Pending item', 'Väntande post' );
		$other    = $this->create_reviewable_post( $language, 'Not submitted item', 'Ej inlämnad' );
		unset( $other );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $pending, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->review_queue_request() );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 1, $data['items'] );
		$this->assertSame( $pending, (int) $data['items'][0]['post_id'] );
		$this->assertSame( Store::REVIEW_PENDING, $data['items'][0]['review_status'] );
	}

	public function test_queue_filters_by_review_status(): void {
		$language = $this->add_language();
		$approved = $this->create_reviewable_post( $language, 'Approved item', 'Godkänd post' );
		$pending  = $this->create_reviewable_post( $language, 'Still pending', 'Fortfarande väntande' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $approved, 'post_title' ) );
		rest_do_request( $this->submit_review_request( $pending, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		rest_do_request( $this->approve_review_request( $approved, 'post_title' ) );

		$response = rest_do_request( $this->review_queue_request( array( 'review_status' => Store::REVIEW_APPROVED ) ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 1, $data['items'] );
		$this->assertSame( $approved, (int) $data['items'][0]['post_id'] );
	}

	public function test_queue_filters_by_post_id(): void {
		$language = $this->add_language();
		$post_a   = $this->create_reviewable_post( $language, 'Post A', 'Inlägg A' );
		$post_b   = $this->create_reviewable_post( $language, 'Post B', 'Inlägg B' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_a, 'post_title' ) );
		rest_do_request( $this->submit_review_request( $post_b, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->review_queue_request( array( 'post_id' => $post_a ) ) );

		$data = $response->get_data();
		$this->assertCount( 1, $data['items'] );
		$this->assertSame( $post_a, (int) $data['items'][0]['post_id'] );
	}

	public function test_queue_filters_by_language(): void {
		$sv = $this->add_language( 'sv', 'sv_SE' );
		$no = $this->add_language( 'no', 'nb_NO' );

		$post_sv = $this->create_reviewable_post( $sv, 'SV post', 'SV inlägg' );
		$post_no = $this->create_reviewable_post( $no, 'NO post', 'NO innlegg' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_sv, 'post_title' ) );
		rest_do_request( $this->submit_review_request( $post_no, 'post_title', array( 'language' => 'no' ) ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->review_queue_request( array( 'language' => 'no' ) ) );

		$data = $response->get_data();
		$this->assertCount( 1, $data['items'] );
		$this->assertSame( $post_no, (int) $data['items'][0]['post_id'] );
	}

	public function test_queue_pagination_caps_per_page_at_store_maximum(): void {
		wp_set_current_user( $this->create_reviewer() );

		$response = rest_do_request( $this->review_queue_request( array( 'per_page' => 999 ) ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( Store::REVIEW_QUEUE_MAX_PER_PAGE, $data['per_page'] );
	}

	public function test_queue_paginates_stably_across_pages(): void {
		$language = $this->add_language();
		$ids      = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->create_reviewable_post( $language, "Queue item {$i}", "Kö post {$i}" );
		}

		wp_set_current_user( $this->create_translator() );
		foreach ( $ids as $post_id ) {
			rest_do_request( $this->submit_review_request( $post_id, 'post_title' ) );
		}

		wp_set_current_user( $this->create_reviewer() );

		$page_1 = rest_do_request(
			$this->review_queue_request(
				array(
					'per_page' => 2,
					'page'     => 1,
				)
			)
		)->get_data();
		$page_2 = rest_do_request(
			$this->review_queue_request(
				array(
					'per_page' => 2,
					'page'     => 2,
				)
			)
		)->get_data();

		$this->assertCount( 2, $page_1['items'] );
		$this->assertCount( 1, $page_2['items'] );
		$this->assertSame( 3, $page_1['total'] );
		$this->assertSame( 3, $page_2['total'] );

		$seen_page_1 = array_column( $page_1['items'], 'post_id' );
		$seen_page_2 = array_column( $page_2['items'], 'post_id' );
		$this->assertEmpty( array_intersect( $seen_page_1, $seen_page_2 ), 'Pages must not overlap.' );
		$this->assertSame( $ids, array_merge( $seen_page_1, $seen_page_2 ), 'Order must be stable across pages.' );
	}

	/**
	 * Creates a page with a saved (not yet submitted) title translation.
	 *
	 * @param object $language Target language row.
	 * @param string $source   Source title.
	 * @param string $target   Translated title.
	 * @return int Post id.
	 */
	private function create_reviewable_post( object $language, string $source, string $target ): int {
		$post = $this->create_page( $source );

		$this->store->save_translation(
			array(
				'source_id'       => (int) $post->ID,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => $source,
				'translated_text' => $target,
			)
		);

		return (int) $post->ID;
	}
}
