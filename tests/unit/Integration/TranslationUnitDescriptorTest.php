<?php
/**
 * TranslationUnitDescriptor public factory tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\TranslationUnitDescriptor
 */
final class TranslationUnitDescriptorTest extends TestCase {

	public function test_from_source_creates_plain_descriptor_with_canonical_hash(): void {
		$descriptor = TranslationUnitDescriptor::from_source(
			'p:vendor:item:15:title',
			'Hello world',
			Contract::FORMAT_PLAIN,
			Contract::OWNERSHIP_RECORD,
			'item',
			'15',
			'title',
			'Item title',
			'vendor_plugin'
		);

		$this->assertSame( 'p:vendor:item:15:title', $descriptor->segment_key );
		$this->assertSame( 'Hello world', $descriptor->source_text );
		$this->assertSame( Contract::FORMAT_PLAIN, $descriptor->text_format );
		$this->assertSame(
			Store::source_hash( 'Hello world', Store::FORMAT_PLAIN ),
			$descriptor->source_hash
		);
	}

	public function test_from_source_creates_html_descriptor_with_canonical_hash(): void {
		$descriptor = TranslationUnitDescriptor::from_source(
			'p:vendor:item:15:body',
			'<strong>Hello</strong>',
			Contract::FORMAT_HTML,
			Contract::OWNERSHIP_RECORD,
			'item',
			'15',
			'body',
			'Item body',
			'vendor_plugin'
		);

		$this->assertSame( Contract::FORMAT_HTML, $descriptor->text_format );
		$this->assertSame(
			Store::source_hash( '<strong>Hello</strong>', Store::FORMAT_HTML ),
			$descriptor->source_hash
		);
	}

	public function test_from_source_matches_constructor_shape_for_equivalent_inputs(): void {
		$factory = TranslationUnitDescriptor::from_source(
			'p:vendor:item:15:title',
			'Hello world',
			Contract::FORMAT_PLAIN,
			Contract::OWNERSHIP_RECORD,
			'item',
			'15',
			'title',
			'Item title',
			'vendor_plugin',
			'context'
		);

		$legacy = new TranslationUnitDescriptor(
			'p:vendor:item:15:title',
			'Hello world',
			Store::source_hash( 'Hello world', Store::FORMAT_PLAIN ),
			Contract::FORMAT_PLAIN,
			Contract::OWNERSHIP_RECORD,
			'item',
			'15',
			'title',
			'Item title',
			'vendor_plugin',
			'context'
		);

		$this->assertEquals( $legacy, $factory );
		$this->assertSame( $legacy->to_segment_array( 10 ), $factory->to_segment_array( 10 ) );
	}

	public function test_from_source_rejects_unsupported_format(): void {
		$this->expectException( \InvalidArgumentException::class );
		TranslationUnitDescriptor::from_source(
			'p:vendor:item:15:title',
			'Hello world',
			'json',
			Contract::OWNERSHIP_RECORD,
			'item',
			'15',
			'title',
			'Item title',
			'vendor_plugin'
		);
	}

	public function test_from_source_rejects_invalid_required_arguments(): void {
		$this->expectException( \InvalidArgumentException::class );
		TranslationUnitDescriptor::from_source(
			'',
			'Hello world',
			Contract::FORMAT_PLAIN,
			Contract::OWNERSHIP_RECORD,
			'item',
			'15',
			'title',
			'Item title',
			'vendor_plugin'
		);
	}
}
