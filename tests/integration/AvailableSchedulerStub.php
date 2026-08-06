<?php
/**
 * Scheduler stub reporting Action Scheduler as available.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationScheduler;

/**
 * Available AS stub for Jobs integration tests.
 */
final class AvailableSchedulerStub extends BackgroundTranslationScheduler {

	public function is_available(): bool {
		return true;
	}

	/**
	 * @return array{available: bool, message: string}
	 */
	public function health(): array {
		return array(
			'available' => true,
			'message'   => 'Action Scheduler is available.',
		);
	}

	/**
	 * @param int $job_id Job id.
	 * @return true
	 */
	public function enqueue_job( int $job_id ) {
		return true;
	}

	/**
	 * @param int $job_id        Job id.
	 * @param int $delay_seconds Delay seconds.
	 * @return true
	 */
	public function enqueue_job_delayed( int $job_id, int $delay_seconds ) {
		return true;
	}
}
