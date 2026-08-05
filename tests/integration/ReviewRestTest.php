<?php
/**
 * Review workflow REST integration tests (R4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Review\ReviewWorkflowService;
use WP_REST_Request;

/**
 * REST surface for submit/approve/reject, capability gating, and optimistic
 * concurrency (ADR-0015 §§4, 6, 11).
 */
final class ReviewRestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_review_routes_are_registered_under_aiml_v1(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/aiml/v1/workspace/review-queue', $routes );
		$this->assertArrayHasKey(
			'/aiml/v1/workspace/(?P<post_id>\d+)/segments/batch-review',
			$routes
		);
		$this->assertArrayHasKey(
			'/aiml/v1/workspace/(?P<post_id>\d+)/segments/(?P<segment_key>.+)/submit-review',
			$routes
		);
		$this->assertArrayHasKey(
			'/aiml/v1/workspace/(?P<post_id>\d+)/segments/(?P<segment_key>.+)/approve',
			$routes
		);
		$this->assertArrayHasKey(
			'/aiml/v1/workspace/(?P<post_id>\d+)/segments/(?P<segment_key>.+)/reject',
			$routes
		);

		// Backward compatibility: pre-existing routes remain untouched.
		$this->assertArrayHasKey( '/aiml/v1/workspace/posts', $routes );
		$this->assertArrayHasKey( '/aiml/v1/workspace/(?P<post_id>\d+)/segments', $routes );
		$this->assertArrayHasKey( '/aiml/v1/workspace/(?P<post_id>\d+)/segments/batch', $routes );
	}

	public function test_generic_segment_save_route_still_matches_after_new_routes(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$load = new WP_REST_Request( 'GET', '/aiml/v1/workspace/' . (int) $post->ID . '/segments' );
		$load->set_param( 'language', 'sv' );
		$segment = $this->first_block_segment( rest_do_request( $load )->get_data()['segments'] );

		$save = $this->workspace_save_request(
			(int) $post->ID,
			$segment,
			array(
				'translated_text' => 'Still works',
				'source_hash'     => $segment['source_hash'],
			)
		);

		$response = rest_do_request( $save );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Still works', $response->get_data()['translated_text'] );
	}

	public function test_submit_review_sets_pending_and_returns_viewmodel(): void {
		$post = $this->prepare_saved_segment( 'Submit me', 'Skicka mig' );

		$response = rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( Store::REVIEW_PENDING, $data['review_status'] );
		$this->assertNotEmpty( $data['submitted_translation_hash'] );
		$this->assertNotEmpty( $data['review_submitted_by'] );
		$this->assertNotEmpty( $data['review_submitted_at'] );
		$this->assertSame( 'Skicka mig', $data['translated_text'] );
	}

	public function test_approve_review_sets_approved_without_changing_content(): void {
		$post = $this->prepare_saved_segment( 'Approve me', 'Godkänn mig' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->approve_review_request( (int) $post->ID, 'post_title' ) );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( Store::REVIEW_APPROVED, $data['review_status'] );
		$this->assertNotEmpty( $data['reviewed_by'] );
		$this->assertNotEmpty( $data['reviewed_at'] );
		$this->assertSame( 'Godkänn mig', $data['translated_text'] );
	}

	public function test_reject_review_requires_a_reason(): void {
		$post = $this->prepare_saved_segment( 'Reject me', 'Avvisa mig' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->reject_review_request( (int) $post->ID, 'post_title' ) );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( ReviewWorkflowService::CODE_REASON_REQUIRED, $response->get_data()['code'] ?? '' );
	}

	public function test_reject_review_with_reason_preserves_translated_text(): void {
		$post = $this->prepare_saved_segment( 'Reject reason', 'Avvisa orsak' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->reject_review_request(
				(int) $post->ID,
				'post_title',
				array( 'reason' => 'Needs glossary terms' )
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( Store::REVIEW_REJECTED, $data['review_status'] );
		$this->assertSame( 'Needs glossary terms', $data['rejection_reason'] );
		$this->assertSame( 'Avvisa orsak', $data['translated_text'] );
	}

	public function test_reviewer_without_translate_cannot_save_segment(): void {
		$post = $this->prepare_saved_segment( 'Reviewer edit', 'Recensent redigering' );

		$load = new WP_REST_Request( 'GET', '/aiml/v1/workspace/' . (int) $post->ID . '/segments' );
		$load->set_param( 'language', 'sv' );
		wp_set_current_user( $this->create_translator() );
		$segments = rest_do_request( $load )->get_data()['segments'];
		$title    = $this->find_segment( $segments, 'post_title' );

		wp_set_current_user( $this->create_reviewer() );
		$save = $this->workspace_save_request(
			(int) $post->ID,
			$title,
			array(
				'translated_text' => 'Hacked by reviewer',
				'source_hash'     => $title['source_hash'],
			)
		);

		$response = rest_do_request( $save );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_reviewer_without_translate_cannot_submit_review(): void {
		$post = $this->prepare_saved_segment( 'No submit', 'Ingen inlämning' );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_translator_without_review_capability_cannot_approve(): void {
		$post = $this->prepare_saved_segment( 'No approve', 'Ingen godkännande' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request( $this->approve_review_request( (int) $post->ID, 'post_title' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_translator_without_review_capability_cannot_reject(): void {
		$post = $this->prepare_saved_segment( 'No reject', 'Ingen avvisning' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->reject_review_request( (int) $post->ID, 'post_title', array( 'reason' => 'Nope' ) )
		);

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_subscriber_cannot_submit_or_review(): void {
		$post       = $this->prepare_saved_segment( 'Sub blocked', 'Sub blockerad' );
		$subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $subscriber );
		$this->assertSame(
			403,
			rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) )->get_status()
		);
		$this->assertSame(
			403,
			rest_do_request( $this->approve_review_request( (int) $post->ID, 'post_title' ) )->get_status()
		);
	}

	public function test_approve_with_stale_expected_review_status_returns_409_with_refreshed_segment(): void {
		$post = $this->prepare_saved_segment( 'Stale approve', 'Gammal godkännande' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->approve_review_request(
				(int) $post->ID,
				'post_title',
				array( 'expected_review_status' => Store::REVIEW_APPROVED )
			)
		);

		$this->assertSame( 409, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( ReviewWorkflowService::CODE_CONFLICT, $data['code'] ?? '' );
		$this->assertArrayHasKey( 'review_status', $data['context'] ?? array() );
		$this->assertSame( Store::REVIEW_PENDING, $data['context']['review_status'] );
	}

	public function test_approve_with_stale_submitted_hash_returns_409(): void {
		$post = $this->prepare_saved_segment( 'Stale hash', 'Gammal hash' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->approve_review_request(
				(int) $post->ID,
				'post_title',
				array( 'submitted_translation_hash' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef' )
			)
		);

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( ReviewWorkflowService::CODE_CONFLICT, $response->get_data()['code'] ?? '' );
	}

	public function test_approve_blocked_by_qa_errors_returns_422(): void {
		$post = $this->prepare_saved_segment( 'Hello {name}', 'Hej utan placeholder' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->approve_review_request( (int) $post->ID, 'post_title' ) );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'aiml_qa_blocked', $response->get_data()['code'] ?? '' );
	}

	/**
	 * Creates a post with a saved title translation and returns it.
	 *
	 * @param string $source Source title.
	 * @param string $target Translated title.
	 */
	private function prepare_saved_segment( string $source, string $target ): \WP_Post {
		$this->add_language();
		$post = $this->create_page( $source );

		$this->store->save_translation(
			array(
				'source_id'       => (int) $post->ID,
				'language_id'     => (int) $this->languages->find_by_code( 'sv' )->language_id,
				'field_key'       => 'post_title',
				'source_text'     => $source,
				'translated_text' => $target,
			)
		);

		wp_set_current_user( $this->create_translator() );

		return $post;
	}

	/**
	 * Finds a segment row by key.
	 *
	 * @param array<int, array<string, mixed>> $segments Segment rows.
	 * @param string                           $key      Segment key.
	 * @return array<string, mixed>
	 */
	private function find_segment( array $segments, string $key ): array {
		foreach ( $segments as $segment ) {
			if ( (string) ( $segment['segment_key'] ?? '' ) === $key ) {
				return $segment;
			}
		}

		$this->fail( "Segment {$key} not found in response." );
	}
}
