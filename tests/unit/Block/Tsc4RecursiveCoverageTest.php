<?php
/**
 * TSC.4 recursive coverage characterization for structural/container blocks.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\BlockRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Gallery, media-text, cover, buttons, columns, group, list recursion coverage.
 *
 * @covers \AIMultilingual\Block\BlockRegistry
 * @covers \AIMultilingual\Translation\BlockExtractor
 */
final class Tsc4RecursiveCoverageTest extends TestCase {

	private const UUID_P1  = '11111111-1111-4111-8111-111111111111';
	private const UUID_P2  = '22222222-2222-4222-8222-222222222222';
	private const UUID_BTN = '33333333-3333-4333-8333-333333333333';
	private const UUID_IMG = '44444444-4444-4444-8444-444444444444';
	private const UUID_CAP = '55555555-5555-4555-8555-555555555555';

	private BlockExtractor $extractor;
	private BlockRenderer $renderer;
	private BlockRegistry $registry;

	protected function setUp(): void {
		parent::setUp();

		$adapters        = new AdapterRegistry();
		$this->registry  = new BlockRegistry( $adapters );
		$this->extractor = new BlockExtractor( $adapters, $this->registry, new BlockExtractionLogger() );
		$this->renderer  = new BlockRenderer( $adapters, new BlockRenderLogger() );
	}

	public function test_gallery_children_extract_without_parent_units(): void {
		$blocks   = array(
			$this->container(
				'core/gallery',
				array(
					$this->image_with_caption( 'Photo one', 'Cap one', self::UUID_IMG, self::UUID_CAP ),
					$this->paragraph( 'Gallery note', self::UUID_P1 ),
				)
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertArrayHasKey( SegmentKey::build( self::UUID_CAP, Contract::FIELD_CAPTION ), $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_P1, Contract::FIELD_CONTENT ), $segments );
		$this->assertSame( array(), $this->units_for_names( $segments, array( 'core/gallery' ) ) );
	}

	public function test_buttons_container_extracts_button_label_only(): void {
		$blocks   = array(
			$this->container(
				'core/buttons',
				array(
					$this->button( 'Click me', self::UUID_BTN, 'https://example.com/page' ),
				)
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 1, $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_BTN, Contract::FIELD_CONTENT ), $segments );
		$this->assertSame( array(), $this->units_for_names( $segments, array( 'core/buttons' ) ) );
	}

	public function test_cover_and_media_text_children_without_host_duplication(): void {
		$blocks   = array(
			$this->container(
				'core/cover',
				array( $this->heading( 'Cover title', self::UUID_P1 ) )
			),
			$this->container(
				'core/media-text',
				array( $this->paragraph( 'Media body', self::UUID_P2 ) )
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 2, $segments );
		$this->assertSame(
			array(),
			$this->units_for_names( $segments, array( 'core/cover', 'core/media-text' ) )
		);
	}

	public function test_render_nested_gallery_caption_without_parent_overlay(): void {
		$blocks = array(
			$this->container(
				'core/gallery',
				array( $this->image_with_caption( 'Photo', 'Caption EN', self::UUID_IMG, self::UUID_CAP ) )
			),
		);
		$key    = SegmentKey::build( self::UUID_CAP, Contract::FIELD_CAPTION );

		$this->renderer->render( $blocks, array( $key => 'Bildtext SV' ) );

		$this->assertStringContainsString( 'Bildtext SV', $blocks[0]['innerBlocks'][0]['innerHTML'] );
		$this->assertStringNotContainsString( 'Caption EN', $blocks[0]['innerHTML'] );
	}

	/**
	 * @param array<string, mixed> $segments Extracted segments.
	 * @param array<string>        $names    Block names that must not appear as units.
	 * @return list<string>
	 */
	private function units_for_names( array $segments, array $names ): array {
		$found = array();
		foreach ( $segments as $segment ) {
			$block_name = (string) ( $segment['block_name'] ?? '' );
			if ( in_array( $block_name, $names, true ) ) {
				$found[] = $block_name;
			}
		}

		return $found;
	}

	/**
	 * @param string                     $name   Block name.
	 * @param list<array<string, mixed>> $inner  Inner blocks.
	 * @return array<string, mixed>
	 */
	private function container( string $name, array $inner ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => array(),
			'innerBlocks'  => $inner,
			'innerHTML'    => '<div></div>',
			'innerContent' => array( '<div>', null, '</div>' ),
		);
	}

	/**
	 * @param string $text Inner text.
	 * @param string $uuid Block UUID.
	 * @return array<string, mixed>
	 */
	private function paragraph( string $text, string $uuid ): array {
		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array( Contract::ATTR_NAME => $uuid ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<p>' . $text . '</p>',
			'innerContent' => array( '<p>' . $text . '</p>' ),
		);
	}

	/**
	 * @param string $text Inner text.
	 * @param string $uuid Block UUID.
	 * @return array<string, mixed>
	 */
	private function heading( string $text, string $uuid ): array {
		return array(
			'blockName'    => 'core/heading',
			'attrs'        => array(
				Contract::ATTR_NAME => $uuid,
				'level'             => 2,
			),
			'innerBlocks'  => array(),
			'innerHTML'    => '<h2>' . $text . '</h2>',
			'innerContent' => array( '<h2>' . $text . '</h2>' ),
		);
	}

	/**
	 * @param string $label Button label.
	 * @param string $uuid  Block UUID.
	 * @param string $href  Link href.
	 * @return array<string, mixed>
	 */
	private function button( string $label, string $uuid, string $href ): array {
		return array(
			'blockName'    => 'core/button',
			'attrs'        => array(
				Contract::ATTR_NAME => $uuid,
				'url'               => $href,
			),
			'innerBlocks'  => array(),
			'innerHTML'    => '<div class="wp-block-button"><a class="wp-block-button__link" href="' . $href . '">' . $label . '</a></div>',
			'innerContent' => array(
				'<div class="wp-block-button"><a class="wp-block-button__link" href="' . $href . '">' . $label . '</a></div>',
			),
		);
	}

	/**
	 * @param string $alt    Image alt text (unused in caption path).
	 * @param string $caption Caption text.
	 * @param string $img_uuid Image block UUID (unused when caption-only).
	 * @param string $cap_uuid Caption segment UUID host.
	 * @return array<string, mixed>
	 */
	private function image_with_caption( string $alt, string $caption, string $img_uuid, string $cap_uuid ): array {
		unset( $alt, $img_uuid );

		return array(
			'blockName'    => 'core/image',
			'attrs'        => array( Contract::ATTR_NAME => $cap_uuid ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<figure class="wp-block-image"><img alt=""/><figcaption>' . $caption . '</figcaption></figure>',
			'innerContent' => array(
				'<figure class="wp-block-image"><img alt=""/><figcaption>' . $caption . '</figcaption></figure>',
			),
		);
	}
}
