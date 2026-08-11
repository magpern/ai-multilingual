<?php
/**
 * JobsFailurePresenter unit tests (OTL.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\JobsFailurePresenter;
use PHPUnit\Framework\TestCase;

/**
 * Bounded failure presentation — no secrets/prompts.
 */
final class JobsFailurePresenterTest extends TestCase {

	public function test_categorizes_known_codes(): void {
		$p     = new JobsFailurePresenter();
		$stale = $p->present( 'stale_source', '', 'Source moved' );
		$this->assertNotNull( $stale );
		$this->assertSame( JobsFailurePresenter::CATEGORY_STALE_SOURCE, $stale['category'] );

		$budget = $p->present( 'budget_exceeded' );
		$this->assertSame( JobsFailurePresenter::CATEGORY_BUDGET, $budget['category'] );
	}

	public function test_bounds_long_messages_and_strips_tags(): void {
		$p      = new JobsFailurePresenter();
		$long   = str_repeat( 'x', 400 );
		$result = $p->present( 'provider_error', 'retryable', '<script>alert(1)</script>' . $long );
		$this->assertNotNull( $result );
		$this->assertStringNotContainsString( '<script>', $result['message'] );
		$this->assertLessThanOrEqual( JobsFailurePresenter::MAX_MESSAGE_CHARS + 3, strlen( $result['message'] ) );
	}

	public function test_empty_returns_null(): void {
		$p = new JobsFailurePresenter();
		$this->assertNull( $p->present( '', '', '' ) );
	}
}
