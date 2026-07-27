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
		$this->assertSame(
			array( 'core/paragraph', 'core/heading', 'core/button' ),
			BlockRegistry::SUPPORTED_BLOCKS
		);
	}

	public function test_supported_blocks_are_eligible(): void {
		foreach ( BlockRegistry::SUPPORTED_BLOCKS as $block_name ) {
			$this->assertTrue( $this->registry->is_supported( $block_name ) );
			$this->assertTrue(
				$this->registry->is_eligible(
					array(
						'blockName' => $block_name,
					)
				)
			);
		}
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

	public function test_adapter_lookup_is_not_implemented_in_f1(): void {
		$this->assertNull( $this->registry->get_adapter( 'core/paragraph' ) );
	}
}
