<?php
/**
 * Review Workflow diagnostics integration tests (R7 / ADR-0015 §13).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Query-time Store diagnostics, cross-request counters, and the
 * `/workspace/review-diagnostics` REST surface.
 */
final class ReviewDiagnosticsTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
		$this->reset_review_diagnostics_counters();
	}

	public function test_review_status_counts_reflects_current_rows_only(): void {
		$language = $this->add_language();
		$pending  = $this->create_reviewable_post( $language, 'Pending', 'Väntande' );
		$approved = $this->create_reviewable_post( $language, 'Approved', 'Godkänd' );
		$rejected = $this->create_reviewable_post( $language, 'Rejected', 'Avvisad' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $pending, 'post_title' ) );
		rest_do_request( $this->submit_review_request( $approved, 'post_title' ) );
		rest_do_request( $this->submit_review_request( $rejected, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		rest_do_request( $this->approve_review_request( $approved, 'post_title' ) );
		rest_do_request( $this->reject_review_request( $rejected, 'post_title', array( 'reason' => 'Fix it' ) ) );

		$counts = $this->store->review_status_counts();

		$this->assertSame( 1, $counts[ Store::REVIEW_PENDING ] );
		$this->assertSame( 1, $counts[ Store::REVIEW_APPROVED ] );
		$this->assertSame( 1, $counts[ Store::REVIEW_REJECTED ] );
		$this->assertArrayHasKey( Store::REVIEW_NOT_SUBMITTED, $counts );
	}

	public function test_review_status_counts_scoped_by_post_and_language(): void {
		$sv      = $this->add_language( 'sv', 'sv_SE' );
		$no      = $this->add_language( 'no', 'nb_NO' );
		$post_sv = $this->create_reviewable_post( $sv, 'SV post', 'SV inlägg' );
		$post_no = $this->create_reviewable_post( $no, 'NO post', 'NO innlegg' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_sv, 'post_title' ) );
		rest_do_request( $this->submit_review_request( $post_no, 'post_title', array( 'language' => 'no' ) ) );

		$scoped_to_sv = $this->store->review_status_counts( Store::SOURCE_POST, 0, (int) $sv->language_id );
		$this->assertSame( 1, $scoped_to_sv[ Store::REVIEW_PENDING ] );

		$scoped_to_post = $this->store->review_status_counts( Store::SOURCE_POST, $post_no );
		$this->assertSame( 1, $scoped_to_post[ Store::REVIEW_PENDING ] );
	}

	public function test_review_pending_age_stats_are_bounded_and_non_negative(): void {
		$language = $this->add_language();
		$post     = $this->create_reviewable_post( $language, 'Aging', 'Åldras' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post, 'post_title' ) );

		$stats = $this->store->review_pending_age_stats();

		$this->assertSame( 1, $stats['count'] );
		$this->assertGreaterThanOrEqual( 0, $stats['avg_seconds'] );
		$this->assertLessThanOrEqual( Store::REVIEW_PENDING_AGE_BOUND_SECONDS, $stats['avg_seconds'] );
		$this->assertLessThanOrEqual( Store::REVIEW_PENDING_AGE_BOUND_SECONDS, $stats['max_seconds'] );
	}

	public function test_review_pending_age_stats_empty_when_nothing_pending(): void {
		$this->add_language();

		$stats = $this->store->review_pending_age_stats();

		$this->assertSame(
			array(
				'count'       => 0,
				'avg_seconds' => 0,
				'max_seconds' => 0,
			),
			$stats
		);
	}

	public function test_approve_conflict_increments_conflicts_and_approval_failures(): void {
		$language = $this->add_language();
		$post     = $this->create_reviewable_post( $language, 'Stale approve', 'Gammal godkännande' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->approve_review_request(
				$post,
				'post_title',
				array( 'expected_review_status' => Store::REVIEW_APPROVED )
			)
		);

		$this->assertSame( 409, $response->get_status() );

		$counters = $this->review_diagnostics_counters();
		$this->assertSame( 1, $counters['conflicts'] );
		$this->assertSame( 1, $counters['approval_failures'] );
		$this->assertSame( 0, $counters['qa_blocked_approvals'] );
	}

	public function test_reject_conflict_increments_conflicts_but_not_approval_failures(): void {
		$language = $this->add_language();
		$post     = $this->create_reviewable_post( $language, 'Stale reject', 'Gammal avvisning' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->reject_review_request(
				$post,
				'post_title',
				array(
					'reason'                 => 'Stale',
					'expected_review_status' => Store::REVIEW_APPROVED,
				)
			)
		);

		$this->assertSame( 409, $response->get_status() );

		$counters = $this->review_diagnostics_counters();
		$this->assertSame( 1, $counters['conflicts'] );
		$this->assertSame( 0, $counters['approval_failures'] );
	}

	public function test_qa_blocked_approval_increments_qa_blocked_only(): void {
		$language = $this->add_language();
		$post     = $this->create_reviewable_post( $language, 'Hello {name}', 'Hej utan placeholder' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->approve_review_request( $post, 'post_title' ) );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'aiml_qa_blocked', $response->get_data()['code'] ?? '' );

		$counters = $this->review_diagnostics_counters();
		$this->assertSame( 1, $counters['qa_blocked_approvals'] );
		$this->assertSame( 0, $counters['approval_failures'] );
		$this->assertSame( 0, $counters['conflicts'] );
	}

	public function test_successful_approve_increments_tm_write_back_success(): void {
		$language = $this->add_language();
		$post     = $this->create_reviewable_post( $language, 'Write back me', 'Skriv tillbaka mig' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->approve_review_request( $post, 'post_title' ) );
		$this->assertSame( 200, $response->get_status() );

		$counters = $this->review_diagnostics_counters();
		$this->assertSame( 1, $counters['tm_write_back_success'] );
		$this->assertSame( 0, $counters['tm_write_back_failure'] );
	}

	public function test_duplicate_approve_does_not_increment_tm_write_back_again(): void {
		$language = $this->add_language();
		$post     = $this->create_reviewable_post( $language, 'Idempotent', 'Idempotent sv' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		rest_do_request( $this->approve_review_request( $post, 'post_title' ) );
		rest_do_request( $this->approve_review_request( $post, 'post_title' ) );

		$counters = $this->review_diagnostics_counters();
		$this->assertSame( 1, $counters['tm_write_back_success'] );
	}

	public function test_review_diagnostics_route_requires_review_capability(): void {
		wp_set_current_user( $this->create_translator() );

		$response = rest_do_request( $this->review_diagnostics_request() );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_review_diagnostics_route_returns_combined_shape(): void {
		$language = $this->add_language();
		$post     = $this->create_reviewable_post( $language, 'Combined', 'Kombinerad' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->review_diagnostics_request() );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertArrayHasKey( 'review_status_counts', $data );
		$this->assertArrayHasKey( 'pending_age', $data );
		$this->assertArrayHasKey( 'counters', $data );
		$this->assertSame( 1, $data['review_status_counts'][ Store::REVIEW_PENDING ] );
		$this->assertSame( 1, $data['pending_age']['count'] );

		foreach ( array( 'conflicts', 'approval_failures', 'qa_blocked_approvals', 'tm_write_back_success', 'tm_write_back_failure' ) as $key ) {
			$this->assertArrayHasKey( $key, $data['counters'] );
		}
	}

	public function test_review_diagnostics_route_scopes_by_post_id_query_param(): void {
		$language = $this->add_language();
		$post_a   = $this->create_reviewable_post( $language, 'Post A', 'Inlägg A' );
		$post_b   = $this->create_reviewable_post( $language, 'Post B', 'Inlägg B' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_a, 'post_title' ) );
		rest_do_request( $this->submit_review_request( $post_b, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->review_diagnostics_request( array( 'post_id' => $post_a ) ) );

		$data = $response->get_data();
		$this->assertSame( 1, $data['review_status_counts'][ Store::REVIEW_PENDING ] );
		$this->assertSame( 1, $data['pending_age']['count'] );
	}

	/**
	 * Builds a GET request for the review diagnostics endpoint.
	 *
	 * @param array<string, mixed> $query Optional query params (post_id, language).
	 */
	private function review_diagnostics_request( array $query = array() ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/aiml/v1/workspace/review-diagnostics' );
		foreach ( $query as $key => $value ) {
			$request->set_param( (string) $key, $value );
		}

		return $request;
	}

	/**
	 * Fully resets the persisted diagnostics counters option between tests,
	 * since it is a WordPress option and not part of the DB rows the base
	 * test case rolls back per-test.
	 */
	private function reset_review_diagnostics_counters(): void {
		( new \AIMultilingual\Workspace\Review\ReviewDiagnosticsCounters() )->reset();
	}

	/**
	 * Reads live counters through the REST surface as a reviewer, preserving
	 * whatever user was previously set as the current user.
	 *
	 * @return array<string, int>
	 */
	private function review_diagnostics_counters(): array {
		$previous_user = get_current_user_id();

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->review_diagnostics_request() );
		wp_set_current_user( $previous_user );

		return $response->get_data()['counters'];
	}

	/**
	 * Creates a post with a saved title translation and returns its id.
	 *
	 * @param object $language Target language row.
	 * @param string $source   Source title.
	 * @param string $target   Translated title.
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
