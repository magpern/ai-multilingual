<?php
/**
 * Strategy F segment key helper.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F segment key helper.
 */
final class SegmentKeyTest extends TestCase {

	private const UUID = '550e8400-e29b-41d4-a716-446655440000';

	public function test_builds_content_segment_key(): void {
		$key = SegmentKey::build( self::UUID, Contract::FIELD_CONTENT );

		$this->assertSame( 'b:' . self::UUID . ':content', $key );
		$this->assertTrue( SegmentKey::is_valid_format( $key ) );
	}

	public function test_parses_valid_segment_key(): void {
		$key    = SegmentKey::build( self::UUID, Contract::FIELD_CONTENT );
		$parsed = SegmentKey::parse( $key );

		$this->assertSame(
			array(
				'uuid'  => self::UUID,
				'field' => 'content',
			),
			$parsed
		);
	}

	public function test_rejects_unsupported_field_on_build(): void {
		$this->expectException( \InvalidArgumentException::class );

		SegmentKey::build( self::UUID, 'caption' );
	}

	public function test_rejects_invalid_uuid_on_build(): void {
		$this->expectException( \InvalidArgumentException::class );

		SegmentKey::build( 'not-a-uuid', Contract::FIELD_CONTENT );
	}

	/**
	 * @dataProvider provide_invalid_keys
	 */
	public function test_rejects_invalid_segment_keys( string $key ): void {
		$this->assertFalse( SegmentKey::is_valid_format( $key ) );
		$this->assertNull( SegmentKey::parse( $key ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function provide_invalid_keys(): array {
		return array(
			'empty'             => array( '' ),
			'wrong prefix'      => array( 'x:' . self::UUID . ':content' ),
			'missing field'     => array( 'b:' . self::UUID ),
			'unsupported field' => array( 'b:' . self::UUID . ':caption' ),
			'invalid uuid'      => array( 'b:not-a-uuid:content' ),
		);
	}
}
