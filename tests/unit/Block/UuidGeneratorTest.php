<?php
/**
 * Strategy F UUID generator.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\UuidGenerator;
use AIMultilingual\Block\UuidValidator;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F UUID generator tests.
 */
final class UuidGeneratorTest extends TestCase {

	public function test_generates_valid_uuid_v4(): void {
		$uuid = UuidGenerator::v4();

		$this->assertTrue( UuidValidator::is_valid( $uuid ) );
		$this->assertSame( Contract::UUID_MAX_LENGTH, strlen( $uuid ) );
		$this->assertSame( '4', $uuid[14] );
	}

	public function test_generates_unique_values(): void {
		$this->assertNotSame( UuidGenerator::v4(), UuidGenerator::v4() );
	}

	public function test_generates_unique_uuid_within_document(): void {
		$claimed = array();
		$attempt = 0;
		$uuid    = UuidGenerator::claim_unique( $claimed, static fn (): string => UuidGenerator::v4(), $attempt );

		$this->assertNotNull( $uuid );
		$this->assertTrue( UuidValidator::is_valid_non_empty( $uuid ) );
		$this->assertTrue( $claimed[ $uuid ] );
	}
}
