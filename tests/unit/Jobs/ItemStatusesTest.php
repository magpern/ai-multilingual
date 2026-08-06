<?php
/**
 * ItemStatuses unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\ItemStatuses;
use PHPUnit\Framework\TestCase;

/**
 * J2 — item status helpers.
 */
final class ItemStatusesTest extends TestCase {

	public function test_terminal_and_success_buckets(): void {
		$this->assertTrue( ItemStatuses::is_terminal( ItemStatuses::COMPLETED ) );
		$this->assertTrue( ItemStatuses::is_success_terminal( ItemStatuses::COMPLETED ) );
		$this->assertTrue( ItemStatuses::is_non_success_terminal( ItemStatuses::FAILED ) );
		$this->assertTrue( ItemStatuses::is_skipped_bucket( ItemStatuses::SKIPPED_CONFLICT ) );
		$this->assertTrue( ItemStatuses::is_stale_bucket( ItemStatuses::STALE_SOURCE ) );
		$this->assertFalse( ItemStatuses::is_terminal( ItemStatuses::QUEUED ) );
	}
}
