<?php
/**
 * TSC.4 performance regression for deeply nested block trees.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\BlockRenderer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Translation\BlockRenderer
 */
final class Tsc4PerformanceRegressionTest extends TestCase {

	public function test_hundred_block_nested_fixture_renders_without_timeout(): void {
		$blocks       = array();
		$translations = array();

		for ( $i = 0; $i < 100; ++$i ) {
			$uuid     = sprintf( '11111111-1111-4111-8111-%012d', $i );
			$blocks[] = array(
				'blockName'    => 'core/group',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array( Contract::ATTR_NAME => $uuid ),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Source ' . $i . '</p>',
						'innerContent' => array( '<p>Source ' . $i . '</p>' ),
					),
				),
				'innerHTML'    => '<div class="wp-block-group"></div>',
				'innerContent' => array( '<div class="wp-block-group">', null, '</div>' ),
			);
			$translations[ SegmentKey::build( $uuid, Contract::FIELD_CONTENT ) ] = 'Mal ' . $i;
		}

		$renderer = new BlockRenderer( new AdapterRegistry(), new BlockRenderLogger() );
		$start    = microtime( true );
		$result   = $renderer->render( $blocks, $translations );
		$elapsed  = microtime( true ) - $start;

		$this->assertTrue( $result->changed );
		$this->assertSame( '<p>Mal 99</p>', $blocks[99]['innerBlocks'][0]['innerHTML'] );
		$this->assertLessThan( 2.0, $elapsed, '100-block nested render should stay bounded.' );
	}
}
