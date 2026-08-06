<?php
/**
 * BackgroundTranslationScheduler unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\BackgroundTranslationScheduler;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Scheduler availability unit tests.
 */
final class BackgroundTranslationSchedulerTest extends TestCase {

	public function test_is_available_false_without_action_scheduler(): void {
		$scheduler = new BackgroundTranslationScheduler();
		$this->assertFalse( $scheduler->is_available() );
	}

	public function test_enqueue_job_returns_error_when_unavailable(): void {
		$scheduler = new BackgroundTranslationScheduler();
		$result    = $scheduler->enqueue_job( 99 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'action_scheduler_unavailable', $result->get_error_code() );
	}
}
