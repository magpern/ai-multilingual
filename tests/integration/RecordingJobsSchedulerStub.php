<?php
/**
 * Scheduler stub that records enqueue calls.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationScheduler;

/**
 * Records enqueue calls for resume/retry wake assertions.
 */
final class RecordingJobsSchedulerStub extends BackgroundTranslationScheduler {

	/**
	 * @var list<int>
	 */
	public array $enqueued = array();

	/**
	 * @var list<array{int,int}>
	 */
	public array $delayed = array();

	/**
	 * @return array{available: bool, ready: bool}
	 */
	public function health(): array {
		return array(
			'available' => true,
			'ready'     => true,
		);
	}

	/**
	 * @param int $job_id Job id.
	 * @return true
	 */
	public function enqueue_job( int $job_id ) {
		$this->enqueued[] = $job_id;
		return true;
	}

	/**
	 * @param int $job_id        Job id.
	 * @param int $delay_seconds Delay seconds.
	 * @return true
	 */
	public function enqueue_job_delayed( int $job_id, int $delay_seconds ) {
		$this->delayed[] = array( $job_id, $delay_seconds );
		return true;
	}
}
