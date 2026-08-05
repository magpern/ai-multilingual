<?php
/**
 * F14 preformatted adapter admission tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block\Adapter;

use AIMultilingual\Block\Adapter\PreformattedAdapter;
use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Block\Adapter\PreformattedAdapter
 */
final class PreformattedAdapterTest extends TestCase {

	private const UUID = '550e8400-e29b-41d4-a716-4466554400d2';

	public function test_extract_and_apply_preserves_pre_wrapper(): void {
		$adapter = new PreformattedAdapter();
		$block   = array(
			'blockName'    => 'core/preformatted',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<pre class="wp-block-preformatted">Code-ish</pre>',
			'innerContent' => array( '<pre class="wp-block-preformatted">Code-ish</pre>' ),
		);

		$fields = $adapter->extract_fields( $block );
		$this->assertCount( 1, $fields );
		$this->assertStringContainsString( 'Code-ish', $fields[0]->source_text );

		$updated = $adapter->apply_translation( $block, Contract::FIELD_CONTENT, 'Kod-ish' );
		$this->assertStringContainsString( 'Kod-ish', $updated['innerHTML'] );
		$this->assertStringContainsString( '<pre', $updated['innerHTML'] );
		$this->assertTrue( $adapter->validate_block_structure( $updated )->valid );
	}

	public function test_registered_and_allowlisted(): void {
		$registry = new AdapterRegistry();
		$this->assertInstanceOf( PreformattedAdapter::class, $registry->get( 'core/preformatted' ) );
		$this->assertContains( 'core/preformatted', BlockRegistry::SUPPORTED_BLOCKS );
	}
}
