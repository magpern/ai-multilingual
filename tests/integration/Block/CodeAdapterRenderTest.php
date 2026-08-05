<?php
/**
 * F14 code frontend render-safety integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Block;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Settings;
use AIMultilingual\Tests\Integration\AimlTestCase;
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
 * Render-safety for core/code.
 */
final class CodeAdapterRenderTest extends AimlTestCase {

	private const UUID = '550e8400-e29b-41d4-a716-4466554400d4';

	public function test_code_renders_translation_without_false_positive(): void {
		$swedish = $this->add_language();
		$content = '<!-- wp:code {"aimlBlockId":"' . self::UUID . '"} --><pre class="wp-block-code"><code>Alpha</code></pre><!-- /wp:code -->';
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		$post    = get_post( $post_id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $swedish->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => SegmentKey::build( self::UUID, Contract::FIELD_CONTENT ),
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<pre class="wp-block-code"><code>Alpha</code></pre>',
				'translated_text' => 'Alfa',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$settings = new Settings(
			array(
				'block_attr_registration_enabled'  => true,
				'block_uuid_injection_enabled'     => true,
				'block_extraction_enabled'         => true,
				'block_frontend_rendering_enabled' => true,
			)
		);
		$adapters = new AdapterRegistry();
		$registry = new BlockRegistry( $adapters );
		$frontend = new BlockFrontendRenderer(
			new BlockRenderGate(),
			new BlockTranslationLookup( $this->store ),
			new BlockTranslationSanitizer(),
			new BlockRenderer( $adapters, new BlockRenderLogger() ),
			new BlockFrontendRenderLogger(),
			$settings,
			$this->context,
			new Extractor( $settings, new BlockExtractor( $adapters, $registry, new BlockExtractionLogger() ) )
		);

		$this->context->set_current( $swedish );
		$html = $frontend->render( $post, $post->post_content );
		$this->assertStringContainsString( 'Alfa', $html );
		$this->assertStringNotContainsString( '>Alpha<', $html );

		$wrong = $frontend->render(
			$post,
			'<!-- wp:code {"aimlBlockId":"550e8400-e29b-41d4-a716-4466554400ff"} --><pre class="wp-block-code"><code>Other</code></pre><!-- /wp:code -->'
		);
		$this->assertStringNotContainsString( 'Alfa', $wrong );
		$this->assertStringContainsString( 'Other', $wrong );
	}
}
