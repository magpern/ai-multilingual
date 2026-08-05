<?php
/**
 * Rejection reason normalization unit tests (R3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace\Review;

use AIMultilingual\Workspace\Review\ReviewWorkflowException;
use AIMultilingual\Workspace\Review\ReviewWorkflowService;
use PHPUnit\Framework\TestCase;

/**
 * Thin unit coverage for reason validation helper.
 */
final class ReviewWorkflowReasonTest extends TestCase {

	public function test_normalize_trims_and_sanitizes(): void {
		$reason = ReviewWorkflowService::normalize_rejection_reason( "  Needs <b>fix</b>  \n" );

		$this->assertSame( 'Needs fix', $reason );
	}

	public function test_normalize_rejects_empty_after_trim(): void {
		$this->expectException( ReviewWorkflowException::class );

		try {
			ReviewWorkflowService::normalize_rejection_reason( '   ' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_REASON_REQUIRED, $exception->get_error_code() );
			throw $exception;
		}
	}

	public function test_normalize_rejects_overlong_reason(): void {
		$this->expectException( ReviewWorkflowException::class );

		try {
			ReviewWorkflowService::normalize_rejection_reason( str_repeat( 'a', ReviewWorkflowService::REASON_MAX_LEN + 1 ) );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_REASON_REQUIRED, $exception->get_error_code() );
			throw $exception;
		}
	}
}
