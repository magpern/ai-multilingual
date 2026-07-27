<?php
/**
 * Strategy F gated frontend block rendering integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Settings;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\BlockFrontendRenderer;
use AIMultilingual\Translation\BlockFrontendRenderLogger;
use AIMultilingual\Translation\BlockRenderGate;
use AIMultilingual\Translation\BlockRenderer;
use AIMultilingual\Translation\BlockTranslationLookup;
use AIMultilingual\Translation\BlockTranslationSanitizer;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Renderer;
use AIMultilingual\Translation\RenderGateContext;
use AIMultilingual\Translation\Store;
use AIMultilingual\Language\Languages;

/**
 * Frontend block rendering through the render gate and Store lookup.
 */
final class BlockFrontendRenderingTest extends AimlTestCase {

	private const UUID_A = '550e8400-e29b-41d4-a716-446655440000';
	private const UUID_B = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
	private const UUID_C = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

	public function test_feature_flag_disabled_returns_source_content(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );

		$this->register_block_renderer(
			new Settings(
				array(
					'block_attr_registration_enabled'  => true,
					'block_uuid_injection_enabled'     => true,
					'block_extraction_enabled'         => true,
					'block_frontend_rendering_enabled' => false,
				)
			)
		);

		$this->assert_rendered_content( $post, $swedish, 'Hello', $post->post_content );
	}

	public function test_dependency_flag_disabled_denies_rendering(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );

		$this->register_block_renderer(
			new Settings(
				array(
					'block_attr_registration_enabled'  => true,
					'block_uuid_injection_enabled'     => true,
					'block_extraction_enabled'         => false,
					'block_frontend_rendering_enabled' => true,
				)
			)
		);

		$this->assert_rendered_content( $post, $swedish, 'Hello', $post->post_content );
	}

	public function test_source_language_returns_source_content(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );
		$this->register_block_renderer();

		$this->context->set_current( $this->languages->default() );
		$this->assert_rendered_content( $post, $this->languages->default(), 'Hello', $post->post_content );
	}

	public function test_unresolved_target_language_returns_source_content(): void {
		$post = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->register_block_renderer();
		$this->context->set_current( null );

		$this->assert_rendered_content( $post, null, 'Hello', $post->post_content );
	}

	public function test_unsupported_disabled_language_returns_source_content(): void {
		$disabled = $this->add_language( 'de', 'de_DE', Languages::STATUS_DISABLED );
		$post     = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $disabled, self::UUID_A, 'Hallo' );
		$this->register_block_renderer();

		$this->assert_rendered_content( $post, $disabled, 'Hello', $post->post_content );
	}

	public function test_frontend_target_language_renders_supported_translations(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Hello' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Hej' );
		$this->register_block_renderer();

		$rendered = $this->render_content( $post, $swedish );
		$this->assertStringContainsString( 'Hej', $rendered );
		$this->assertStringNotContainsString( '>Hello<', $rendered );
	}

	public function test_paragraph_translation_renders(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Paragraph EN' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Stycke SV' );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Stycke SV', $this->render_content( $post, $swedish ) );
	}

	public function test_heading_translation_renders(): void {
		$swedish = $this->add_language();
		$content = sprintf(
			'<!-- wp:heading {"level":2,"%1$s":"%2$s"} --><h2>Heading EN</h2><!-- /wp:heading -->',
			Contract::ATTR_NAME,
			self::UUID_A
		);
		$post    = $this->create_page( 'Heading', $content );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Rubrik SV', '<h2>Heading EN</h2>' );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Rubrik SV', $this->render_content( $post, $swedish ) );
	}

	public function test_button_translation_renders(): void {
		$swedish = $this->add_language();
		$content = sprintf(
			'<!-- wp:button {"%1$s":"%2$s"} --><div class="wp-block-button"><a class="wp-block-button__link">Buy EN</a></div><!-- /wp:button -->',
			Contract::ATTR_NAME,
			self::UUID_A
		);
		$post    = $this->create_page( 'Button', $content );
		$this->save_block_translation(
			$post,
			$swedish,
			self::UUID_A,
			'Köp SV',
			'<div class="wp-block-button"><a class="wp-block-button__link">Buy EN</a></div>'
		);
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Köp SV', $this->render_content( $post, $swedish ) );
	}

	public function test_mixed_translated_and_untranslated_blocks(): void {
		$swedish = $this->add_language();
		$content = $this->paragraph_content( self::UUID_A, 'Translated EN' )
			. $this->paragraph_content( self::UUID_B, 'Untranslated EN' );
		$post    = $this->create_page( 'Mixed', $content );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Translated SV' );
		$this->register_block_renderer();

		$rendered = $this->render_content( $post, $swedish );
		$this->assertStringContainsString( 'Translated SV', $rendered );
		$this->assertStringContainsString( 'Untranslated EN', $rendered );
	}

	public function test_stale_translation_is_excluded(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Changed EN' );
		$key     = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Stale SV', '<p>Original EN</p>' );

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'aiml_translations',
			array( 'is_stale' => 1 ),
			array(
				'source_type' => Store::SOURCE_POST,
				'source_id'   => (int) $post->ID,
				'language_id' => (int) $swedish->language_id,
				'segment_key' => $key,
			)
		);
		$this->store = new Store( $this->cache );

		$this->register_block_renderer();
		$this->assertStringContainsString( 'Changed EN', $this->render_content( $post, $swedish ) );
	}

	public function test_orphaned_translation_is_excluded(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Current EN' );
		$key     = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Orphan SV' );

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'aiml_translations',
			array( 'status' => Store::STATUS_IGNORED ),
			array(
				'source_type' => Store::SOURCE_POST,
				'source_id'   => (int) $post->ID,
				'language_id' => (int) $swedish->language_id,
				'segment_key' => $key,
			)
		);
		$this->store = new Store( $this->cache );

		$this->register_block_renderer();
		$this->assertStringContainsString( 'Current EN', $this->render_content( $post, $swedish ) );
	}

	public function test_empty_translation_is_excluded(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Keep EN' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, '' );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Keep EN', $this->render_content( $post, $swedish ) );
	}

	public function test_whitespace_only_translation_is_excluded(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Keep EN' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, "  \n\t  " );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Keep EN', $this->render_content( $post, $swedish ) );
	}

	public function test_unsupported_segment_kind_is_excluded(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Keep EN' );
		$key     = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $swedish->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>Keep EN</p>',
				'translated_text' => 'Field SV',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$this->register_block_renderer();
		$this->assertStringContainsString( 'Keep EN', $this->render_content( $post, $swedish ) );
	}

	public function test_unsupported_block_remains_unchanged(): void {
		$swedish = $this->add_language();
		$content = '<!-- wp:quote --><blockquote><p>Quote EN</p></blockquote><!-- /wp:quote -->'
			. $this->paragraph_content( self::UUID_A, 'Paragraph EN' );
		$post    = $this->create_page( 'Quote', $content );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Stycke SV' );
		$this->register_block_renderer();

		$rendered = $this->render_content( $post, $swedish );
		$this->assertStringContainsString( 'Quote EN', $rendered );
		$this->assertStringContainsString( 'Stycke SV', $rendered );
	}

	public function test_nested_group_renders_once(): void {
		$swedish = $this->add_language();
		$content = '<!-- wp:group -->'
			. '<div class="wp-block-group">' . $this->paragraph_content( self::UUID_A, 'Nested EN' ) . '</div>'
			. '<!-- /wp:group -->';
		$post    = $this->create_page( 'Nested', $content );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Nested SV' );
		$this->register_block_renderer();

		$rendered = $this->render_content( $post, $swedish );
		$this->assertSame( 1, substr_count( $rendered, 'Nested SV' ) );
	}

	public function test_second_pass_is_stable(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Stable EN' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Stable SV' );
		$this->register_block_renderer();

		$first  = $this->render_content( $post, $swedish );
		$second = apply_filters( 'the_content', $first );

		$this->assertSame( trim( $first ), trim( (string) $second ) );
	}

	public function test_recursion_guard_returns_source_on_reentry(): void {
		$swedish  = $this->add_language();
		$post     = $this->create_block_page( self::UUID_A, 'Safe EN' );
		$renderer = $this->block_frontend_renderer();
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Safe SV', '<p>Safe EN</p>' );
		$this->context->set_current( $swedish );

		$property = new \ReflectionProperty( BlockFrontendRenderer::class, 'rendering' );
		$property->setAccessible( true );
		$property->setValue( $renderer, true );

		$this->assertSame( $post->post_content, $renderer->render( $post, $post->post_content ) );
	}

	public function test_admin_request_is_denied(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Admin EN' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Admin SV' );
		$this->register_block_renderer();
		$this->context->set_current( $swedish );

		set_current_screen( 'edit-page' );

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$output          = apply_filters( 'the_content', $post->post_content );

		$this->assertStringContainsString( 'Admin EN', (string) $output );
		$this->assertStringNotContainsString( 'Admin SV', (string) $output );

		set_current_screen( 'front' );
	}

	public function test_elementor_body_is_denied(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Elementor EN' );
		update_post_meta( $post->ID, '_elementor_data', '[{"id":"abc"}]' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Elementor SV' );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Elementor EN', $this->render_content( $post, $swedish ) );
	}

	public function test_non_block_content_is_denied(): void {
		$swedish = $this->add_language();
		$post    = $this->create_page( 'Classic', '<p>Classic EN</p>' );
		$this->translate( $post, $swedish, Extractor::FIELD_CONTENT, '<p>Klassisk SV</p>' );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Klassisk SV', $this->render_content( $post, $swedish ) );
	}

	public function test_lookup_invalid_scope_fails_closed(): void {
		$lookup = new BlockTranslationLookup( $this->store );
		$result = $lookup->for_post( Store::SOURCE_POST, 0, 0 );

		$this->assertFalse( $result->successful );
		$this->assertSame( 'invalid_scope', $result->failure_reason );
	}

	public function test_unsafe_translated_markup_is_rejected(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Safe EN' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, '<script>alert(1)</script>' );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Safe EN', $this->render_content( $post, $swedish ) );
	}

	public function test_valid_allowlisted_markup_is_accepted(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Rich EN' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, '<p>Rich <strong>SV</strong></p>' );
		$this->register_block_renderer();

		$this->assertStringContainsString( '<strong>SV</strong>', $this->render_content( $post, $swedish ) );
	}

	public function test_same_uuid_in_two_posts_does_not_leak(): void {
		$swedish = $this->add_language();
		$post_a  = $this->create_block_page( self::UUID_A, 'Post A EN' );
		$post_b  = $this->create_block_page( self::UUID_A, 'Post B EN' );
		$this->save_block_translation( $post_a, $swedish, self::UUID_A, 'Post A SV' );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Post A SV', $this->render_content( $post_a, $swedish ) );
		$this->assertStringContainsString( 'Post B EN', $this->render_content( $post_b, $swedish ) );
	}

	public function test_no_translations_leaves_source_text_visible(): void {
		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Original EN' );
		$this->register_block_renderer();

		$this->assertStringContainsString( 'Original EN', $this->render_content( $post, $swedish ) );
		$this->assertSame( $post->post_content, get_post( $post->ID )->post_content );
	}

	public function test_stored_post_content_is_unchanged_after_render(): void {
		global $wpdb;

		$swedish = $this->add_language();
		$post    = $this->create_block_page( self::UUID_A, 'Persist EN' );
		$this->save_block_translation( $post, $swedish, self::UUID_A, 'Persist SV' );
		$this->register_block_renderer();

		$before = $wpdb->get_var( $wpdb->prepare( 'SELECT post_content FROM ' . $wpdb->posts . ' WHERE ID = %d', $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->render_content( $post, $swedish );
		$after = $wpdb->get_var( $wpdb->prepare( 'SELECT post_content FROM ' . $wpdb->posts . ' WHERE ID = %d', $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->assertSame( $before, $after );
	}

	public function test_gate_denies_duplicate_uuid_in_one_post(): void {
		$content = $this->paragraph_content( self::UUID_A, 'One' )
			. $this->paragraph_content( self::UUID_A, 'Two' );
		$post    = $this->create_page( 'Dup UUID', $content );
		$gate    = new BlockRenderGate();
		$context = new RenderGateContext(
			$this->enabled_settings(),
			$this->target_context( $this->add_language() ),
			$this->enabled_extractor(),
			$post,
			$content,
		);

		$this->assertSame( BlockRenderGate::REASON_INCOMPLETE_IDENTITY, $gate->evaluate( $context )->reason );
	}

	public function test_block_renderer_and_lookup_have_no_store_in_source(): void {
		$root = dirname( __DIR__, 2 );
		$this->assertStringNotContainsString(
			'load_object',
			(string) file_get_contents( $root . '/src/Translation/BlockRenderer.php' )
		);
		$this->assertStringNotContainsString(
			'Store',
			(string) file_get_contents( $root . '/src/Block/Adapter/ParagraphAdapter.php' )
		);
	}

	private function enabled_settings(): Settings {
		return new Settings(
			array(
				'block_attr_registration_enabled'  => true,
				'block_uuid_injection_enabled'     => true,
				'block_extraction_enabled'         => true,
				'block_frontend_rendering_enabled' => true,
			)
		);
	}

	private function enabled_extractor(): Extractor {
		$registry = new AdapterRegistry();

		return new Extractor(
			$this->enabled_settings(),
			new BlockExtractor(
				$registry,
				new BlockRegistry( $registry ),
				new BlockExtractionLogger()
			)
		);
	}

	private function block_frontend_renderer( ?Settings $settings = null ): BlockFrontendRenderer {
		$settings   = $settings ?? $this->enabled_settings();
		$registry   = new AdapterRegistry();
		$extractor  = new Extractor(
			$settings,
			new BlockExtractor(
				$registry,
				new BlockRegistry( $registry ),
				new BlockExtractionLogger()
			)
		);
		$block_core = new BlockRenderer( $registry, new BlockRenderLogger() );

		return new BlockFrontendRenderer(
			new BlockRenderGate(),
			new BlockTranslationLookup( $this->store ),
			new BlockTranslationSanitizer(),
			$block_core,
			new BlockFrontendRenderLogger(),
			$settings,
			$this->context,
			$extractor
		);
	}

	private function register_block_renderer( ?Settings $settings = null ): void {
		$settings  = $settings ?? $this->enabled_settings();
		$registry  = new AdapterRegistry();
		$extractor = new Extractor(
			$settings,
			new BlockExtractor(
				$registry,
				new BlockRegistry( $registry ),
				new BlockExtractionLogger()
			)
		);
		$frontend  = new BlockFrontendRenderer(
			new BlockRenderGate(),
			new BlockTranslationLookup( $this->store ),
			new BlockTranslationSanitizer(),
			new BlockRenderer( $registry, new BlockRenderLogger() ),
			new BlockFrontendRenderLogger(),
			$settings,
			$this->context,
			$extractor
		);

		( new Renderer( $this->context, $this->store, $extractor, $frontend ) )->register();
	}

	private function create_block_page( string $uuid, string $text ): \WP_Post {
		return $this->create_page( 'Blocks', $this->paragraph_content( $uuid, $text ) );
	}

	private function paragraph_content( string $uuid, string $text ): string {
		return sprintf(
			'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
			Contract::ATTR_NAME,
			$uuid,
			$text
		);
	}

	private function save_block_translation(
		\WP_Post $post,
		object $language,
		string $uuid,
		string $translation,
		?string $source_html = null
	): void {
		$key = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		if ( null === $source_html && preg_match( '/<p>(.*?)<\/p>/s', $post->post_content, $matches ) ) {
			$source_html = '<p>' . $matches[1] . '</p>';
		}
		$source_html = $source_html ?? '<p>Original</p>';
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => $source_html,
				'translated_text' => $translation,
				'status'          => Store::STATUS_REVIEWED,
			)
		);
	}

	private function target_context( object $language ): \AIMultilingual\Language\LanguageContext {
		$this->context->set_current( $language );

		return $this->context;
	}

	private function render_content( \WP_Post $post, ?object $language ): string {
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		$this->context->set_current( $language );

		$output = trim( (string) apply_filters( 'the_content', $post->post_content ) );
		wp_reset_postdata();

		return $output;
	}

	/**
	 * Asserts rendered output contains a needle and canonical post content is unchanged.
	 *
	 * @param \WP_Post    $post       Source post.
	 * @param object|null $language   Target language.
	 * @param string      $needle     Expected substring in rendered output.
	 * @param string      $canonical  Expected stored post_content.
	 */
	private function assert_rendered_content( \WP_Post $post, ?object $language, string $needle, string $canonical ): void {
		$rendered = $this->render_content( $post, $language );
		$this->assertStringContainsString( $needle, $rendered );
		$this->assertSame( $canonical, get_post( $post->ID )->post_content );
	}
}
