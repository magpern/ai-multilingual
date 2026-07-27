<?php
/**
 * Strategy F block extraction.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\SourceNormalizer;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * BlockExtractor segment production and stability.
 */
final class BlockExtractorTest extends TestCase {

	private const UUID_A = '550e8400-e29b-41d4-a716-446655440000';
	private const UUID_B = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
	private const UUID_C = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

	private BlockExtractor $extractor;

	protected function setUp(): void {
		parent::setUp();

		$this->extractor = new BlockExtractor(
			new AdapterRegistry(),
			new BlockRegistry( new AdapterRegistry() ),
			new BlockExtractionLogger()
		);
	}

	public function test_paragraph_extraction_produces_stable_segment(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'Hello', self::UUID_A ),
			)
		);

		$key = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
		$this->assertArrayHasKey( $key, $segments );
		$this->assertSame( 'core/paragraph', $segments[ $key ]['block_name'] );
		$this->assertSame( '<p>Hello</p>', $segments[ $key ]['source_text'] );
		$this->assertSame( Store::KIND_BLOCK, $segments[ $key ]['segment_kind'] );
	}

	public function test_heading_extraction(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->block(
					'core/heading',
					'<h2>Title</h2>',
					self::UUID_B
				),
			)
		);

		$key = SegmentKey::build( self::UUID_B, Contract::FIELD_CONTENT );
		$this->assertArrayHasKey( $key, $segments );
		$this->assertSame( 'core/heading', $segments[ $key ]['block_name'] );
	}

	public function test_button_extraction(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->block(
					'core/button',
					'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Click</a></div>',
					self::UUID_C
				),
			)
		);

		$key = SegmentKey::build( self::UUID_C, Contract::FIELD_CONTENT );
		$this->assertArrayHasKey( $key, $segments );
		$this->assertSame( 'core/button', $segments[ $key ]['block_name'] );
	}

	public function test_nested_container_traverses_children(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				array(
					'blockName'    => 'core/group',
					'attrs'        => array(),
					'innerHTML'    => '<div class="wp-block-group"></div>',
					'innerBlocks'  => array(
						$this->paragraph( 'Inside group', self::UUID_A ),
						$this->block( 'core/heading', '<h2>Nested</h2>', self::UUID_B ),
					),
					'innerContent' => array( '<div class="wp-block-group">', null, null, '</div>' ),
				),
			)
		);

		$this->assertCount( 2, $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT ), $segments );
		$this->assertArrayHasKey( SegmentKey::build( self::UUID_B, Contract::FIELD_CONTENT ), $segments );
	}

	public function test_unsupported_blocks_are_ignored(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->block(
					'core/quote',
					'<blockquote class="wp-block-quote"><p>Quote</p></blockquote>',
					self::UUID_A
				),
			)
		);

		$this->assertSame( array(), $segments );
	}

	public function test_dynamic_blocks_are_ignored(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->block(
					'core/latest-posts',
					'<ul><li>Post</li></ul>',
					self::UUID_A
				),
			)
		);

		$this->assertSame( array(), $segments );
	}

	public function test_multiple_segments_have_distinct_keys_and_order(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'One', self::UUID_A ),
				$this->paragraph( 'Two', self::UUID_B ),
			)
		);

		$this->assertCount( 2, $segments );
		$this->assertSame( 0, $segments[ SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT ) ]['segment_order'] );
		$this->assertSame( 1, $segments[ SegmentKey::build( self::UUID_B, Contract::FIELD_CONTENT ) ]['segment_order'] );
	}

	public function test_identical_document_extracts_identical_segments(): void {
		$tree = array(
			$this->paragraph( 'Stable', self::UUID_A ),
			$this->block( 'core/heading', '<h2>Heading</h2>', self::UUID_B ),
		);

		$first  = $this->extractor->extract_blocks( $tree );
		$second = $this->extractor->extract_blocks( $tree );

		$this->assertSame( $first, $second );
	}

	public function test_uuid_change_only_affects_segment_key(): void {
		$first  = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'Same text', self::UUID_A ),
			)
		);
		$second = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'Same text', self::UUID_B ),
			)
		);

		$key_a = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
		$key_b = SegmentKey::build( self::UUID_B, Contract::FIELD_CONTENT );

		$this->assertSame( $first[ $key_a ]['source_hash'], $second[ $key_b ]['source_hash'] );
		$this->assertNotSame( $key_a, $key_b );
	}

	public function test_text_change_only_affects_source_hash(): void {
		$key = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );

		$first  = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'Version one', self::UUID_A ),
			)
		);
		$second = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'Version two', self::UUID_A ),
			)
		);

		$this->assertSame( $key, array_key_first( $first ) );
		$this->assertNotSame( $first[ $key ]['source_hash'], $second[ $key ]['source_hash'] );
	}

	public function test_missing_uuid_skips_extraction(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'No UUID', '' ),
			)
		);

		$this->assertSame( array(), $segments );
	}

	public function test_no_duplicate_segment_identities(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'First', self::UUID_A ),
				$this->paragraph( 'Second', self::UUID_A ),
			)
		);

		$this->assertCount( 1, $segments );
	}

	public function test_segment_keys_use_frozen_grammar(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->paragraph( 'Grammar', self::UUID_A ),
			)
		);

		$key = array_key_first( $segments );
		$this->assertSame( 'b:' . self::UUID_A . ':content', $key );
	}

	public function test_normalized_source_and_hash_are_populated(): void {
		$segments = $this->extractor->extract_blocks(
			array(
				$this->paragraph( "Line\r\n", self::UUID_A ),
			)
		);

		$key     = SegmentKey::build( self::UUID_A, Contract::FIELD_CONTENT );
		$segment = $segments[ $key ];

		$this->assertSame(
			SourceNormalizer::normalize( '<p>Line' . "\r\n" . '</p>', Store::FORMAT_HTML ),
			$segment['normalized_source']
		);
		$this->assertSame(
			SourceNormalizer::source_hash( '<p>Line' . "\r\n" . '</p>', Store::FORMAT_HTML ),
			$segment['source_hash']
		);
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
	 * @param string $name       Block name.
	 * @param string $inner_html Inner HTML.
	 * @param string $uuid       Block UUID.
	 * @return array<string, mixed>
	 */
	private function block( string $name, string $inner_html, string $uuid ): array {
		$attrs = array();
		if ( '' !== $uuid ) {
			$attrs[ Contract::ATTR_NAME ] = $uuid;
		}

		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);
	}
}
