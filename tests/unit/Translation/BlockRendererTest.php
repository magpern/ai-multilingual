<?php
/**
 * Strategy F block renderer proof.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\BlockRenderer;
use PHPUnit\Framework\TestCase;

/**
 * BlockRenderer proof behavior without WordPress serialization helpers.
 */
final class BlockRendererTest extends TestCase {

	private const UUID_A = '550e8400-e29b-41d4-a716-446655440000';
	private const UUID_B = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
	private const UUID_C = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

	private BlockRenderer $renderer;

	protected function setUp(): void {
		parent::setUp();

		$this->renderer = new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() );
	}

	public function test_paragraph_render_replaces_content_only(): void {
		$blocks = array( $this->paragraph( 'Hello', self::UUID_A ) );
		$key    = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );

		$result = $this->renderer->render(
			$blocks,
			array( $key => 'Bonjour' )
		);

		$this->assertTrue( $result->changed );
		$this->assertSame( '<p>Bonjour</p>', $blocks[0]['innerHTML'] );
		$this->assertSame( array( '<p>Bonjour</p>' ), $blocks[0]['innerContent'] );
	}

	public function test_heading_render_replaces_content_only(): void {
		$blocks = array(
			$this->block(
				'core/heading',
				'<h2>Title</h2>',
				self::UUID_B,
				array( 'level' => 2 )
			),
		);
		$key    = SegmentKey::build( self::UUID_B, Contract::FIELD_CONTENT );

		$this->renderer->render( $blocks, array( $key => 'Rubrik' ) );

		$this->assertSame( '<h2>Rubrik</h2>', $blocks[0]['innerHTML'] );
	}

	public function test_button_render_replaces_label_only(): void {
		$html   = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com/a">Buy now</a></div>';
		$blocks = array(
			$this->block( 'core/button', $html, self::UUID_C, array( 'url' => 'https://example.com/a' ) ),
		);
		$key    = SegmentKey::build( self::UUID_C, Contract::FIELD_CONTENT );

		$this->renderer->render( $blocks, array( $key => 'Acheter' ) );

		$this->assertStringContainsString( 'href="https://example.com/a"', $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'Acheter', $blocks[0]['innerHTML'] );
		$this->assertStringNotContainsString( 'Buy now', $blocks[0]['innerHTML'] );
		$this->assertSame( 'https://example.com/a', $blocks[0]['attrs']['url'] );
	}

	public function test_mixed_translated_and_untranslated_document(): void {
		$blocks = array(
			$this->paragraph( 'One', self::UUID_A ),
			$this->paragraph( 'Two', self::UUID_B ),
		);
		$key_a  = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );

		$result = $this->renderer->render( $blocks, array( $key_a => 'Ett' ) );

		$this->assertTrue( $result->changed );
		$this->assertSame( '<p>Ett</p>', $blocks[0]['innerHTML'] );
		$this->assertSame( '<p>Two</p>', $blocks[1]['innerHTML'] );
		$this->assertTrue( $this->has_event( $result, BlockRenderLogger::EVENT_TRANSLATION_MISSING ) );
	}

	public function test_missing_translation_preserves_original_content(): void {
		$blocks = array( $this->paragraph( 'Keep me', self::UUID_A ) );
		$before = $blocks[0]['innerHTML'];

		$result = $this->renderer->render( $blocks, array() );

		$this->assertFalse( $result->changed );
		$this->assertSame( $before, $blocks[0]['innerHTML'] );
	}

	public function test_nested_container_children_render_and_container_stays_structural(): void {
		$child  = $this->paragraph( 'Inside', self::UUID_A );
		$blocks = array(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array( 'layout' => array( 'type' => 'constrained' ) ),
				'innerHTML'    => '<div class="wp-block-group"></div>',
				'innerBlocks'  => array( $child ),
				'innerContent' => array( '<div class="wp-block-group">', null, '</div>' ),
			),
		);
		$key    = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );

		$this->renderer->render( $blocks, array( $key => 'Inuti' ) );

		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertSame( '<p>Inuti</p>', $blocks[0]['innerBlocks'][0]['innerHTML'] );
	}

	public function test_unsupported_block_is_unchanged(): void {
		$html   = '<hr class="wp-block-separator"/>';
		$blocks = array(
			$this->block( 'core/separator', $html, self::UUID_A ),
		);
		$before = $blocks[0];

		$result = $this->renderer->render(
			$blocks,
			array( SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT ) => 'Citat' )
		);

		$this->assertFalse( $result->changed );
		$this->assertSame( $before['innerHTML'], $blocks[0]['innerHTML'] );
		$this->assertTrue( $this->has_event( $result, BlockRenderLogger::EVENT_UNSUPPORTED_BLOCK ) );
	}

	public function test_multiple_translated_blocks(): void {
		$blocks = array(
			$this->paragraph( 'One', self::UUID_A ),
			$this->block( 'core/heading', '<h2>Two</h2>', self::UUID_B, array( 'level' => 2 ) ),
		);

		$result = $this->renderer->render(
			$blocks,
			array(
				SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT ) => 'Ett',
				SegmentKey::build( self::UUID_B, Contract::FIELD_CONTENT ) => 'Tva',
			)
		);

		$this->assertTrue( $result->changed );
		$this->assertSame( '<p>Ett</p>', $blocks[0]['innerHTML'] );
		$this->assertSame( '<h2>Tva</h2>', $blocks[1]['innerHTML'] );
	}

	public function test_document_without_translations_is_unchanged(): void {
		$blocks = array( $this->paragraph( 'Stable', self::UUID_A ) );
		$copy   = $blocks;

		$result = $this->renderer->render( $blocks, array() );

		$this->assertFalse( $result->changed );
		$this->assertSame( $copy, $blocks );
	}

	public function test_block_attributes_are_preserved(): void {
		$attrs  = array(
			Contract::ATTR_NAME => self::UUID_A,
			'className'         => 'intro',
			'anchor'            => 'intro-section',
			'textAlign'         => 'center',
			'style'             => array( 'typography' => array( 'fontSize' => 'large' ) ),
			'customField'       => 'keep-me',
		);
		$blocks = array(
			$this->block( 'core/paragraph', '<p>Hello</p>', self::UUID_A, $attrs ),
		);
		$key    = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );

		$this->renderer->render( $blocks, array( $key => 'Hej' ) );

		$this->assertSame( $attrs, $blocks[0]['attrs'] );
	}

	public function test_inner_blocks_are_preserved_on_supported_blocks(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/buttons',
				'attrs'        => array(),
				'innerHTML'    => '<div class="wp-block-buttons"></div>',
				'innerBlocks'  => array(
					$this->block(
						'core/button',
						'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com">Go</a></div>',
						self::UUID_C,
						array( 'url' => 'https://example.com' )
					),
				),
				'innerContent' => array( '<div class="wp-block-buttons">', null, '</div>' ),
			),
		);
		$key    = SegmentKey::build( self::UUID_C, Contract::FIELD_CONTENT );

		$this->renderer->render( $blocks, array( $key => 'Ga' ) );

		$this->assertCount( 1, $blocks[0]['innerBlocks'] );
		$this->assertSame( 'core/buttons', $blocks[0]['blockName'] );
		$this->assertStringContainsString( 'Ga', $blocks[0]['innerBlocks'][0]['innerHTML'] );
	}

	public function test_segment_key_lookup_drives_translation_application(): void {
		$key    = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
		$blocks = array( $this->paragraph( 'Source', self::UUID_A ) );

		$this->renderer->render( $blocks, array( $key => 'Mal' ) );

		$this->assertSame( '<p>Mal</p>', $blocks[0]['innerHTML'] );
	}

	public function test_logs_block_rendered_event(): void {
		$key    = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
		$blocks = array( $this->paragraph( 'Source', self::UUID_A ) );

		$result = $this->renderer->render( $blocks, array( $key => 'Mal' ) );

		$this->assertTrue( $this->has_event( $result, BlockRenderLogger::EVENT_BLOCK_RENDERED ) );
	}

	/**
	 * @param string $text Inner text.
	 * @param string $uuid Block UUID.
	 * @return array<string, mixed>
	 */
	private function paragraph( string $text, string $uuid ): array {
		return $this->block( 'core/paragraph', '<p>' . $text . '</p>', $uuid );
	}

	/**
	 * @param string               $name       Block name.
	 * @param string               $inner_html Inner HTML.
	 * @param string               $uuid       Block UUID.
	 * @param array<string, mixed> $attrs      Block attributes.
	 * @return array<string, mixed>
	 */
	private function block( string $name, string $inner_html, string $uuid, array $attrs = array() ): array {
		$attrs[ Contract::ATTR_NAME ] = $uuid;

		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);
	}

	/**
	 * @param \AIMultilingual\Translation\RenderResult $result Render result.
	 * @param string                                   $event  Event name.
	 */
	private function has_event( \AIMultilingual\Translation\RenderResult $result, string $event ): bool {
		foreach ( $result->events as $record ) {
			if ( ( $record['event'] ?? '' ) === $event ) {
				return true;
			}
		}

		return false;
	}
}
