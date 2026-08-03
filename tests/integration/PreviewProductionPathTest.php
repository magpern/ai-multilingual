<?php
/**
 * Preview URL production path tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use WP_REST_Request;

/**
 * PreviewService returns public URLs only (no admin HTML assembly).
 */
final class PreviewProductionPathTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
	}

	public function test_preview_url_returns_public_route(): void {
		$this->add_language();
		$post = $this->create_block_page();
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request(
			'GET',
			'/aiml/v1/workspace/' . (int) $post->ID . '/preview-url'
		);
		$request->set_param( 'language', 'sv' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$url = (string) $response->get_data()['url'];
		$this->assertStringContainsString( '/sv/', $url );
		$this->assertStringNotContainsString( 'wp-admin', $url );
		$this->assertStringNotContainsString( '<p>', $url );
	}
}
