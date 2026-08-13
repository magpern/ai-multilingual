<?php
/**
 * TSC.4 frontend render integration for non-content block fields.
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
use AIMultilingual\Translation\Store;

/**
 * @covers \AIMultilingual\Translation\BlockTranslationLookup
 * @covers \AIMultilingual\Translation\BlockFrontendRenderer
 */
final class Tsc4BlockFieldRenderTest extends AimlTestCase {

	private const UUID_Q = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
	private const UUID_D = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1';
	private const UUID_I = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc1';
	private const UUID_F = 'dddddddd-dddd-4ddd-8ddd-ddddddddddd1';

	public function test_quote_citation_renders_on_frontend(): void {
		$swedish = $this->add_language();
		$content = sprintf(
			'<!-- wp:quote {"%1$s":"%2$s"} --><blockquote class="wp-block-quote"><p>Body</p><cite>Author EN</cite></blockquote><!-- /wp:quote -->',
			Contract::ATTR_NAME,
			self::UUID_Q
		);
		$post    = $this->create_page( 'Quote', $content );
		$this->save_field_translation( $post, $swedish, self::UUID_Q, Contract::FIELD_CITATION, 'Author EN', 'Författare SV', Store::FORMAT_HTML );

		$this->assertStringContainsString( 'Författare SV', $this->render_blocks( $post, $swedish, $content ) );
	}

	public function test_details_summary_renders_on_frontend(): void {
		$swedish = $this->add_language();
		$content = sprintf(
			'<!-- wp:details {"%1$s":"%2$s"} --><details class="wp-block-details"><summary>Summary EN</summary><p>Body</p></details><!-- /wp:details -->',
			Contract::ATTR_NAME,
			self::UUID_D
		);
		$post    = $this->create_page( 'Details', $content );
		$this->save_field_translation( $post, $swedish, self::UUID_D, Contract::FIELD_SUMMARY, 'Summary EN', 'Sammanfattning SV', Store::FORMAT_HTML );

		$this->assertStringContainsString( 'Sammanfattning SV', $this->render_blocks( $post, $swedish, $content ) );
	}

	public function test_image_caption_renders_on_frontend(): void {
		$swedish = $this->add_language();
		$content = sprintf(
			'<!-- wp:image {"%1$s":"%2$s"} --><figure class="wp-block-image"><img src="https://example.com/x.jpg" alt=""/><figcaption>Caption EN</figcaption></figure><!-- /wp:image -->',
			Contract::ATTR_NAME,
			self::UUID_I
		);
		$post    = $this->create_page( 'Image', $content );
		$this->save_field_translation( $post, $swedish, self::UUID_I, Contract::FIELD_CAPTION, 'Caption EN', 'Bildtext SV', Store::FORMAT_HTML );

		$this->assertStringContainsString( 'Bildtext SV', $this->render_blocks( $post, $swedish, $content ) );
	}

	public function test_file_labels_render_on_frontend(): void {
		$swedish = $this->add_language();
		$content = sprintf(
			'<!-- wp:file {"%1$s":"%2$s","fileName":"Report EN","downloadButtonText":"Download EN"} --><div class="wp-block-file"><a href="https://example.com/report.pdf">Report EN</a><a href="https://example.com/report.pdf" class="wp-block-file__button" download>Download EN</a></div><!-- /wp:file -->',
			Contract::ATTR_NAME,
			self::UUID_F
		);
		$post    = $this->create_page( 'File', $content );
		$this->save_field_translation( $post, $swedish, self::UUID_F, Contract::FIELD_FILE_NAME, 'Report EN', 'Rapport SV', Store::FORMAT_PLAIN );
		$this->save_field_translation( $post, $swedish, self::UUID_F, Contract::FIELD_DOWNLOAD_BUTTON_TEXT, 'Download EN', 'Ladda ner SV', Store::FORMAT_PLAIN );

		$output = $this->render_blocks( $post, $swedish, $content );
		$this->assertStringContainsString( 'Rapport SV', $output );
		$this->assertStringContainsString( 'Ladda ner SV', $output );
	}

	public function test_forged_href_translation_falls_back_to_source(): void {
		$swedish = $this->add_language();
		$uuid    = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1';
		$content = sprintf(
			'<!-- wp:button {"%1$s":"%2$s","url":"https://example.com/safe"} --><div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com/safe">Go EN</a></div><!-- /wp:button -->',
			Contract::ATTR_NAME,
			$uuid
		);
		$post    = $this->create_page( 'Button', $content );
		$this->save_field_translation(
			$post,
			$swedish,
			$uuid,
			Contract::FIELD_CONTENT,
			'<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com/safe">Go EN</a></div>',
			'<a href="https://evil.test">Forged</a>',
			Store::FORMAT_HTML
		);

		$output = $this->render_blocks( $post, $swedish, $content );
		$this->assertStringContainsString( 'Go EN', $output );
		$this->assertStringContainsString( 'https://example.com/safe', $output );
		$this->assertStringNotContainsString( 'https://evil.test', $output );
	}

	private function render_blocks( \WP_Post $post, object $language, string $content ): string {
		$settings = new Settings(
			array(
				'block_attr_registration_enabled'  => true,
				'block_uuid_injection_enabled'     => true,
				'block_extraction_enabled'         => true,
				'block_frontend_rendering_enabled' => true,
			)
		);
		$registry = new AdapterRegistry();
		$frontend = new BlockFrontendRenderer(
			new BlockRenderGate(),
			new BlockTranslationLookup( $this->store ),
			new BlockTranslationSanitizer(),
			new BlockRenderer( $registry, new BlockRenderLogger() ),
			new BlockFrontendRenderLogger(),
			$settings,
			$this->context,
			new Extractor(
				$settings,
				new BlockExtractor(
					$registry,
					new BlockRegistry( $registry ),
					new BlockExtractionLogger()
				)
			)
		);

		$this->context->set_current( $language );

		return $frontend->render( $post, $content );
	}

	private function save_field_translation(
		\WP_Post $post,
		object $language,
		string $uuid,
		string $field,
		string $source,
		string $translation,
		string $format
	): void {
		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => SegmentKey::build( $uuid, $field ),
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => $format,
				'source_text'     => $source,
				'translated_text' => $translation,
				'status'          => Store::STATUS_REVIEWED,
			)
		);
	}
}
