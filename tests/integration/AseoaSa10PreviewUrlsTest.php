<?php
/**
 * A.SEOa SA10 — preview URL contracts (ADR-0008).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;
use AIMultilingual\Workspace\PreviewService;
use WP_REST_Request;

/**
 * Characterizes Supported SA10: capability-gated preview URLs on source permalinks.
 */
final class AseoaSa10PreviewUrlsTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->set_permalink_structure( '/%postname%/' );
	}

	protected function tearDown(): void {
		$this->set_permalink_structure( '' );
		parent::tearDown();
	}

	public function test_authorized_translator_gets_prefixed_preview_url(): void {
		$this->add_language();
		$post = $this->create_page( 'About Us' );
		wp_set_current_user( $this->create_translator() );

		$service = new PreviewService(
			$this->languages,
			$this->context,
			$this->make_router()
		);

		$url = $service->preview_url( $post, 'sv' );
		$this->assertIsString( $url );
		$this->assertStringContainsString( '/sv/', $url );
		$this->assertStringContainsString( '/' . $post->post_name . '/', $url );
		$this->assertStringNotContainsString( 'wp-admin', $url );
	}

	public function test_default_language_preview_url_is_unprefixed(): void {
		$this->add_language();
		$post    = $this->create_page( 'About Us' );
		$service = new PreviewService(
			$this->languages,
			$this->context,
			$this->make_router()
		);

		$url = $service->preview_url( $post, 'en' );
		$this->assertIsString( $url );
		$this->assertStringNotContainsString( '/sv/', $url );
		$this->assertStringContainsString( '/' . $post->post_name . '/', $url );
	}

	public function test_unknown_language_returns_error(): void {
		$this->add_language();
		$post    = $this->create_page();
		$service = new PreviewService(
			$this->languages,
			$this->context,
			$this->make_router()
		);

		$result = $service->preview_url( $post, 'xx' );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_preview_language_not_public_for_anonymous(): void {
		$this->add_language( 'de', 'de_DE', Languages::STATUS_PREVIEW );
		$post = $this->create_page();

		wp_set_current_user( 0 );
		$router = $this->route( '/de/' . $post->post_name . '/' );

		$this->assertFalse( $router->is_prefixed() );
		$this->assertTrue( $this->context->is_default() );

		$this->go_to( '/de/' . $post->post_name . '/' );
		$this->assertTrue( is_404() );
	}

	public function test_preview_language_routable_for_capability_holder(): void {
		$this->add_language( 'de', 'de_DE', Languages::STATUS_PREVIEW );
		$post = $this->create_page();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->assertTrue( user_can( $user_id, Plugin::CAPABILITY ) );

		$router = $this->route( '/de/' . $post->post_name . '/' );
		$this->assertTrue( $router->is_prefixed() );
		$this->assertSame( 'de', $this->context->current()->code );
	}

	public function test_published_language_unaffected_for_anonymous(): void {
		$this->add_language();
		$post = $this->create_page();

		wp_set_current_user( 0 );
		$router = $this->route( '/sv/' . $post->post_name . '/' );
		$this->assertTrue( $router->is_prefixed() );
		$this->assertSame( 'sv', $this->context->current()->code );
	}

	public function test_rest_preview_url_requires_auth_and_returns_public_route(): void {
		$this->add_language();
		$post = $this->create_page();

		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', '/aiml/v1/workspace/' . (int) $post->ID . '/preview-url' );
		$request->set_param( 'language', 'sv' );
		$anon = rest_do_request( $request );
		$this->assertNotSame( 200, $anon->get_status() );

		wp_set_current_user( $this->create_translator() );
		$ok = rest_do_request( $request );
		$this->assertSame( 200, $ok->get_status() );
		$url = (string) $ok->get_data()['url'];
		$this->assertStringContainsString( '/sv/', $url );
		$this->assertStringNotContainsString( 'wp-admin', $url );
	}

	public function test_preview_url_does_not_leak_translated_context_after_call(): void {
		$this->add_language();
		$post    = $this->create_page();
		$router  = $this->make_router();
		$service = new PreviewService( $this->languages, $this->context, $router );

		$before_default = $this->context->is_default();
		$service->preview_url( $post, 'sv' );
		$this->assertSame( $before_default, $this->context->is_default() );
		$this->assertFalse( $this->context->is_translated() );
	}
}
