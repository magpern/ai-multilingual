<?php
/**
 * JobTransitionPolicy unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobTransitionPolicy;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * J2 — job transition matrix.
 */
final class JobTransitionPolicyTest extends TestCase {

	public function test_legal_transitions(): void {
		$this->assertTrue( JobTransitionPolicy::can_transition( JobStatuses::QUEUED, JobStatuses::RUNNING ) );
		$this->assertTrue( JobTransitionPolicy::can_transition( JobStatuses::RUNNING, JobStatuses::PAUSED ) );
		$this->assertTrue( JobTransitionPolicy::can_transition( JobStatuses::PAUSED, JobStatuses::QUEUED ) );
		$this->assertTrue( JobTransitionPolicy::can_transition( JobStatuses::RUNNING, JobStatuses::COMPLETED ) );
	}

	public function test_illegal_transition_returns_error(): void {
		$result = JobTransitionPolicy::validate_transition( JobStatuses::COMPLETED, JobStatuses::RUNNING );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'illegal_transition', $result->get_error_code() );
	}

	public function test_operator_retry_reopens_eligible_terminal_jobs(): void {
		$this->assertTrue(
			JobTransitionPolicy::can_transition( JobStatuses::FAILED, JobStatuses::QUEUED )
		);
		$this->assertTrue(
			JobTransitionPolicy::can_transition( JobStatuses::COMPLETED_WITH_ERRORS, JobStatuses::QUEUED )
		);
		$this->assertFalse(
			JobTransitionPolicy::can_transition( JobStatuses::COMPLETED, JobStatuses::QUEUED )
		);
	}

	public function test_resume_cancelled_is_not_resumable(): void {
		$job    = (object) array( 'status' => JobStatuses::CANCELLED );
		$result = JobTransitionPolicy::validate_resume( $job );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'job_not_resumable', $result->get_error_code() );
	}
}
