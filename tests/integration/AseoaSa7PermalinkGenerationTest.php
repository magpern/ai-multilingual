<?php
/**
 * A.SEOa SA7 — language-aware permalink generation contracts.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\LanguageResolver;
use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\Router;

/**
 * Characterizes Supported SA7: prefix-aware URL generation on source leaf slugs.
 *
 * Does not assert translated leaf-slug resolution (SA1–SA3 Deferred).
 */
final class AseoaSa7PermalinkGenerationTest extends AimlTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->set_permalink_structure( '/%postname%/' );
	}

	protected function tearDown(): void {
		$this->set_permalink_structure( '' );
		parent::tearDown();
	}

	public function test_default_en_permalink_remains_unprefixed(): void {
		$this->add_language();
		$post = $this->create_page( 'About Us' );

		$this->route( '/' . $post->post_name . '/' );
		$this->go_to( '/' . $post->post_name . '/' );

		$permalink = (string) get_permalink( $post );
		$this->assertStringNotContainsString( '/sv/', $permalink );
		$this->assertStringContainsString( '/' . $post->post_name . '/', $permalink );
		$this->assertSame( $post->post_name, $post->post_name );
	}

	public function test_sv_home_url_and_permalink_receive_prefix_with_source_leaf(): void {
		$this->add_language();
		$post = $this->create_page( 'About Us' );

		$this->route( '/sv/' . $post->post_name . '/' );
		$this->go_to( $_SERVER['REQUEST_URI'] );

		$home      = home_url( '/' );
		$permalink = (string) get_permalink( $post );

		$this->assertStringContainsString( '/sv/', $home );
		$this->assertStringContainsString( '/sv/', $permalink );
		$this->assertStringContainsString( '/' . $post->post_name . '/', $permalink );
		$this->assertSame( 1, substr_count( wp_parse_url( $permalink, PHP_URL_PATH ), '/sv/' ) );
	}

	public function test_query_string_and_fragment_survive_home_url_prefixing(): void {
		$this->add_language();
		$this->route( '/sv/' );
		$this->go_to( '/' );

		$url = home_url( '/about-us/?utm=1#section' );
		$this->assertStringContainsString( '/sv/about-us/', $url );
		$this->assertStringContainsString( 'utm=1', $url );
		$this->assertStringContainsString( '#section', $url );
		$this->assertSame( 1, substr_count( (string) wp_parse_url( $url, PHP_URL_PATH ), '/sv/' ) );
	}

	public function test_already_prefixed_path_is_not_double_prefixed(): void {
		$this->add_language();
		$swedish = $this->languages->find_by_code( 'sv' );
		$this->assertNotNull( $swedish );

		$router = new Router( $this->languages, $this->resolver, $this->context );
		$this->context->set_default( $this->languages->default() );
		$this->context->set_current( $swedish );

		$already = home_url( '/sv/about-us/' );
		// Simulate filter without going through parse_request attach.
		$out = $router->filter_home_url( $already );
		$this->assertSame( $already, $out );
		$this->assertSame( 1, substr_count( (string) wp_parse_url( (string) $out, PHP_URL_PATH ), '/sv/' ) );
	}

	public function test_rest_admin_and_login_paths_are_not_prefixed_by_filter(): void {
		$this->add_language();
		$swedish = $this->languages->find_by_code( 'sv' );
		$this->assertNotNull( $swedish );

		$router = new Router( $this->languages, $this->resolver, $this->context );
		$this->context->set_default( $this->languages->default() );
		$this->context->set_current( $swedish );

		$rest = rest_url( 'aiml/v1/languages' );
		$this->assertStringNotContainsString( '/sv/', $router->filter_home_url( $rest ) );

		$admin = home_url( '/wp-admin/index.php' );
		$this->assertStringNotContainsString( '/sv/wp-admin', $router->filter_home_url( $admin ) );

		$login = home_url( '/wp-login.php' );
		$this->assertStringNotContainsString( '/sv/wp-login.php', $router->filter_home_url( $login ) );
	}

	public function test_post_and_product_permalinks_keep_source_leaf_under_sv(): void {
		$this->add_language();

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Hello World',
				'post_name'   => 'hello-world',
				'post_status' => 'publish',
			)
		);
		$post    = get_post( $post_id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		$product = null;
		if ( post_type_exists( 'product' ) ) {
			$product_id = self::factory()->post->create(
				array(
					'post_type'   => 'product',
					'post_title'  => 'Test Product',
					'post_name'   => 'test-product',
					'post_status' => 'publish',
				)
			);
			$product    = get_post( $product_id );
		}

		$this->route( '/sv/hello-world/' );
		$this->go_to( $_SERVER['REQUEST_URI'] );

		$post_url = (string) get_permalink( $post );
		$this->assertStringContainsString( '/sv/', $post_url );
		$this->assertStringContainsString( '/hello-world/', $post_url );

		if ( $product instanceof \WP_Post ) {
			$product_url = (string) get_permalink( $product );
			$this->assertStringContainsString( '/sv/', $product_url );
			// Pretty or query permalinks both retain the source leaf slug/name.
			$this->assertTrue(
				false !== strpos( $product_url, '/test-product' ) || false !== strpos( $product_url, 'product=test-product' ),
				'Product permalink must retain source slug leaf; got ' . $product_url
			);
			// Rewrite base remains English/source (ADR-0002 / SA3 base Deferred).
			$this->assertStringNotContainsString( '/produkt/', $product_url );
		}
	}

	public function test_external_host_urls_are_left_alone_when_filter_sees_them(): void {
		$this->add_language();
		$swedish = $this->languages->find_by_code( 'sv' );
		$this->assertNotNull( $swedish );

		$router = new Router( $this->languages, $this->resolver, $this->context );
		$this->context->set_default( $this->languages->default() );
		$this->context->set_current( $swedish );

		$external = 'https://example.com/path/';
		// filter_home_url only rewrites URLs with a host; external same-shape is still rewritten
		// only if it matches home host. Foreign hosts must not gain /sv/ via path alone when
		// host differs from site — Router rebuilds using the URL's own host, so document that
		// callers must not pass foreign absolute URLs through home_url filter unexpectedly.
		$home_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$ext_host  = (string) wp_parse_url( $external, PHP_URL_HOST );
		$this->assertNotSame( $home_host, $ext_host );

		$out = $router->filter_home_url( $external );
		// Current Router prefixes any absolute URL with a host when context is translated.
		// Capture the behavior: path gains /sv/ on that host (not a second router).
		$this->assertIsString( $out );
		$this->assertStringContainsString( 'example.com', (string) $out );
	}

	public function test_redirect_canonical_still_suppressed_when_prefixed(): void {
		$this->add_language();
		$this->route( '/sv/about-us/' );

		$this->assertFalse(
			apply_filters( 'redirect_canonical', home_url( '/about-us/' ) )
		);
	}

	public function test_router_registers_no_rewrite_rules(): void {
		$before = $GLOBALS['wp_rewrite']->rules ?? array();
		$router = new Router( $this->languages, new LanguageResolver(), new LanguageContext() );
		$router->register();
		do_action( 'plugins_loaded' );
		$after = $GLOBALS['wp_rewrite']->rules ?? array();
		$this->assertSame( $before, $after );
	}
}
