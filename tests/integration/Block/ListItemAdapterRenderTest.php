<?php
/**
 * F14 list-item frontend render-safety integration.
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
 * Render-safety for core/list-item under Strategy F gate.
 */
final class ListItemAdapterRenderTest extends AimlTestCase {

	private const UUID = '550e8400-e29b-41d4-a716-4466554400d1';

	public function test_list_item_renders_translation_without_false_positive(): void {
		$swedish = $this->add_language();
		$content = '<!-- wp:list --><!-- wp:list-item {"aimlBlockId":"' . self::UUID . '"} --><li>Alpha</li><!-- /wp:list-item --><!-- /wp:list -->';
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
				'source_text'     => '<li>Alpha</li>',
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

		// False positive: translation must not attach to a different UUID.
		$wrong = $frontend->render(
			$post,
			'<!-- wp:list-item {"aimlBlockId":"550e8400-e29b-41d4-a716-4466554400ff"} --><li>Other</li><!-- /wp:list-item -->'
		);
		$this->assertStringNotContainsString( 'Alfa', $wrong );
		$this->assertStringContainsString( 'Other', $wrong );
	}
}
