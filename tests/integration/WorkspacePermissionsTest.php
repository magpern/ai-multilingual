<?php
/**
 * Workspace permission tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use WP_REST_Request;

/**
 * REST authorization for workspace routes.
 */
final class WorkspacePermissionsTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_anonymous_user_cannot_list_posts(): void {
		wp_set_current_user( 0 );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/aiml/v1/workspace/posts' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_subscriber_cannot_access_workspace(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/aiml/v1/workspace/posts' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_editor_without_edit_post_gets_forbidden_for_foreign_post(): void {
		$this->add_language();
		$owner_id = $this->create_translator();
		wp_set_current_user( $owner_id );
		$post = $this->create_block_page();

		$other_id = (int) self::factory()->user->create( array( 'role' => 'author' ) );
		$other    = new \WP_User( $other_id );
		$other->add_cap( \AIMultilingual\Plugin::CAPABILITY );
		wp_set_current_user( $other_id );

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/segments'
		);
		$request->set_param( 'language', 'sv' );

		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}
}
