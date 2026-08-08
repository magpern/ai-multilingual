<?php
/**
 * A.6 N1 — custom nav_menu_item title extract + overlay.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\WorkspaceService;

/**
 * Custom menu titles use post_title on the menu item; empty custom titles
 * stay on the linked object title path (already covered).
 */
final class NavMenuItemTranslationTest extends AimlTestCase {

	public function test_custom_nav_menu_item_title_is_extracted(): void {
		$item = $this->create_nav_menu_item( 'Home', 'https://example.com/' );

		$segments = $this->extractor->extract( $item );

		$this->assertSame( array( Extractor::FIELD_TITLE ), array_keys( $segments ) );
		$this->assertSame( 'Home', $segments[ Extractor::FIELD_TITLE ]['source_text'] );
		$this->assertSame( Store::FORMAT_PLAIN, $segments[ Extractor::FIELD_TITLE ]['text_format'] );
	}

	public function test_empty_custom_nav_menu_item_title_is_not_extracted(): void {
		$item = $this->create_nav_menu_item( '', 'https://example.com/shop/' );

		$this->assertSame( array(), $this->extractor->extract( $item ) );
	}

	public function test_nav_menu_item_does_not_extract_excerpt_or_content(): void {
		$item = $this->create_nav_menu_item( 'Home', 'https://example.com/' );
		wp_update_post(
			array(
				'ID'           => $item->ID,
				'post_excerpt' => 'Should not become a segment',
				'post_content' => '<!-- wp:paragraph --><p>No</p><!-- /wp:paragraph -->',
			)
		);
		$item = get_post( $item->ID );
		$this->assertInstanceOf( \WP_Post::class, $item );

		$segments = $this->extractor->extract( $item );

		$this->assertArrayHasKey( Extractor::FIELD_TITLE, $segments );
		$this->assertArrayNotHasKey( Extractor::FIELD_EXCERPT, $segments );
		$this->assertArrayNotHasKey( Extractor::FIELD_CONTENT, $segments );
		$this->assertCount( 1, $segments );
	}

	public function test_workspace_supports_nav_menu_item_post_type(): void {
		$this->assertContains( 'nav_menu_item', WorkspaceService::SUPPORTED_POST_TYPES );
	}

	public function test_custom_nav_menu_item_title_overlays_in_target_language(): void {
		$swedish = $this->add_language();
		$item    = $this->create_nav_menu_item( 'Home', 'https://example.com/' );

		$this->translate( $item, $swedish, Extractor::FIELD_TITLE, 'Hem' );
		$this->register_renderer();

		$this->assertSame( 'Home', apply_filters( 'the_title', $item->post_title, $item->ID ) );

		$this->context->set_current( $swedish );

		$this->assertSame( 'Hem', apply_filters( 'the_title', $item->post_title, $item->ID ) );
	}

	public function test_page_title_overlay_unchanged_for_object_title_menus(): void {
		$swedish = $this->add_language();
		$page    = $this->create_page( 'Shop' );

		$this->translate( $page, $swedish, Extractor::FIELD_TITLE, 'Butik' );
		$this->register_renderer();
		$this->context->set_current( $swedish );

		// Object-title menu path calls the_title with the page ID, not the menu item.
		$this->assertSame( 'Butik', apply_filters( 'the_title', $page->post_title, $page->ID ) );
	}

	public function test_nav_menu_item_store_miss_keeps_source_title(): void {
		$swedish = $this->add_language();
		$item    = $this->create_nav_menu_item( 'Home', 'https://example.com/' );

		$this->register_renderer();
		$this->context->set_current( $swedish );

		$this->assertSame( 'Home', apply_filters( 'the_title', $item->post_title, $item->ID ) );
	}

	/**
	 * @param string $title Custom menu title (empty = object-title style).
	 * @param string $url   Menu item URL.
	 */
	private function create_nav_menu_item( string $title, string $url ): \WP_Post {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'nav_menu_item',
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => '',
				'post_excerpt' => '',
			)
		);

		update_post_meta( $id, '_menu_item_type', 'custom' );
		update_post_meta( $id, '_menu_item_url', $url );

		$post = get_post( $id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		return $post;
	}
}
