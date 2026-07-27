<?php
/**
 * Strategy F block adapters.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block\Adapter;

use AIMultilingual\Block\Adapter\ButtonAdapter;
use AIMultilingual\Block\Adapter\HeadingAdapter;
use AIMultilingual\Block\Adapter\ParagraphAdapter;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\SourceNormalizer;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Production adapters for paragraph, heading, and button blocks.
 */
final class BlockAdapterTest extends TestCase {

	private const UUID = '550e8400-e29b-41d4-a716-446655440000';

	public function test_paragraph_extracts_inner_html_content(): void {
		$adapter = new ParagraphAdapter();
		$block   = $this->block(
			'core/paragraph',
			'<p>Hello world</p>',
			array( Contract::ATTR_NAME => self::UUID )
		);

		$fields = $adapter->extract_fields( $block );

		$this->assertCount( 1, $fields );
		$this->assertSame( Contract::FIELD_CONTENT, $fields[0]->field_id );
		$this->assertSame( '<p>Hello world</p>', $fields[0]->source_text );
		$this->assertSame( Store::FORMAT_HTML, $fields[0]->text_format );
	}

	public function test_heading_extracts_inner_html_content(): void {
		$adapter = new HeadingAdapter();
		$block   = $this->block(
			'core/heading',
			'<h2>Section title</h2>',
			array( Contract::ATTR_NAME => self::UUID )
		);

		$fields = $adapter->extract_fields( $block );

		$this->assertCount( 1, $fields );
		$this->assertSame( '<h2>Section title</h2>', $fields[0]->source_text );
	}

	public function test_button_extracts_inner_html_content(): void {
		$adapter = new ButtonAdapter();
		$block   = $this->block(
			'core/button',
			'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com">Buy now</a></div>',
			array( Contract::ATTR_NAME => self::UUID )
		);

		$fields = $adapter->extract_fields( $block );

		$this->assertCount( 1, $fields );
		$this->assertStringContainsString( 'Buy now', $fields[0]->source_text );
	}

	public function test_adapters_build_segment_keys_via_helper(): void {
		$adapter = new ParagraphAdapter();
		$key     = $adapter->get_segment_key( self::UUID, Contract::FIELD_CONTENT );

		$this->assertSame(
			SegmentKey::build( self::UUID, Contract::FIELD_CONTENT ),
			$key
		);
	}

	public function test_identical_content_produces_identical_hash(): void {
		$html = '<p>Stable text</p>';
		$hash = SourceNormalizer::source_hash( $html, Store::FORMAT_HTML );

		$this->assertSame( $hash, SourceNormalizer::source_hash( $html, Store::FORMAT_HTML ) );
	}

	public function test_changed_content_produces_changed_hash(): void {
		$first  = SourceNormalizer::source_hash( '<p>One</p>', Store::FORMAT_HTML );
		$second = SourceNormalizer::source_hash( '<p>Two</p>', Store::FORMAT_HTML );

		$this->assertNotSame( $first, $second );
	}

	public function test_whitespace_normalization_is_deterministic_for_html(): void {
		$raw    = "<p>Line one\r\nLine two&nbsp;here</p>\r\n";
		$first  = SourceNormalizer::normalize( $raw, Store::FORMAT_HTML );
		$second = SourceNormalizer::normalize( str_replace( "\r\n", "\n", $raw ), Store::FORMAT_HTML );

		$this->assertSame( $first, $second );
		$this->assertSame(
			SourceNormalizer::source_hash( $raw, Store::FORMAT_HTML ),
			SourceNormalizer::source_hash( str_replace( "\r\n", "\n", $raw ), Store::FORMAT_HTML )
		);
	}

	/**
	 * @param string               $name      Block name.
	 * @param string               $inner_html Inner HTML.
	 * @param array<string, mixed> $attrs     Block attributes.
	 * @return array<string, mixed>
	 */
	private function block( string $name, string $inner_html, array $attrs = array() ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);
	}
}
