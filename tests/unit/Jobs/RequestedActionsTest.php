<?php
/**
 * RequestedActions unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\RequestedActions;
use PHPUnit\Framework\TestCase;

/**
 * J2 — requested_action helpers.
 */
final class RequestedActionsTest extends TestCase {

	public function test_pending_detection(): void {
		$this->assertFalse( RequestedActions::is_pending( RequestedActions::NONE ) );
		$this->assertTrue( RequestedActions::is_pending( RequestedActions::PAUSE ) );
		$this->assertTrue( RequestedActions::is_pending( RequestedActions::CANCEL ) );
	}
}
