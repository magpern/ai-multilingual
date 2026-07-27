<?php
/**
 * Strategy F UUID validator.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\UuidValidator;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F UUID validator.
 */
final class UuidValidatorTest extends TestCase {

	private const VALID_UUID = '550e8400-e29b-41d4-a716-446655440000';

	public function test_accepts_valid_uuid_v4(): void {
		$this->assertTrue( UuidValidator::is_valid( self::VALID_UUID ) );
		$this->assertTrue( UuidValidator::is_valid_non_empty( self::VALID_UUID ) );
		$this->assertSame( self::VALID_UUID, UuidValidator::normalize( self::VALID_UUID ) );
	}

	/**
	 * @dataProvider provide_invalid_values
	 *
	 * @param mixed $value Invalid candidate.
	 */
	public function test_rejects_invalid_values( $value ): void {
		$this->assertFalse( UuidValidator::is_valid( $value ) );
		$this->assertFalse( UuidValidator::is_valid_non_empty( $value ) );
		$this->assertNull( UuidValidator::normalize( $value ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_invalid_values(): array {
		return array(
			'empty string'    => array( '' ),
			'uppercase uuid'  => array( '550E8400-E29B-41D4-A716-446655440000' ),
			'wrong version'   => array( '550e8400-e29b-51d4-a716-446655440000' ),
			'too long'        => array( self::VALID_UUID . '0' ),
			'not a string'    => array( 42 ),
			'null'            => array( null ),
			'missing hyphens' => array( '550e8400e29b41d4a716446655440000' ),
		);
	}
}
