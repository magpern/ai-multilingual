<?php
/**
 * Strategy F block registry allowlist.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F block registry allowlist.
 */
final class BlockRegistryTest extends TestCase {

	private BlockRegistry $registry;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = new BlockRegistry();
	}

	public function test_initial_allowlist_contains_proof_blocks(): void {
		$this->assertContains( 'core/paragraph', BlockRegistry::SUPPORTED_BLOCKS );
		$this->assertContains( 'core/heading', BlockRegistry::SUPPORTED_BLOCKS );
		$this->assertContains( 'core/button', BlockRegistry::SUPPORTED_BLOCKS );
		$this->assertContains( 'core/list-item', BlockRegistry::SUPPORTED_BLOCKS );
		$this->assertContains( 'core/preformatted', BlockRegistry::SUPPORTED_BLOCKS );
		$this->assertContains( 'core/verse', BlockRegistry::SUPPORTED_BLOCKS );
		$this->assertContains( 'core/code', BlockRegistry::SUPPORTED_BLOCKS );
	}

	public function test_supported_blocks_are_eligible_leaves(): void {
		foreach ( BlockRegistry::SUPPORTED_BLOCKS as $block_name ) {
			$this->assertTrue( $this->registry->is_supported( $block_name ) );
			$this->assertTrue(
				$this->registry->is_eligible(
					array(
						'blockName'   => $block_name,
						'innerBlocks' => array(),
						'innerHTML'   => '<p>Text</p>',
					)
				)
			);
		}
	}

	public function test_containers_and_dynamic_blocks_are_ineligible(): void {
		$this->assertFalse(
			$this->registry->is_eligible(
				array(
					'blockName'   => 'core/group',
					'innerBlocks' => array(
						array(
							'blockName' => 'core/paragraph',
						),
					),
					'innerHTML'   => '<div></div>',
				)
			)
		);

		$this->assertFalse(
			$this->registry->is_eligible(
				array(
					'blockName'   => 'core/block',
					'innerBlocks' => array(),
					'innerHTML'   => '<p>Text</p>',
				)
			)
		);
	}

	public function test_unsupported_blocks_are_rejected(): void {
		$this->assertFalse( $this->registry->is_supported( 'core/group' ) );
		$this->assertFalse(
			$this->registry->is_eligible(
				array(
					'blockName' => 'core/group',
				)
			)
		);
	}

	public function test_content_field_is_supported_for_allowlisted_blocks(): void {
		foreach ( BlockRegistry::SUPPORTED_BLOCKS as $block_name ) {
			$this->assertTrue( $this->registry->supports_field( $block_name, Contract::FIELD_CONTENT ) );
			$this->assertSame( array( 'content' ), $this->registry->get_supported_fields( $block_name ) );
		}
	}

	public function test_adapter_lookup_returns_production_adapters(): void {
		$registry = new BlockRegistry( new \AIMultilingual\Block\AdapterRegistry() );

		$this->assertInstanceOf(
			\AIMultilingual\Block\Adapter\ParagraphAdapter::class,
			$registry->get_adapter( 'core/paragraph' )
		);
		$this->assertNull( $registry->get_adapter( 'core/group' ) );
	}

	public function test_structural_and_host_classification(): void {
		$this->assertTrue( $this->registry->is_structural_transparent( 'core/group' ) );
		$this->assertTrue( $this->registry->is_structural_transparent( 'core/list' ) );
		$this->assertTrue( $this->registry->is_child_traversal_host( 'core/quote' ) );
		$this->assertTrue( $this->registry->is_child_traversal_host( 'core/cover' ) );
		$this->assertFalse( $this->registry->is_structural_transparent( 'core/paragraph' ) );
		$this->assertFalse( $this->registry->is_child_traversal_host( 'core/list-item' ) );
	}

	public function test_supported_leaf_with_inner_blocks_is_ineligible(): void {
		$this->assertFalse(
			$this->registry->is_eligible(
				array(
					'blockName'   => 'core/list-item',
					'innerBlocks' => array(
						array( 'blockName' => 'core/list' ),
					),
					'innerHTML'   => '<li>Parent</li>',
				)
			)
		);
	}
}
