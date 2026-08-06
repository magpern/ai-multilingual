<?php
/**
 * Scheduler stub reporting Action Scheduler as unavailable.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationScheduler;

/**
 * Test double for AS-unavailable scenarios.
 */
final class UnavailableJobsSchedulerStub extends BackgroundTranslationScheduler {

	public function is_available(): bool {
		return false;
	}

	/**
	 * @return array{available: bool, message: string}
	 */
	public function health(): array {
		return array(
			'available' => false,
			'message'   => 'Action Scheduler is not available.',
		);
	}
}
