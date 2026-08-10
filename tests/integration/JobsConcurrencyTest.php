<?php
/**
 * Jobs concurrency admission integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationConcurrencyPolicy;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\JobStatuses;
use WP_Error;

/**
 * Verifies the database-backed running cap at its boundary.
 */
final class JobsConcurrencyTest extends AimlTestCase {

	private BackgroundTranslationJobRepository $jobs;

	protected function setUp(): void {
		parent::setUp();
		$this->jobs = new BackgroundTranslationJobRepository();
	}

	public function test_one_slot_left_admits_exactly_one_job(): void {
		for ( $i = 0; $i < 19; ++$i ) {
			$this->insert_job( JobStatuses::RUNNING, 'running-' . $i );
		}
		$first  = $this->insert_job( JobStatuses::QUEUED, 'candidate-a' );
		$second = $this->insert_job( JobStatuses::QUEUED, 'candidate-b' );
		$policy = new BackgroundTranslationConcurrencyPolicy( $this->jobs );

		$admitted = $policy->admit_and_mark_running( (int) $first->job_id, JobStatuses::QUEUED );
		$rejected = $policy->admit_and_mark_running( (int) $second->job_id, JobStatuses::QUEUED );

		$this->assertNotInstanceOf( WP_Error::class, $admitted );
		$this->assertInstanceOf( WP_Error::class, $rejected );
		$this->assertSame( 'concurrency_limit_exceeded', $rejected->get_error_code() );
		$this->assertSame( 20, $this->jobs->count_by_status( JobStatuses::RUNNING ) );
	}

	public function test_full_cap_rejects_without_status_change(): void {
		for ( $i = 0; $i < 20; ++$i ) {
			$this->insert_job( JobStatuses::RUNNING, 'full-' . $i );
		}
		$candidate = $this->insert_job( JobStatuses::QUEUED, 'full-candidate' );
		$policy    = new BackgroundTranslationConcurrencyPolicy( $this->jobs );

		$result = $policy->admit_and_mark_running( (int) $candidate->job_id, JobStatuses::QUEUED );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( JobStatuses::QUEUED, $this->jobs->find( (int) $candidate->job_id )->status );
		$this->assertSame( 20, $this->jobs->count_by_status( JobStatuses::RUNNING ) );
	}

	private function insert_job( string $status, string $suffix ): object {
		$job = $this->jobs->insert(
			array(
				'job_type'        => 'translate_selected',
				'status'          => $status,
				'idempotency_key' => hash( 'sha256', __METHOD__ . '|' . $suffix ),
				'source_type'     => 'post',
				'source_id'       => 10000 + ( abs( crc32( $suffix ) ) % 1000000 ),
				'language_id'     => 2,
				'lock_key'        => hash( 'sha256', 'lock|' . $suffix ),
				'active_lock_key' => null,
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );

		return $job;
	}
}
