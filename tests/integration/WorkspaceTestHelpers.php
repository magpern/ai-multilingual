<?php
/**
 * Shared helpers for workspace integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Extractor;
use WP_REST_Request;

/**
 * Workspace REST test helpers.
 */
trait WorkspaceTestHelpers {

	/**
	 * Enables Strategy F extraction flags for workspace tests.
	 */
	protected function enable_strategy_f_flags(): void {
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					'block_attr_registration_enabled'  => true,
					'block_uuid_injection_enabled'     => true,
					'block_extraction_enabled'         => true,
					'block_frontend_rendering_enabled' => true,
				)
			)
		);

		Plugin::instance()->init();
		Plugin::instance()->reload_settings();
		$this->cache->flush_all();
	}

	/**
	 * @return int Editor user id with aiml_translate.
	 */
	protected function create_translator(): int {
		return (int) self::factory()->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * Creates a reviewer with `aiml_review_translations` + `edit_post` on
	 * both posts and pages, but deliberately without `aiml_translate` —
	 * able to inspect/approve/reject only, never to alter segment text.
	 *
	 * Cannot reuse the built-in `editor` role: it is one of
	 * `Plugin::CAPABLE_ROLES` and is granted `aiml_translate` on activation,
	 * which would defeat the "reviewer without translate" scenario. A
	 * dedicated role isolates the page/post edit caps from that grant.
	 *
	 * @return int Reviewer user id.
	 */
	protected function create_reviewer(): int {
		$role = get_role( 'aiml_test_reviewer' );
		if ( null === $role ) {
			add_role(
				'aiml_test_reviewer',
				'AIML Test Reviewer',
				array(
					'read'                 => true,
					'edit_posts'           => true,
					'edit_others_posts'    => true,
					'edit_published_posts' => true,
					'edit_pages'           => true,
					'edit_others_pages'    => true,
					'edit_published_pages' => true,
					\AIMultilingual\Workspace\Review\ReviewCapabilities::REVIEW_TRANSLATIONS => true,
				)
			);
			$role = get_role( 'aiml_test_reviewer' );
		}

		// `$wp_roles` is a process-lifetime singleton that WP_UnitTestCase does
		// not reset between tests, so a capability removed in-memory by a
		// different test's exercise of `ReviewCapabilities::revoke_all_roles()`
		// (which walks every existing role, including this one) stays removed
		// for the rest of the run even though the DB row rolls back. Re-assert
		// the capability here rather than only on first creation.
		if ( $role instanceof \WP_Role && ! $role->has_cap( \AIMultilingual\Workspace\Review\ReviewCapabilities::REVIEW_TRANSLATIONS ) ) {
			$role->add_cap( \AIMultilingual\Workspace\Review\ReviewCapabilities::REVIEW_TRANSLATIONS );
		}

		return (int) self::factory()->user->create( array( 'role' => 'aiml_test_reviewer' ) );
	}

	/**
	 * Creates a user with both translate and review capabilities.
	 *
	 * @return int User id.
	 */
	protected function create_translator_reviewer(): int {
		$user_id = $this->create_translator();
		$user    = new \WP_User( $user_id );
		$user->add_cap( \AIMultilingual\Workspace\Review\ReviewCapabilities::REVIEW_TRANSLATIONS );

		return $user_id;
	}

	/**
	 * @param string $uuid Block UUID.
	 * @return \WP_Post
	 */
	protected function create_block_page( string $uuid = '550e8400-e29b-41d4-a716-446655440000' ): \WP_Post {
		return $this->create_page(
			'Workspace blocks',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>Hello workspace</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuid
			)
		);
	}

	/**
	 * @return string Segment key for default paragraph fixture.
	 */
	protected function default_segment_key(): string {
		return SegmentKey::build( '550e8400-e29b-41d4-a716-446655440000', Contract::FIELD_CONTENT );
	}

	/**
	 * Returns the first block segment from a workspace segments response.
	 *
	 * @param array<int, array<string, mixed>> $segments Segment rows.
	 * @return array<string, mixed>
	 */
	protected function first_block_segment( array $segments ): array {
		foreach ( $segments as $segment ) {
			if ( str_starts_with( (string) ( $segment['segment_key'] ?? '' ), 'b:' ) ) {
				return $segment;
			}
		}

		$this->fail( 'Expected at least one block segment in the workspace response.' );
	}

	/**
	 * Builds a POST request for a single segment save.
	 *
	 * @param int                  $post_id     Post id.
	 * @param array<string, mixed> $segment     Segment row.
	 * @param array<string, mixed> $body        Request body.
	 * @return WP_REST_Request
	 */
	protected function workspace_save_request( int $post_id, array $segment, array $body ): WP_REST_Request {
		$key     = (string) $segment['segment_key'];
		$request = new WP_REST_Request(
			'POST',
			sprintf( '/aiml/v1/workspace/%d/segments/%s', $post_id, $key )
		);
		$request->set_url_params(
			array(
				'post_id'     => $post_id,
				'segment_key' => $key,
			)
		);
		$request->set_param( 'language', 'sv' );
		foreach ( $body as $key => $value ) {
			$request->set_param( (string) $key, $value );
		}

		return $request;
	}

	/**
	 * Builds an extractor with Strategy F block extraction enabled.
	 */
	protected function strategy_f_extractor(): Extractor {
		$settings = new Settings(
			array(
				'block_attr_registration_enabled'  => true,
				'block_uuid_injection_enabled'     => true,
				'block_extraction_enabled'         => true,
				'block_frontend_rendering_enabled' => true,
			)
		);
		$registry = new AdapterRegistry();

		return new Extractor(
			$settings,
			new BlockExtractor(
				$registry,
				new BlockRegistry( $registry ),
				new BlockExtractionLogger()
			)
		);
	}

	/**
	 * Builds a POST request for a batch segment save.
	 *
	 * @param int                              $post_id  Post id.
	 * @param array<int, array<string, mixed>> $items    Batch items.
	 * @param string                           $language Language code.
	 * @return WP_REST_Request
	 */
	protected function workspace_batch_save_request(
		int $post_id,
		array $items,
		string $language = 'sv'
	): WP_REST_Request {
		$request = new WP_REST_Request(
			'POST',
			sprintf( '/aiml/v1/workspace/%d/segments/batch', $post_id )
		);
		$request->set_url_params( array( 'post_id' => $post_id ) );
		$request->set_param( 'language', $language );
		$request->set_param( 'segments', $items );

		return $request;
	}

	/**
	 * Builds a POST request to submit a segment for review.
	 *
	 * @param int                  $post_id Post id.
	 * @param string               $key     Segment key.
	 * @param array<string, mixed> $body    Optional extra body params.
	 * @return WP_REST_Request
	 */
	protected function submit_review_request( int $post_id, string $key, array $body = array() ): WP_REST_Request {
		return $this->review_action_request( $post_id, $key, 'submit-review', $body );
	}

	/**
	 * Builds a POST request to approve a pending review.
	 *
	 * @param int                  $post_id Post id.
	 * @param string               $key     Segment key.
	 * @param array<string, mixed> $body    Optional extra body params.
	 * @return WP_REST_Request
	 */
	protected function approve_review_request( int $post_id, string $key, array $body = array() ): WP_REST_Request {
		return $this->review_action_request( $post_id, $key, 'approve', $body );
	}

	/**
	 * Builds a POST request to reject a pending review.
	 *
	 * @param int                  $post_id Post id.
	 * @param string               $key     Segment key.
	 * @param array<string, mixed> $body    Optional extra body params (e.g. 'reason').
	 * @return WP_REST_Request
	 */
	protected function reject_review_request( int $post_id, string $key, array $body = array() ): WP_REST_Request {
		return $this->review_action_request( $post_id, $key, 'reject', $body );
	}

	/**
	 * Shared builder for the single-segment review action routes.
	 *
	 * @param int                  $post_id Post id.
	 * @param string               $key     Segment key.
	 * @param string               $action  One of submit-review|approve|reject.
	 * @param array<string, mixed> $body    Optional extra body params.
	 * @return WP_REST_Request
	 */
	private function review_action_request( int $post_id, string $key, string $action, array $body ): WP_REST_Request {
		$request = new WP_REST_Request(
			'POST',
			sprintf( '/aiml/v1/workspace/%d/segments/%s/%s', $post_id, $key, $action )
		);
		$request->set_url_params(
			array(
				'post_id'     => $post_id,
				'segment_key' => $key,
			)
		);
		$request->set_param( 'language', 'sv' );
		foreach ( $body as $param_key => $value ) {
			$request->set_param( (string) $param_key, $value );
		}

		return $request;
	}

	/**
	 * Builds a POST request for a batch review action.
	 *
	 * @param int                              $post_id  Post id.
	 * @param string                           $action   One of submit|approve|reject.
	 * @param array<int, array<string, mixed>> $items    Per-segment payloads.
	 * @param string                           $language Language code.
	 * @return WP_REST_Request
	 */
	protected function batch_review_request(
		int $post_id,
		string $action,
		array $items,
		string $language = 'sv'
	): WP_REST_Request {
		$request = new WP_REST_Request(
			'POST',
			sprintf( '/aiml/v1/workspace/%d/segments/batch-review', $post_id )
		);
		$request->set_url_params( array( 'post_id' => $post_id ) );
		$request->set_param( 'language', $language );
		$request->set_param( 'action', $action );
		$request->set_param( 'segments', $items );

		return $request;
	}

	/**
	 * Builds a GET request for the review queue.
	 *
	 * @param array<string, mixed> $query Query params (post_id, language, review_status, page, per_page).
	 * @return WP_REST_Request
	 */
	protected function review_queue_request( array $query = array() ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/aiml/v1/workspace/review-queue' );
		foreach ( $query as $key => $value ) {
			$request->set_param( (string) $key, $value );
		}

		return $request;
	}

	/**
	 * Creates a page with two Strategy F block paragraphs.
	 *
	 * @return \WP_Post
	 */
	protected function create_two_block_page(): \WP_Post {
		return $this->create_page(
			'Two block segments',
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>First block</p><!-- /wp:paragraph -->'
				. '<!-- wp:paragraph {"%1$s":"%3$s"} --><p>Second block</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				'550e8400-e29b-41d4-a716-446655440000',
				'660e8400-e29b-41d4-a716-446655440001'
			)
		);
	}

	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- PHPUnit lifecycle hook.
	protected function tearDown(): void {
		update_option( Settings::OPTION, Settings::sanitize( Settings::defaults() ) );
		Plugin::instance()->reload_settings();
		$this->cache->flush_all();
		$this->languages->flush();

		parent::tearDown();
	}
}
