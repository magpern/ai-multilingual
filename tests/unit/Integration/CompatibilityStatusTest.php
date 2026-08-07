<?php
/**
 * CompatibilityStatus unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\CompatibilityStatus
 */
final class CompatibilityStatusTest extends TestCase {

	public function test_allows_operation_and_overlay_matrix(): void {
		$ok = new CompatibilityStatus( Contract::STATE_COMPATIBLE, 'ok' );
		$this->assertTrue( $ok->allows_operation() );
		$this->assertTrue( $ok->allows_overlay() );

		$disabled = new CompatibilityStatus( Contract::STATE_DISABLED, 'off' );
		$this->assertFalse( $disabled->allows_operation() );
		$this->assertFalse( $disabled->allows_overlay() );

		$degraded = new CompatibilityStatus( Contract::STATE_DEGRADED, 'partial' );
		$this->assertTrue( $degraded->allows_operation() );
		$this->assertFalse( $degraded->allows_overlay() );
	}

	public function test_rejects_unknown_state(): void {
		$this->expectException( \InvalidArgumentException::class );
		new CompatibilityStatus( 'not-a-state' );
	}
}
