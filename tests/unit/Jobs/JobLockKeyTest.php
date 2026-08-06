<?php
/**
 * JobLockKey unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\JobLockKey;
use PHPUnit\Framework\TestCase;

/**
 * J2 — lock key builder.
 */
final class JobLockKeyTest extends TestCase {

	public function test_build_format(): void {
		$this->assertSame( 'post:42:3', JobLockKey::build( 'post', 42, 3 ) );
	}
}
