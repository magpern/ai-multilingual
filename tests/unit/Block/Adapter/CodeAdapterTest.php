<?php
/**
 * F14 code adapter admission tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block\Adapter;

use AIMultilingual\Block\Adapter\CodeAdapter;
use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Block\Adapter\CodeAdapter
 */
final class CodeAdapterTest extends TestCase {

	private const UUID = '550e8400-e29b-41d4-a716-4466554400d4';

	public function test_extract_and_apply_preserves_code_wrapper(): void {
		$adapter = new CodeAdapter();
		$block   = array(
			'blockName'    => 'core/code',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<pre class="wp-block-code"><code>echo 1;</code></pre>',
			'innerContent' => array( '<pre class="wp-block-code"><code>echo 1;</code></pre>' ),
		);

		$fields = $adapter->extract_fields( $block );
		$this->assertCount( 1, $fields );
		$this->assertStringContainsString( 'echo 1;', $fields[0]->source_text );

		$updated = $adapter->apply_translation( $block, Contract::FIELD_CONTENT, 'echo 2;' );
		$this->assertStringContainsString( 'echo 2;', $updated['innerHTML'] );
		$this->assertStringContainsString( '<code>', $updated['innerHTML'] );
		$this->assertTrue( $adapter->validate_block_structure( $updated )->valid );
	}

	public function test_registered_and_allowlisted(): void {
		$registry = new AdapterRegistry();
		$this->assertInstanceOf( CodeAdapter::class, $registry->get( 'core/code' ) );
		$this->assertContains( 'core/code', BlockRegistry::SUPPORTED_BLOCKS );
	}
}
