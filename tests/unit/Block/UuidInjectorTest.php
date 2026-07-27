<?php
/**
 * Strategy F UUID injector.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\BlockIdentityLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\UuidInjector;
use AIMultilingual\Block\UuidValidator;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F UUID injector tests.
 */
final class UuidInjectorTest extends TestCase {

	private UuidInjector $injector;

	protected function setUp(): void {
		parent::setUp();

		$this->injector = new UuidInjector( new BlockRegistry(), new BlockIdentityLogger() );
	}

	public function test_creates_uuid_for_eligible_block(): void {
		$blocks = array( $this->paragraph_block( 'Hello' ) );
		$result = $this->injector->inject_blocks( $blocks );

		$this->assertTrue( $result->changed );
		$this->assertSame( 1, $result->stats['uuid_created'] );
		$this->assertTrue(
			UuidValidator::is_valid_non_empty( (string) $blocks[0]['attrs'][ Contract::ATTR_NAME ] )
		);
	}

	public function test_second_pass_is_idempotent(): void {
		$blocks = array( $this->paragraph_block( 'Hello' ) );

		$this->injector->inject_blocks( $blocks );
		$uuid   = (string) $blocks[0]['attrs'][ Contract::ATTR_NAME ];
		$result = $this->injector->inject_blocks( $blocks );

		$this->assertFalse( $result->changed );
		$this->assertSame( $uuid, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( 1, $result->stats['uuid_preserved'] );
	}

	public function test_replaces_malformed_uuid(): void {
		$blocks = array(
			$this->paragraph_block(
				'Hello',
				array(
					Contract::ATTR_NAME => 'not-a-uuid',
				)
			),
		);

		$result = $this->injector->inject_blocks( $blocks );

		$this->assertTrue( $result->changed );
		$this->assertSame( 1, $result->stats['uuid_replaced_invalid'] );
		$this->assertTrue(
			UuidValidator::is_valid_non_empty( (string) $blocks[0]['attrs'][ Contract::ATTR_NAME ] )
		);
	}

	public function test_preserves_valid_uuid(): void {
		$uuid   = '550e8400-e29b-41d4-a716-446655440000';
		$blocks = array(
			$this->paragraph_block(
				'Hello',
				array(
					Contract::ATTR_NAME => $uuid,
				)
			),
		);

		$result = $this->injector->inject_blocks( $blocks );

		$this->assertFalse( $result->changed );
		$this->assertSame( $uuid, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
	}

	public function test_ignores_unsupported_block(): void {
		$blocks = array( $this->paragraph_block( 'Hello', array(), 'core/group' ) );
		$result = $this->injector->inject_blocks( $blocks );

		$this->assertFalse( $result->changed );
		$this->assertArrayNotHasKey( Contract::ATTR_NAME, $blocks[0]['attrs'] );
	}

	public function test_ignores_empty_leaf(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array( '' ),
			),
		);
		$result = $this->injector->inject_blocks( $blocks );

		$this->assertFalse( $result->changed );
		$this->assertArrayNotHasKey( Contract::ATTR_NAME, $blocks[0]['attrs'] );
	}

	public function test_detects_duplicate_uuid_without_repairing(): void {
		$uuid   = '550e8400-e29b-41d4-a716-446655440000';
		$blocks = array(
			$this->paragraph_block( 'One', array( Contract::ATTR_NAME => $uuid ) ),
			$this->paragraph_block( 'Two', array( Contract::ATTR_NAME => $uuid ) ),
		);

		$result = $this->injector->inject_blocks( $blocks );

		$this->assertSame( 1, $result->stats['uuid_duplicate_detected'] );
		$this->assertSame( $uuid, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( $uuid, $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( array( $uuid => 2 ), $result->duplicates );
	}

	public function test_injects_nested_eligible_blocks(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array(),
				'innerBlocks'  => array(
					$this->paragraph_block( 'Nested' ),
					$this->paragraph_block( 'Also nested', array(), 'core/heading' ),
				),
				'innerHTML'    => '<div class="wp-block-group"></div>',
				'innerContent' => array( '<div class="wp-block-group">', null, null, '</div>' ),
			),
		);

		$result = $this->injector->inject_blocks( $blocks );

		$this->assertTrue( $result->changed );
		$this->assertSame( 2, $result->stats['uuid_created'] );
		$this->assertTrue(
			UuidValidator::is_valid_non_empty(
				(string) $blocks[0]['innerBlocks'][0]['attrs'][ Contract::ATTR_NAME ]
			)
		);
		$this->assertTrue(
			UuidValidator::is_valid_non_empty(
				(string) $blocks[0]['innerBlocks'][1]['attrs'][ Contract::ATTR_NAME ]
			)
		);
	}

	/**
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array<string, mixed>
	 */
	private function paragraph_block( string $text, array $attrs = array(), string $block_name = 'core/paragraph' ): array {
		$html = 'core/heading' === $block_name
			? '<h2>' . $text . '</h2>'
			: '<p>' . $text . '</p>';

		return array(
			'blockName'    => $block_name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}
}
