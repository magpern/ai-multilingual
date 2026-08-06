<?php
/**
 * BackgroundTranslationScheduler extended unit tests (J4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\BackgroundTranslationScheduler;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Scheduler health and sweep unit coverage.
 */
final class BackgroundTranslationSchedulerHealthTest extends TestCase {

	public function test_health_unavailable_without_action_scheduler(): void {
		$scheduler = new BackgroundTranslationScheduler();
		$health    = $scheduler->health();

		$this->assertFalse( $health['available'] );
		$this->assertNotSame( '', $health['message'] );
	}

	public function test_enqueue_job_returns_error_when_unavailable(): void {
		$scheduler = new BackgroundTranslationScheduler();
		$result    = $scheduler->enqueue_job( 99 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'action_scheduler_unavailable', $result->get_error_code() );
	}
}
