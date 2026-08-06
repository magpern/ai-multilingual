<?php
/**
 * JobBounds unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\JobBounds;
use PHPUnit\Framework\TestCase;

/**
 * J2 — workload bound constants.
 */
final class JobBoundsTest extends TestCase {

	public function test_frozen_defaults(): void {
		$this->assertSame( 50, JobBounds::MAX_POSTS_PER_BULK );
		$this->assertSame( 500, JobBounds::MAX_ITEMS_PER_JOB );
		$this->assertSame( 50, JobBounds::MAX_SELECTED_SEGMENTS );
		$this->assertSame( 20, JobBounds::MAX_CONCURRENT_RUNNING );
		$this->assertSame( 300, JobBounds::DEFAULT_LEASE_TTL_SECONDS );
	}
}
