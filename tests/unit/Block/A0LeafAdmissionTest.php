<?php
/**
 * A.0 Wave 1–2 Gutenberg leaf/field admission tests.
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
 * Quote citation, details summary, pullquote, media captions, file labels.
 *
 * @covers \AIMultilingual\Block\Adapter\QuoteAdapter
 * @covers \AIMultilingual\Block\Adapter\DetailsAdapter
 * @covers \AIMultilingual\Block\Adapter\PullquoteAdapter
 * @covers \AIMultilingual\Block\Adapter\ImageAdapter
 * @covers \AIMultilingual\Block\Adapter\FileAdapter
 * @covers \AIMultilingual\Block\Adapter\AudioAdapter
 * @covers \AIMultilingual\Block\Adapter\VideoAdapter
 */
final class A0LeafAdmissionTest extends TestCase {

	private const UUID_QUOTE = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
	private const UUID_CHILD = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbb1';
	private const UUID_DET   = 'cccccccc-cccc-4ccc-8ccc-ccccccccccc1';
	private const UUID_PQ    = 'dddddddd-dddd-4ddd-8ddd-ddddddddddd1';
	private const UUID_IMG   = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeee1';
	private const UUID_FILE  = 'ffffffff-ffff-4fff-8fff-fffffffffff1';

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

	public function test_quote_citation_and_child_extract_without_duplicate(): void {
		$blocks   = array( $this->quote_with_citation( 'Quoted body', 'Author Cite', self::UUID_CHILD, self::UUID_QUOTE ) );
		$segments = $this->extractor->extract_blocks( $blocks );

		$citation_key = SegmentKey::build( self::UUID_QUOTE, Contract::FIELD_CITATION );
		$child_key    = SegmentKey::build( self::UUID_CHILD, Contract::FIELD_CONTENT );

		$this->assertCount( 2, $segments );
		$this->assertArrayHasKey( $citation_key, $segments );
		$this->assertArrayHasKey( $child_key, $segments );
		$this->assertSame( 'Author Cite', $segments[ $citation_key ]['source_text'] );
		$this->assertTrue( $this->registry->is_eligible( $blocks[0] ) );
	}

	public function test_quote_citation_render_preserves_inner_blocks(): void {
		$blocks = array( $this->quote_with_citation( 'Quoted body', 'Author Cite', self::UUID_CHILD, self::UUID_QUOTE ) );
		$map    = array(
			SegmentKey::build( self::UUID_QUOTE, Contract::FIELD_CITATION ) => 'Citerad författare',
			SegmentKey::build( self::UUID_CHILD, Contract::FIELD_CONTENT )  => 'Citerad text',
		);

		$this->renderer->render( $blocks, $map );

		$this->assertSame( '<p>Citerad text</p>', trim( $blocks[0]['innerBlocks'][0]['innerHTML'] ) );
		$this->assertStringContainsString( 'Citerad författare', (string) $blocks[0]['innerHTML'] );
		$this->assertCount( 3, $blocks[0]['innerContent'] );
		$this->assertNull( $blocks[0]['innerContent'][1] );
	}

	public function test_details_summary_and_child_extract(): void {
		$blocks   = array( $this->details_with_summary( 'Body', 'Summary text', self::UUID_CHILD, self::UUID_DET ) );
		$segments = $this->extractor->extract_blocks( $blocks );
		$key      = SegmentKey::build( self::UUID_DET, Contract::FIELD_SUMMARY );

		$this->assertCount( 2, $segments );
		$this->assertArrayHasKey( $key, $segments );
		$this->assertSame( 'Summary text', $segments[ $key ]['source_text'] );
	}

	public function test_pullquote_leaf_body_and_citation(): void {
		$html     = '<figure class="wp-block-pullquote"><blockquote><p>Body</p><cite>Cite</cite></blockquote></figure>';
		$blocks   = array(
			array(
				'blockName'    => 'core/pullquote',
				'attrs'        => array( Contract::ATTR_NAME => self::UUID_PQ ),
				'innerBlocks'  => array(),
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertArrayHasKey( SegmentKey::build( self::UUID_PQ, Contract::FIELD_CONTENT ), $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_PQ, Contract::FIELD_CITATION ), $segments );
	}

	public function test_pullquote_with_nested_child_without_citation_extracts_child_only(): void {
		$blocks   = array(
			array(
				'blockName'    => 'core/pullquote',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array( Contract::ATTR_NAME => self::UUID_CHILD ),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Nested</p>',
						'innerContent' => array( '<p>Nested</p>' ),
					),
				),
				'innerHTML'    => '<figure class="wp-block-pullquote"></figure>',
				'innerContent' => array( '<figure class="wp-block-pullquote">', null, '</figure>' ),
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertCount( 1, $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_CHILD, Contract::FIELD_CONTENT ), $segments );
		$this->assertFalse( $this->registry->is_eligible( $blocks[0] ) );
	}

	public function test_image_caption_extract_and_render(): void {
		$html     = '<figure class="wp-block-image"><img src="x.jpg" alt="ignore"/><figcaption>Cap</figcaption></figure>';
		$blocks   = array(
			array(
				'blockName'    => 'core/image',
				'attrs'        => array( Contract::ATTR_NAME => self::UUID_IMG ),
				'innerBlocks'  => array(),
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );
		$key      = SegmentKey::build( self::UUID_IMG, Contract::FIELD_CAPTION );

		$this->assertCount( 1, $segments );
		$this->assertSame( 'Cap', $segments[ $key ]['source_text'] );

		$map = array( $key => 'Bildtext' );
		$this->renderer->render( $blocks, $map );
		$this->assertStringContainsString( 'Bildtext', (string) $blocks[0]['innerHTML'] );
		$this->assertStringContainsString( 'alt="ignore"', (string) $blocks[0]['innerHTML'] );
	}

	public function test_file_labels_extract_from_attrs(): void {
		$html     = '<div class="wp-block-file"><a href="https://example.com/a.pdf">Report.pdf</a><a class="wp-block-file__button" download href="https://example.com/a.pdf">Download</a></div>';
		$blocks   = array(
			array(
				'blockName'    => 'core/file',
				'attrs'        => array(
					Contract::ATTR_NAME  => self::UUID_FILE,
					'fileName'           => 'Report.pdf',
					'downloadButtonText' => 'Download',
				),
				'innerBlocks'  => array(),
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			),
		);
		$segments = $this->extractor->extract_blocks( $blocks );

		$this->assertArrayHasKey( SegmentKey::build( self::UUID_FILE, Contract::FIELD_FILE_NAME ), $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_FILE, Contract::FIELD_DOWNLOAD_BUTTON_TEXT ), $segments );
	}

	public function test_audio_and_video_captions(): void {
		$cases = array(
			'core/audio' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa10',
			'core/video' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa11',
		);
		foreach ( $cases as $name => $uuid ) {
			$html     = '<figure class="wp-block-' . substr( $name, 5 ) . '"><figcaption>Media cap</figcaption></figure>';
			$blocks   = array(
				array(
					'blockName'    => $name,
					'attrs'        => array( Contract::ATTR_NAME => $uuid ),
					'innerBlocks'  => array(),
					'innerHTML'    => $html,
					'innerContent' => array( $html ),
				),
			);
			$segments = $this->extractor->extract_blocks( $blocks );
			$this->assertArrayHasKey( SegmentKey::build( $uuid, Contract::FIELD_CAPTION ), $segments );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function quote_with_citation( string $body, string $cite, string $child_uuid, string $host_uuid ): array {
		return array(
			'blockName'    => 'core/quote',
			'attrs'        => array( Contract::ATTR_NAME => $host_uuid ),
			'innerBlocks'  => array(
				array(
					'blockName'    => 'core/paragraph',
					'attrs'        => array( Contract::ATTR_NAME => $child_uuid ),
					'innerBlocks'  => array(),
					'innerHTML'    => '<p>' . $body . '</p>',
					'innerContent' => array( '<p>' . $body . '</p>' ),
				),
			),
			'innerHTML'    => '<blockquote class="wp-block-quote"><cite>' . $cite . '</cite></blockquote>',
			'innerContent' => array(
				'<blockquote class="wp-block-quote">',
				null,
				'<cite>' . $cite . '</cite></blockquote>',
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function details_with_summary( string $body, string $summary, string $child_uuid, string $host_uuid ): array {
		return array(
			'blockName'    => 'core/details',
			'attrs'        => array( Contract::ATTR_NAME => $host_uuid ),
			'innerBlocks'  => array(
				array(
					'blockName'    => 'core/paragraph',
					'attrs'        => array( Contract::ATTR_NAME => $child_uuid ),
					'innerBlocks'  => array(),
					'innerHTML'    => '<p>' . $body . '</p>',
					'innerContent' => array( '<p>' . $body . '</p>' ),
				),
			),
			'innerHTML'    => '<details class="wp-block-details"><summary>' . $summary . '</summary></details>',
			'innerContent' => array(
				'<details class="wp-block-details"><summary>' . $summary . '</summary>',
				null,
				'</details>',
			),
		);
	}
}
