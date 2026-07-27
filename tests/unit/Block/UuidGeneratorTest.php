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
		$first  = UuidGenerator::v4();
		$second = UuidGenerator::v4();

		$this->assertNotSame( $first, $second );
	}
}
