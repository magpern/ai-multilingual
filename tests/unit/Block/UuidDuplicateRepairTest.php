<?php
/**
 * Strategy F duplicate UUID repair.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\BlockIdentityLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\InjectResult;
use AIMultilingual\Block\UuidGenerator;
use AIMultilingual\Block\UuidInjector;
use AIMultilingual\Block\UuidValidator;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F first-wins duplicate repair tests.
 */
final class UuidDuplicateRepairTest extends TestCase {

	private const UUID_A = '550e8400-e29b-41d4-a716-446655440000';
	private const UUID_B = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';

	public function test_two_sibling_duplicates_repair_first_wins(): void {
		$blocks = array(
			$this->paragraph( 'One', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'Two', array( Contract::ATTR_NAME => self::UUID_A ) ),
		);

		$result = $this->injector()->inject_blocks( $blocks );

		$this->assertTrue( $result->successful );
		$this->assertSame( self::UUID_A, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertNotSame( self::UUID_A, $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( 1, $result->stats['uuid_duplicate_detected'] );
		$this->assertSame( 1, $result->stats['uuid_duplicate_repaired'] );
		$this->assertSame( array( self::UUID_A => $blocks[1]['attrs'][ Contract::ATTR_NAME ] ), $result->replacements );
		$this->assertSame( array(), $result->duplicates );
	}

	public function test_three_occurrences_receive_distinct_replacements(): void {
		$blocks = array(
			$this->paragraph( 'One', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'Two', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'Three', array( Contract::ATTR_NAME => self::UUID_A ) ),
		);

		$result = $this->injector()->inject_blocks( $blocks );

		$first  = (string) $blocks[0]['attrs'][ Contract::ATTR_NAME ];
		$second = (string) $blocks[1]['attrs'][ Contract::ATTR_NAME ];
		$third  = (string) $blocks[2]['attrs'][ Contract::ATTR_NAME ];

		$this->assertSame( self::UUID_A, $first );
		$this->assertNotSame( self::UUID_A, $second );
		$this->assertNotSame( self::UUID_A, $third );
		$this->assertNotSame( $second, $third );
		$this->assertSame( 2, $result->stats['uuid_duplicate_repaired'] );
	}

	public function test_nested_duplicate_uses_document_order(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array(),
				'innerBlocks'  => array(
					$this->paragraph( 'Inner first', array( Contract::ATTR_NAME => self::UUID_A ) ),
				),
				'innerHTML'    => '<div class="wp-block-group"></div>',
				'innerContent' => array( '<div class="wp-block-group">', null, '</div>' ),
			),
			$this->paragraph( 'Outer second', array( Contract::ATTR_NAME => self::UUID_A ) ),
		);

		$result = $this->injector()->inject_blocks( $blocks );

		$this->assertSame( self::UUID_A, $blocks[0]['innerBlocks'][0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertNotSame( self::UUID_A, $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertTrue( $result->successful );
	}

	public function test_ineligible_block_does_not_claim_identity(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array( Contract::ATTR_NAME => self::UUID_A ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<div class="wp-block-group"></div>',
				'innerContent' => array( '<div class="wp-block-group"></div>' ),
			),
			$this->paragraph( 'Eligible', array( Contract::ATTR_NAME => self::UUID_A ) ),
		);

		$result = $this->injector()->inject_blocks( $blocks );

		$this->assertSame( self::UUID_A, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( self::UUID_A, $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( 0, $result->stats['uuid_duplicate_repaired'] );
	}

	public function test_cross_post_independence(): void {
		$post_one = array( $this->paragraph( 'Post one', array( Contract::ATTR_NAME => self::UUID_A ) ) );
		$post_two = array( $this->paragraph( 'Post two', array( Contract::ATTR_NAME => self::UUID_A ) ) );

		$this->injector()->inject_blocks( $post_one );
		$this->injector()->inject_blocks( $post_two );

		$this->assertSame( self::UUID_A, $post_one[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( self::UUID_A, $post_two[0]['attrs'][ Contract::ATTR_NAME ] );
	}

	public function test_malformed_duplicate_is_replaced_before_claiming(): void {
		$blocks = array(
			$this->paragraph( 'First', array( Contract::ATTR_NAME => 'bad-value' ) ),
			$this->paragraph( 'Second', array( Contract::ATTR_NAME => 'bad-value' ) ),
		);

		$result = $this->injector()->inject_blocks( $blocks );

		$this->assertTrue( $result->successful );
		$this->assertSame( 2, $result->stats['uuid_replaced_invalid'] );
		$this->assertNotSame( $blocks[0]['attrs'][ Contract::ATTR_NAME ], $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( array(), $result->duplicates );
	}

	public function test_generation_collision_retries_until_unique(): void {
		$calls   = 0;
		$factory = function () use ( &$calls ): string {
			++$calls;
			return 1 === $calls ? self::UUID_A : self::UUID_B;
		};

		$blocks = array(
			$this->paragraph( 'Keeps A', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'Needs new', array() ),
		);

		$result = $this->injector( $factory )->inject_blocks( $blocks );

		$this->assertTrue( $result->successful );
		$this->assertSame( self::UUID_A, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( self::UUID_B, $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertTrue(
			$this->has_event( $result, BlockIdentityLogger::EVENT_UUID_GENERATION_COLLISION )
		);
	}

	public function test_retry_exhaustion_fails_without_partial_mutation(): void {
		$factory = static fn (): string => self::UUID_A;

		$blocks = array(
			$this->paragraph( 'First', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'Duplicate', array( Contract::ATTR_NAME => self::UUID_A ) ),
		);

		$result = $this->injector( $factory )->inject_blocks( $blocks );

		$this->assertFalse( $result->successful );
		$this->assertSame( 'uuid_claim_exhausted', $result->failure_reason );
		$this->assertSame( self::UUID_A, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( self::UUID_A, $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertFalse( $result->changed );
	}

	public function test_second_pass_is_idempotent_and_duplicate_free(): void {
		$blocks = array(
			$this->paragraph( 'One', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'Two', array( Contract::ATTR_NAME => self::UUID_A ) ),
		);

		$first = $this->injector()->inject_blocks( $blocks );
		$this->assertTrue( $first->successful );
		$snapshot = serialize( $blocks );

		$second = $this->injector()->inject_blocks( $blocks );

		$this->assertFalse( $second->changed );
		$this->assertSame( $snapshot, serialize( $blocks ) );
		$this->assertSame( array(), $second->duplicates );
	}

	public function test_multiple_independent_duplicate_groups(): void {
		$blocks = array(
			$this->paragraph( 'A1', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'A2', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'B1', array( Contract::ATTR_NAME => self::UUID_B ) ),
			$this->paragraph( 'B2', array( Contract::ATTR_NAME => self::UUID_B ) ),
		);

		$result = $this->injector()->inject_blocks( $blocks );

		$this->assertSame( self::UUID_A, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( self::UUID_B, $blocks[2]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertNotSame( self::UUID_A, $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertNotSame( self::UUID_B, $blocks[3]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( 2, $result->stats['uuid_duplicate_repaired'] );
		$this->assertSame( array(), $result->duplicates );
	}

	public function test_unsupported_blocks_with_duplicate_uuids_are_untouched(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array( Contract::ATTR_NAME => self::UUID_A ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<div></div>',
				'innerContent' => array( '<div></div>' ),
			),
			array(
				'blockName'    => 'core/group',
				'attrs'        => array( Contract::ATTR_NAME => self::UUID_A ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<div></div>',
				'innerContent' => array( '<div></div>' ),
			),
		);

		$result = $this->injector()->inject_blocks( $blocks );

		$this->assertFalse( $result->changed );
		$this->assertSame( self::UUID_A, $blocks[0]['attrs'][ Contract::ATTR_NAME ] );
		$this->assertSame( self::UUID_A, $blocks[1]['attrs'][ Contract::ATTR_NAME ] );
	}

	public function test_logs_repair_complete_when_duplicates_repaired(): void {
		$blocks = array(
			$this->paragraph( 'One', array( Contract::ATTR_NAME => self::UUID_A ) ),
			$this->paragraph( 'Two', array( Contract::ATTR_NAME => self::UUID_A ) ),
		);

		$result = $this->injector()->inject_blocks( $blocks );

		$this->assertTrue( $this->has_event( $result, BlockIdentityLogger::EVENT_UUID_DUPLICATE_REPAIRED ) );
		$this->assertTrue( $this->has_event( $result, BlockIdentityLogger::EVENT_UUID_REPAIR_COMPLETE ) );
	}

	public function test_claim_unique_respects_retry_limit(): void {
		$claimed = array( self::UUID_A => true );
		$attempt = 0;
		$factory = static fn (): string => self::UUID_A;

		$this->assertNull( UuidGenerator::claim_unique( $claimed, $factory, $attempt ) );
		$this->assertSame( UuidGenerator::MAX_CLAIM_ATTEMPTS, $attempt );
	}

	/**
	 * @param callable(): string|null $factory Optional UUID factory.
	 */
	private function injector( ?callable $factory = null ): UuidInjector {
		return new UuidInjector( new BlockRegistry(), new BlockIdentityLogger(), $factory );
	}

	/**
	 * Builds a minimal paragraph block for repair tests.
	 *
	 * @param string               $text  Inner HTML text.
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array<string, mixed>
	 */
	private function paragraph( string $text, array $attrs = array() ): array {
		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '<p>' . $text . '</p>',
			'innerContent' => array( '<p>' . $text . '</p>' ),
		);
	}

	private function has_event( InjectResult $result, string $event ): bool {
		foreach ( $result->events as $record ) {
			if ( ( $record['event'] ?? '' ) === $event ) {
				return true;
			}
		}

		return false;
	}
}
