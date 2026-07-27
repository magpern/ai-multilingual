<?php
/**
 * Strategy F block tree walker.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\BlockTreeWalker;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F block tree walker tests.
 */
final class BlockTreeWalkerTest extends TestCase {

	public function test_collects_blocks_in_depth_first_order(): void {
		$blocks = array(
			$this->block( 'core/group', '<div></div>', array( $this->block( 'core/paragraph', '<p>A</p>' ), $this->block( 'core/heading', '<h2>B</h2>' ) ) ),
			$this->block( 'core/button', '<div class="wp-block-button"><a>B</a></div>' ),
		);

		$names = array_map(
			static fn( array $block ): string => (string) $block['blockName'],
			( new BlockTreeWalker() )->collect( $blocks )
		);

		$this->assertSame(
			array( 'core/group', 'core/paragraph', 'core/heading', 'core/button' ),
			$names
		);
	}

	public function test_skips_freeform_chunks(): void {
		$blocks = array(
			array(
				'blockName'    => null,
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
			$this->block( 'core/paragraph', '<p>Only</p>' ),
		);

		$collected = ( new BlockTreeWalker() )->collect( $blocks );

		$this->assertCount( 1, $collected );
		$this->assertSame( 'core/paragraph', $collected[0]['blockName'] );
	}

	/**
	 * Builds a minimal block array for walker tests.
	 *
	 * @param string                     $name         Block name.
	 * @param string                     $inner_html   Inner HTML.
	 * @param list<array<string, mixed>> $inner_blocks Nested blocks.
	 * @return array<string, mixed>
	 */
	private function block( string $name, string $inner_html, array $inner_blocks = array() ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => array(),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => $inner_html,
			'innerContent' => array( $inner_html ),
		);
	}
}
