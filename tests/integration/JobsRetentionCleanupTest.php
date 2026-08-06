<?php
/**
 * Background translation jobs retention cleanup integration tests (J7 / plan §18).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationDiagnostics;
use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\BackgroundTranslationRetentionCleanup;
use AIMultilingual\Jobs\BackgroundTranslationScheduler;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobLeaseService;
use AIMultilingual\Jobs\JobProgressReconciler;
use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobTypes;

/**
 * Bounded retention cleanup must never delete active or leased jobs.
 */
final class JobsRetentionCleanupTest extends AimlTestCase {

	private BackgroundTranslationJobRepository $jobs;

	private BackgroundTranslationItemRepository $items;

	private BackgroundTranslationRetentionCleanup $cleanup;

	private BackgroundTranslationJobService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->jobs    = new BackgroundTranslationJobRepository();
		$this->items   = new BackgroundTranslationItemRepository();
		$diagnostics   = new BackgroundTranslationDiagnostics( $this->jobs, $this->items );
		$this->cleanup = new BackgroundTranslationRetentionCleanup( $this->jobs, $this->items, $diagnostics );
		$this->service = new BackgroundTranslationJobService(
			$this->jobs,
			$this->items,
			new JobLeaseService( $this->jobs, $this->items ),
			new JobProgressReconciler( $this->jobs, $this->items )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function create_args( string $suffix ): array {
		return array(
			'job_type'       => JobTypes::TRANSLATE_SELECTED,
			'source_type'    => 'post',
			'source_id'      => 2000 + crc32( $suffix ) % 7000,
			'language_id'    => 2,
			'segment_keys'   => array( 'title' ),
			'provider_id'    => 'openai',
			'prompt_profile' => 'default',
			'prompt_version' => '1',
			'created_by'     => 1,
		);
	}

	public function test_cleanup_deletes_old_completed_jobs_within_batch_limit(): void {
		$job = $this->service->create_job( $this->create_args( 'old-completed' ) );
		$this->assertIsObject( $job );

		$finished = gmdate( 'Y-m-d H:i:s', time() - ( 31 * DAY_IN_SECONDS ) );
		$this->jobs->update(
			(int) $job->job_id,
			array(
				'status'          => JobStatuses::COMPLETED,
				'finished_at'     => $finished,
				'active_lock_key' => null,
				'lease_owner'     => '',
			)
		);

		$metrics = $this->cleanup->run( 10 );

		$this->assertSame( 1, $metrics['jobs_deleted'] );
		$this->assertGreaterThanOrEqual( 1, $metrics['items_deleted'] );
		$this->assertNull( $this->jobs->find( (int) $job->job_id ) );
	}

	public function test_cleanup_never_deletes_active_or_leased_jobs(): void {
		$job = $this->service->create_job( $this->create_args( 'active-keep' ) );
		$this->assertIsObject( $job );

		$finished = gmdate( 'Y-m-d H:i:s', time() - ( 120 * DAY_IN_SECONDS ) );
		$this->jobs->update(
			(int) $job->job_id,
			array(
				'status'           => JobStatuses::QUEUED,
				'finished_at'      => $finished,
				'active_lock_key'  => (string) $job->lock_key,
				'lease_owner'      => 'worker-token',
				'lease_expires_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
			)
		);

		$metrics = $this->cleanup->run( 10 );

		$this->assertSame( 0, $metrics['jobs_deleted'] );
		$this->assertNotNull( $this->jobs->find( (int) $job->job_id ) );
	}

	public function test_cleanup_is_idempotent_for_same_eligible_job(): void {
		$job = $this->service->create_job( $this->create_args( 'idempotent' ) );
		$this->assertIsObject( $job );

		$finished = gmdate( 'Y-m-d H:i:s', time() - ( 100 * DAY_IN_SECONDS ) );
		$this->jobs->update(
			(int) $job->job_id,
			array(
				'status'          => JobStatuses::FAILED,
				'finished_at'     => $finished,
				'active_lock_key' => null,
				'lease_owner'     => '',
			)
		);

		$first  = $this->cleanup->run( 10 );
		$second = $this->cleanup->run( 10 );

		$this->assertSame( 1, $first['jobs_deleted'] );
		$this->assertSame( 0, $second['jobs_deleted'] );
	}

	public function test_cleanup_deletes_orphan_items(): void {
		global $wpdb;

		$job = $this->service->create_job( $this->create_args( 'orphan-parent' ) );
		$this->assertIsObject( $job );

		$item = $this->items->list_by_job( (int) $job->job_id )[0];
		$wpdb->delete(
			\AIMultilingual\Database\Schema::jobs(),
			array( 'job_id' => (int) $job->job_id ),
			array( '%d' )
		);

		$this->assertNotNull( $this->items->find( (int) $item->item_id ) );

		$metrics = $this->cleanup->run( 10 );

		$this->assertGreaterThanOrEqual( 1, $metrics['orphans_deleted'] );
		$this->assertNull( $this->items->find( (int) $item->item_id ) );
	}

	public function test_sweep_records_cleanup_without_touching_store(): void {
		$job = $this->service->create_job( $this->create_args( 'sweep-metrics' ) );
		$this->assertIsObject( $job );

		$finished = gmdate( 'Y-m-d H:i:s', time() - ( 31 * DAY_IN_SECONDS ) );
		$this->jobs->update(
			(int) $job->job_id,
			array(
				'status'          => JobStatuses::COMPLETED,
				'finished_at'     => $finished,
				'active_lock_key' => null,
				'lease_owner'     => '',
			)
		);

		$diagnostics = new BackgroundTranslationDiagnostics( $this->jobs, $this->items );
		$diagnostics->reset_counters();
		$scheduler = new BackgroundTranslationScheduler(
			new BackgroundTranslationRetentionCleanup( $this->jobs, $this->items, $diagnostics ),
			$diagnostics
		);

		$scheduler->run_sweep( new JobLeaseService( $this->jobs, $this->items ) );

		$counters = $diagnostics->counters();
		$this->assertGreaterThanOrEqual( 1, $counters[ BackgroundTranslationDiagnostics::CLEANUP_JOBS_DELETED ] );
	}
}
