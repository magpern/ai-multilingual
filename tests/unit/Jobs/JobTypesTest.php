<?php
/**
 * JobTypes unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\JobTypes;
use PHPUnit\Framework\TestCase;

/**
 * J2 — job type policy helpers.
 */
final class JobTypesTest extends TestCase {

	public function test_retranslate_policy(): void {
		$this->assertTrue( JobTypes::allows_retranslate( JobTypes::TRANSLATE_SELECTED ) );
		$this->assertTrue( JobTypes::allows_retranslate( JobTypes::RETRANSLATE_STALE ) );
		$this->assertFalse( JobTypes::allows_retranslate( JobTypes::TRANSLATE_MISSING ) );
		$this->assertFalse( JobTypes::allows_retranslate( JobTypes::BULK_TRANSLATE ) );
	}
}
