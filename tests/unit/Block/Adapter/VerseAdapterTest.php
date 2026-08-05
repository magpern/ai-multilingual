<?php
/**
 * F14 verse adapter admission tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block\Adapter;

use AIMultilingual\Block\Adapter\VerseAdapter;
use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Block\Adapter\VerseAdapter
 */
final class VerseAdapterTest extends TestCase {

	private const UUID = '550e8400-e29b-41d4-a716-4466554400d3';

	public function test_extract_and_apply_preserves_pre_wrapper(): void {
		$adapter = new VerseAdapter();
		$block   = array(
			'blockName'    => 'core/verse',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<pre class="wp-block-verse">Line one</pre>',
			'innerContent' => array( '<pre class="wp-block-verse">Line one</pre>' ),
		);

		$fields = $adapter->extract_fields( $block );
		$this->assertCount( 1, $fields );

		$updated = $adapter->apply_translation( $block, Contract::FIELD_CONTENT, 'Rad ett' );
		$this->assertStringContainsString( 'Rad ett', $updated['innerHTML'] );
		$this->assertStringContainsString( 'wp-block-verse', $updated['innerHTML'] );
		$this->assertTrue( $adapter->validate_block_structure( $updated )->valid );
	}

	public function test_registered_and_allowlisted(): void {
		$registry = new AdapterRegistry();
		$this->assertInstanceOf( VerseAdapter::class, $registry->get( 'core/verse' ) );
		$this->assertContains( 'core/verse', BlockRegistry::SUPPORTED_BLOCKS );
	}
}
