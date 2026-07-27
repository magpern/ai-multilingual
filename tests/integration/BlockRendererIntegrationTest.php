<?php
/**
 * Strategy F block renderer serialization integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\BlockRenderer;

/**
 * BlockRenderer proof with WordPress parse_blocks and serialize_blocks.
 */
final class BlockRendererIntegrationTest extends AimlTestCase {

	public function test_render_content_without_translations_is_byte_identical(): void {
		$content = '<!-- wp:paragraph {"aimlBlockId":"550e8400-e29b-41d4-a716-446655440000"} -->' . "\n"
			. '<p>Stable body</p>' . "\n"
			. '<!-- /wp:paragraph -->';

		$renderer = new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() );
		$result   = $renderer->render_content( $content, array() );

		$this->assertFalse( $result->changed );
		$this->assertSame( $content, $result->content );
	}

	public function test_render_content_serializes_translated_blocks(): void {
		$uuid    = '550e8400-e29b-41d4-a716-446655440000';
		$content = sprintf(
			'<!-- wp:paragraph {"%1$s":"%2$s","className":"intro"} -->' . "\n"
			. '<p class="intro">Hello</p>' . "\n"
			. '<!-- /wp:paragraph -->',
			Contract::ATTR_NAME,
			$uuid
		);
		$key     = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );

		$renderer = new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() );
		$result   = $renderer->render_content( $content, array( $key => 'Hej' ) );

		$this->assertTrue( $result->changed );
		$this->assertNotSame( $content, $result->content );
		$this->assertStringContainsString( 'Hej', $result->content );
		$this->assertStringContainsString( 'intro', $result->content );
		$this->assertStringContainsString( $uuid, $result->content );
	}

	public function test_render_content_is_stable_on_second_pass(): void {
		$uuid     = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
		$content  = sprintf(
			'<!-- wp:heading {"level":2,"%1$s":"%2$s"} -->' . "\n"
			. '<h2 class="wp-block-heading">Title</h2>' . "\n"
			. '<!-- /wp:heading -->',
			Contract::ATTR_NAME,
			$uuid
		);
		$key      = SegmentKey::build( $uuid, Contract::FIELD_CONTENT );
		$renderer = new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() );

		$first  = $renderer->render_content( $content, array( $key => 'Rubrik' ) );
		$second = $renderer->render_content( $first->content, array( $key => 'Rubrik' ) );

		$this->assertTrue( $first->changed );
		$this->assertFalse( $second->changed );
		$this->assertSame( $first->content, $second->content );
	}
}
