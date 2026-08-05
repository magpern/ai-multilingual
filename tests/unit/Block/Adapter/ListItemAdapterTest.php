<?php
/**
 * F14 list-item adapter admission tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block\Adapter;

use AIMultilingual\Block\Adapter\ListItemAdapter;
use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Block\Adapter\ListItemAdapter
 */
final class ListItemAdapterTest extends TestCase {

	private const UUID = '550e8400-e29b-41d4-a716-4466554400d1';

	public function test_extracts_list_item_inner_html(): void {
		$adapter = new ListItemAdapter();
		$block   = array(
			'blockName'    => 'core/list-item',
			'attrs'        => array( Contract::ATTR_NAME => self::UUID ),
			'innerBlocks'  => array(),
			'innerHTML'    => '<li>Alpha</li>',
			'innerContent' => array( '<li>Alpha</li>' ),
		);

		$fields = $adapter->extract_fields( $block );

		$this->assertCount( 1, $fields );
		$this->assertSame( '<li>Alpha</li>', $fields[0]->source_text );
	}

	public function test_apply_translation_preserves_li_wrapper_and_attrs(): void {
		$adapter = new ListItemAdapter();
		$attrs   = array( Contract::ATTR_NAME => self::UUID );
		$block   = array(
			'blockName'    => 'core/list-item',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '<li>Alpha</li>',
			'innerContent' => array( '<li>Alpha</li>' ),
		);

		$updated = $adapter->apply_translation( $block, Contract::FIELD_CONTENT, 'Alfa' );

		$this->assertSame( $attrs, $updated['attrs'] );
		$this->assertSame( '<li>Alfa</li>', $updated['innerHTML'] );
		$this->assertTrue( $adapter->validate_block_structure( $updated )->valid );
	}

	public function test_rejects_nested_list_item_with_inner_blocks(): void {
		$adapter = new ListItemAdapter();
		$block   = array(
			'blockName'   => 'core/list-item',
			'attrs'       => array(),
			'innerBlocks' => array(
				array( 'blockName' => 'core/list' ),
			),
			'innerHTML'   => '<li>Nested</li>',
		);

		$this->assertFalse( $adapter->is_translatable_instance( $block ) );
		$this->assertFalse( $adapter->validate_block_structure( $block )->valid );
	}

	public function test_registered_and_allowlisted(): void {
		$registry = new AdapterRegistry();
		$this->assertInstanceOf( ListItemAdapter::class, $registry->get( 'core/list-item' ) );
		$this->assertContains( 'core/list-item', BlockRegistry::SUPPORTED_BLOCKS );
	}
}
