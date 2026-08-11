<?php
/**
 * OTL.0 operations REST integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Additive GET /workspace/operations and /operations/{id}.
 */
final class OperationsRestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_operations_requires_auth(): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( $this->operations_request( array( 'language' => 'sv' ) ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_operations_requires_language(): void {
		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request( $this->operations_request( array() ) );
		$this->assertSame( 422, $response->get_status() );
	}

	public function test_list_returns_axes_without_assessment_or_publication(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Hello', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Hej' );

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request( $this->operations_request( array( 'language' => 'sv' ) ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '1', $response->get_headers()['X-AIML-Workspace-Api-Version'] ?? null );

		$data = $response->get_data();
		$this->assertGreaterThanOrEqual( 1, (int) $data['total'] );
		$this->assertNotEmpty( $data['items'] );
		$item = $data['items'][0];
		$this->assertArrayHasKey( 'translation_id', $item );
		$this->assertArrayHasKey( 'publish_status', $item );
		$this->assertArrayHasKey( 'is_stale', $item );
		$this->assertArrayHasKey( 'source_preview', $item );
		$this->assertArrayHasKey( 'allowed_actions', $item );
		$this->assertArrayNotHasKey( 'assessment', $item );
		$this->assertArrayNotHasKey( 'qa', $item );
		$this->assertArrayNotHasKey( 'publication', $item );
		$this->assertNull( $item['jobs'] );

		$publish = null;
		foreach ( $item['allowed_actions'] as $action ) {
			if ( 'publish' === $action['id'] ) {
				$publish = $action;
			}
		}
		$this->assertNotNull( $publish );
		$this->assertFalse( $publish['allowed'] );
	}

	public function test_list_filters_by_review_status(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Filter me', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Filtrera' );

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->operations_request(
				array(
					'language'      => 'sv',
					'review_status' => Store::REVIEW_NOT_SUBMITTED,
				)
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$ids = array_column( $response->get_data()['items'], 'translation_id' );
		$this->assertContains( (int) $row->translation_id, $ids );
	}

	public function test_list_pagination_bounds_per_page(): void {
		$language = $this->add_language();
		for ( $i = 0; $i < 3; $i++ ) {
			$post = $this->create_page( "Page $i", '<p>Body</p>' );
			$this->translate( $post, $language, 'post_title', "Titel $i" );
		}

		wp_set_current_user( $this->create_translator() );
		$response = rest_do_request(
			$this->operations_request(
				array(
					'language' => 'sv',
					'per_page' => 100,
					'page'     => 1,
				)
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 50, (int) $data['per_page'] );
		$this->assertLessThanOrEqual( 50, count( $data['items'] ) );
	}

	public function test_detail_includes_assessment_and_publication(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Detail', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Detalj' );
		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );

		wp_set_current_user( $this->create_translator() );
		$request  = new WP_REST_Request( 'GET', '/aiml/v1/workspace/operations/' . (int) $row->translation_id );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( (int) $row->translation_id, (int) $data['translation_id'] );
		$this->assertArrayHasKey( 'assessment', $data );
		$this->assertArrayHasKey( 'qa', $data );
		$this->assertArrayHasKey( 'publication', $data );
		$this->assertArrayHasKey( 'source_text', $data );
		$this->assertSame( 'Detalj', $data['translated_text'] );
		$this->assertIsArray( $data['jobs'] );
		$this->assertNull( $data['jobs']['association'] );
		$this->assertTrue( $data['jobs']['lookup']['bounded'] );
		$this->assertFalse( $data['jobs']['lookup']['matched'] );
		$this->assertArrayNotHasKey( 'selection_rule', $data['jobs'] );

		$publish = null;
		foreach ( $data['allowed_actions'] as $action ) {
			if ( 'publish' === $action['id'] ) {
				$publish = $action;
			}
		}
		$this->assertNotNull( $publish );
		// May be true or false depending on TI.7 — must not be DETAIL_ONLY.
		$this->assertNotSame( 'detail_only', $publish['reason_code'] );
	}

	public function test_detail_missing_returns_404(): void {
		wp_set_current_user( $this->create_translator() );
		$request  = new WP_REST_Request( 'GET', '/aiml/v1/workspace/operations/99999999' );
		$response = rest_do_request( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_reviewer_can_list_operations(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Reviewer list', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Recensent' );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->operations_request( array( 'language' => 'sv' ) ) );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_by_translation_id_roundtrip(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'PK', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Primär' );
		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );
		$found = $this->store->get_by_translation_id( (int) $row->translation_id );
		$this->assertNotNull( $found );
		$this->assertSame( (int) $row->translation_id, (int) $found->translation_id );
	}

	public function test_subscriber_cannot_access_operations(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Denied', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Nekad' );

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$response = rest_do_request( $this->operations_request( array( 'language' => 'sv' ) ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_language_isolation(): void {
		$sv   = $this->add_language( 'sv', 'sv_SE' );
		$de   = $this->add_language( 'de', 'de_DE' );
		$post = $this->create_page( 'Iso', '<p>Body</p>' );
		$this->translate( $post, $sv, 'post_title', 'Svenska' );
		$this->translate( $post, $de, 'post_title', 'Deutsch' );

		wp_set_current_user( $this->create_translator() );
		$sv_resp = rest_do_request( $this->operations_request( array( 'language' => 'sv' ) ) );
		$de_resp = rest_do_request( $this->operations_request( array( 'language' => 'de' ) ) );
		$this->assertSame( 200, $sv_resp->get_status() );
		$this->assertSame( 200, $de_resp->get_status() );
		foreach ( $sv_resp->get_data()['items'] as $item ) {
			$this->assertSame( 'sv', $item['language_code'] );
		}
		foreach ( $de_resp->get_data()['items'] as $item ) {
			$this->assertSame( 'de', $item['language_code'] );
		}
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
}
