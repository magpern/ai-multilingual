<?php
/**
 * IntegrationSecurity unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\IntegrationSecurity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\IntegrationSecurity
 */
final class IntegrationSecurityTest extends TestCase {

	public function test_detects_serialized_callback_payloads(): void {
		$this->assertTrue( IntegrationSecurity::looks_like_serialized_callback( 'O:8:"stdClass":0:{}' ) );
		$this->assertFalse( IntegrationSecurity::looks_like_serialized_callback( 'plain-token' ) );
		$this->assertFalse( IntegrationSecurity::looks_like_serialized_callback( array( 'fn' => 'x' ) ) );
	}
}
