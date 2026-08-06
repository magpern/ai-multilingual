<?php
/**
 * ItemTransitionPolicy unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\ItemTransitionPolicy;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * J2 — item transition matrix.
 */
final class ItemTransitionPolicyTest extends TestCase {

	public function test_materialize_and_success_paths(): void {
		$this->assertTrue( ItemTransitionPolicy::can_transition( '', ItemStatuses::QUEUED ) );
		$this->assertTrue( ItemTransitionPolicy::can_transition( ItemStatuses::QUEUED, ItemStatuses::RUNNING ) );
		$this->assertTrue( ItemTransitionPolicy::can_transition( ItemStatuses::RUNNING, ItemStatuses::COMPLETED ) );
		$this->assertTrue( ItemTransitionPolicy::can_transition( ItemStatuses::FAILED, ItemStatuses::QUEUED ) );
	}

	public function test_terminal_immutable(): void {
		$result = ItemTransitionPolicy::validate_transition( ItemStatuses::COMPLETED, ItemStatuses::FAILED );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'illegal_transition', $result->get_error_code() );
	}
}
