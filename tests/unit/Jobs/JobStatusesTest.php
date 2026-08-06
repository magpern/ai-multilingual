<?php
/**
 * JobStatuses unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\JobStatuses;
use PHPUnit\Framework\TestCase;

/**
 * J2 — job status helpers.
 */
final class JobStatusesTest extends TestCase {

	public function test_terminal_statuses(): void {
		$this->assertTrue( JobStatuses::is_terminal( JobStatuses::COMPLETED ) );
		$this->assertTrue( JobStatuses::is_terminal( JobStatuses::COMPLETED_WITH_ERRORS ) );
		$this->assertTrue( JobStatuses::is_terminal( JobStatuses::FAILED ) );
		$this->assertTrue( JobStatuses::is_terminal( JobStatuses::CANCELLED ) );
		$this->assertFalse( JobStatuses::is_terminal( JobStatuses::QUEUED ) );
		$this->assertFalse( JobStatuses::is_terminal( JobStatuses::RUNNING ) );
	}

	public function test_active_statuses_hold_lock_semantics(): void {
		$this->assertTrue( JobStatuses::is_active( JobStatuses::QUEUED ) );
		$this->assertTrue( JobStatuses::is_active( JobStatuses::RUNNING ) );
		$this->assertTrue( JobStatuses::is_active( JobStatuses::PAUSED ) );
		$this->assertFalse( JobStatuses::is_active( JobStatuses::COMPLETED ) );
	}
}
