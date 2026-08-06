<?php
/**
 * Background Translation Jobs J2 integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationBatchCoordinator;
use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobBounds;
use AIMultilingual\Jobs\JobLeaseService;
use AIMultilingual\Jobs\JobProgressReconciler;
use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Jobs\RequestedActions;
use WP_Error;

/**
 * J2 lifecycle, leases, idempotency, batches.
 */
final class JobsLifecycleTest extends AimlTestCase {

	private BackgroundTranslationJobService $service;

	private BackgroundTranslationJobRepository $jobs;

	private BackgroundTranslationItemRepository $items;

	private JobLeaseService $leases;

	protected function setUp(): void {
		parent::setUp();
		$this->jobs    = new BackgroundTranslationJobRepository();
		$this->items   = new BackgroundTranslationItemRepository();
		$this->leases  = new JobLeaseService( $this->jobs, $this->items );
		$this->service = new BackgroundTranslationJobService( $this->jobs, $this->items, $this->leases );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function create_args( string $suffix, array $overrides = array() ): array {
		return array_merge(
			array(
				'job_type'       => JobTypes::TRANSLATE_SELECTED,
				'source_type'    => 'post',
				'source_id'      => 1000 + crc32( $suffix ) % 9000,
				'language_id'    => 2,
				'segment_keys'   => array( 'title', 'body' ),
				'provider_id'    => 'openai',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			),
			$overrides
		);
	}

	public function test_create_job_materializes_items_and_lock(): void {
		$job = $this->service->create_job( $this->create_args( 'materialize' ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );
		$this->assertSame( JobStatuses::QUEUED, $job->status );
		$this->assertSame( 'post:' . $job->source_id . ':2', $job->lock_key );
		$this->assertSame( $job->lock_key, $job->active_lock_key );
		$this->assertSame( 2, (int) $job->total_items );
		$this->assertSame( 2, (int) $job->queued_items );

		$rows = $this->items->list_by_job( (int) $job->job_id );
		$this->assertCount( 2, $rows );
	}

	public function test_idempotent_create_returns_existing_job(): void {
		$args  = $this->create_args( 'idem' );
		$first = $this->service->create_job( $args );
		$this->assertNotInstanceOf( WP_Error::class, $first );

		$second = $this->service->create_job( $args );
		$this->assertNotInstanceOf( WP_Error::class, $second );
		$this->assertSame( (int) $first->job_id, (int) $second->job_id );
	}

	public function test_idempotency_conflict_on_parameter_mismatch(): void {
		$args  = $this->create_args( 'idem-conflict', array( 'source_id' => 6012 ) );
		$first = $this->service->create_job( $args );
		$this->assertNotInstanceOf( WP_Error::class, $first );

		$this->jobs->update(
			(int) $first->job_id,
			array(
				'provider_id' => 'tampered-provider',
			)
		);

		$second = $this->service->create_job( $args );
		$this->assertInstanceOf( WP_Error::class, $second );
		$this->assertSame( 'idempotency_conflict', $second->get_error_code() );
	}

	public function test_force_new_creates_distinct_job(): void {
		$args  = $this->create_args( 'force-new', array( 'source_id' => 6011 ) );
		$first = $this->service->create_job( $args );
		$this->assertNotInstanceOf( WP_Error::class, $first );

		$this->service->request_cancel( (int) $first->job_id );
		$this->service->observe_requested_action_at_boundary( (int) $first->job_id );

		$second = $this->service->create_job( array_merge( $args, array( 'force_new' => true ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $second );
		$this->assertNotSame( (int) $first->job_id, (int) $second->job_id );
	}

	public function test_active_lock_conflict(): void {
		$args  = $this->create_args( 'lock-conflict', array( 'source_id' => 5555 ) );
		$first = $this->service->create_job( $args );
		$this->assertNotInstanceOf( WP_Error::class, $first );

		$second = $this->service->create_job(
			array_merge(
				$args,
				array(
					'segment_keys' => array( 'title' ),
				)
			)
		);
		$this->assertInstanceOf( WP_Error::class, $second );
		$this->assertSame( 'lock_key_conflict', $second->get_error_code() );
	}

	public function test_multiple_terminal_jobs_allow_null_active_lock(): void {
		$args = $this->create_args( 'terminal-a', array( 'source_id' => 6001 ) );
		$job  = $this->service->create_job( $args );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$this->items->update(
			(int) $this->items->list_by_job( (int) $job->job_id )[0]->item_id,
			array(
				'status'      => ItemStatuses::COMPLETED,
				'result_code' => 'ok',
				'finished_at' => current_time( 'mysql', true ),
			)
		);
		$this->items->update(
			(int) $this->items->list_by_job( (int) $job->job_id )[1]->item_id,
			array(
				'status'      => ItemStatuses::COMPLETED,
				'result_code' => 'ok',
				'finished_at' => current_time( 'mysql', true ),
			)
		);

		$final = $this->service->finalize_job_from_items( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $final );
		$this->assertSame( JobStatuses::COMPLETED, $final->status );
		$this->assertNull( $final->active_lock_key );

		$again = $this->service->create_job(
			array_merge( $args, array( 'force_new' => true ) )
		);
		$this->assertNotInstanceOf( WP_Error::class, $again );
		$this->assertNotNull( $again->active_lock_key );
	}

	public function test_pause_request_and_boundary_observe(): void {
		$job = $this->service->create_job( $this->create_args( 'pause', array( 'source_id' => 6002 ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$paused_req = $this->service->request_pause( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $paused_req );
		$this->assertSame( RequestedActions::PAUSE, $paused_req->requested_action );

		$observed = $this->service->observe_requested_action_at_boundary( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $observed );
		$this->assertSame( JobStatuses::PAUSED, $observed->status );
		$this->assertSame( RequestedActions::NONE, $observed->requested_action );
	}

	public function test_cancel_and_resume_rules(): void {
		$job = $this->service->create_job( $this->create_args( 'cancel', array( 'source_id' => 6003 ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$this->service->request_cancel( (int) $job->job_id );
		$cancelled = $this->service->observe_requested_action_at_boundary( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $cancelled );
		$this->assertSame( JobStatuses::CANCELLED, $cancelled->status );
		$this->assertNull( $cancelled->active_lock_key );

		$resume = $this->service->resume( (int) $job->job_id );
		$this->assertInstanceOf( WP_Error::class, $resume );
		$this->assertSame( 'job_not_resumable', $resume->get_error_code() );
	}

	public function test_resume_paused_job(): void {
		$job = $this->service->create_job( $this->create_args( 'resume', array( 'source_id' => 6004 ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$this->service->request_pause( (int) $job->job_id );
		$this->service->observe_requested_action_at_boundary( (int) $job->job_id );

		$resumed = $this->service->resume( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $resumed );
		$this->assertSame( JobStatuses::QUEUED, $resumed->status );
	}

	public function test_lease_claim_heartbeat_and_stale_recovery(): void {
		$job = $this->service->create_job( $this->create_args( 'lease', array( 'source_id' => 6005 ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$owner = 'worker-token-a';
		$claim = $this->leases->claim( (int) $job->job_id, $owner, 300 );
		$this->assertNotInstanceOf( WP_Error::class, $claim );
		$this->assertSame( JobStatuses::RUNNING, $claim->status );
		$this->assertSame( $owner, $claim->lease_owner );

		$beat = $this->leases->heartbeat( (int) $job->job_id, $owner, 300 );
		$this->assertNotInstanceOf( WP_Error::class, $beat );
		$this->assertNotNull( $beat );
		$this->assertNotEmpty( $beat->lease_expires_at );

		$item = $this->items->list_by_job( (int) $job->job_id )[0];
		$this->items->update( (int) $item->item_id, array( 'status' => ItemStatuses::RUNNING ) );

		$past = gmdate( 'Y-m-d H:i:s', time() - 60 );
		$this->jobs->update(
			(int) $job->job_id,
			array(
				'lease_expires_at' => $past,
			)
		);

		$recovered = $this->leases->recover_stale_leases( gmdate( 'Y-m-d H:i:s' ) );
		$this->assertNotEmpty( $recovered );

		$fresh = $this->jobs->find( (int) $job->job_id );
		$this->assertSame( '', $fresh->lease_owner );
		$this->assertSame( ItemStatuses::QUEUED, $this->items->find( (int) $item->item_id )->status );
	}

	public function test_progress_reconcile(): void {
		$job = $this->service->create_job( $this->create_args( 'reconcile', array( 'source_id' => 6006 ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$rows = $this->items->list_by_job( (int) $job->job_id );
		$this->items->update( (int) $rows[0]->item_id, array( 'status' => ItemStatuses::COMPLETED ) );
		$this->items->update( (int) $rows[1]->item_id, array( 'status' => ItemStatuses::FAILED ) );

		$reconciler = new JobProgressReconciler( $this->jobs, $this->items );
		$updated    = $reconciler->reconcile( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $updated );
		$this->assertSame( 2, (int) $updated->total_items );
		$this->assertSame( 1, (int) $updated->completed_items );
		$this->assertSame( 1, (int) $updated->failed_items );
	}

	public function test_finalize_partial_success(): void {
		$job = $this->service->create_job( $this->create_args( 'partial', array( 'source_id' => 6007 ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$rows = $this->items->list_by_job( (int) $job->job_id );
		$this->items->update( (int) $rows[0]->item_id, array( 'status' => ItemStatuses::COMPLETED ) );
		$this->items->update( (int) $rows[1]->item_id, array( 'status' => ItemStatuses::SKIPPED_CONFLICT ) );

		$final = $this->service->finalize_job_from_items( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $final );
		$this->assertSame( JobStatuses::COMPLETED_WITH_ERRORS, $final->status );
	}

	public function test_retry_failed_items_preserves_error_evidence(): void {
		$job = $this->service->create_job( $this->create_args( 'retry', array( 'source_id' => 6008 ) ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$item = $this->items->list_by_job( (int) $job->job_id )[0];
		$this->items->update(
			(int) $item->item_id,
			array(
				'status'             => ItemStatuses::FAILED,
				'last_error_code'    => 'provider_error',
				'last_error_message' => 'temporary',
			)
		);

		$retried = $this->service->retry_failed_items( (int) $job->job_id );
		$this->assertNotInstanceOf( WP_Error::class, $retried );

		$fresh = $this->items->find( (int) $item->item_id );
		$this->assertSame( ItemStatuses::QUEUED, $fresh->status );
		$this->assertSame( 'provider_error', $fresh->last_error_code );
	}

	public function test_workload_bounds(): void {
		$too_many = array();
		for ( $i = 0; $i <= JobBounds::MAX_SELECTED_SEGMENTS; $i++ ) {
			$too_many[] = 'seg-' . $i;
		}

		$result = $this->service->create_job(
			$this->create_args(
				'bounds',
				array(
					'source_id'    => 6010,
					'segment_keys' => $too_many,
				)
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'workload_limit_exceeded', $result->get_error_code() );
	}

	public function test_batch_coordinator_create_and_progress(): void {
		$coordinator = new BackgroundTranslationBatchCoordinator( $this->service, $this->jobs );

		$posts = array(
			array(
				'source_id'    => 7001,
				'segment_keys' => array( 'title' ),
			),
			array(
				'source_id'    => 7002,
				'segment_keys' => array( 'title' ),
			),
		);

		$bulk = $coordinator->create_bulk(
			$posts,
			2,
			array(
				'source_type'    => 'post',
				'provider_id'    => 'openai',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);

		$this->assertNotInstanceOf( WP_Error::class, $bulk );
		$this->assertArrayHasKey( 'batch_id', $bulk );
		$this->assertCount( 2, $bulk['jobs'] );

		$progress = $coordinator->batch_progress( $bulk['batch_id'] );
		$this->assertSame( 2, $progress['job_count'] );
		$this->assertSame( 2, $progress['total_items'] );
	}

	public function test_batch_cancel(): void {
		$coordinator = new BackgroundTranslationBatchCoordinator( $this->service, $this->jobs );

		$bulk = $coordinator->create_bulk(
			array(
				array(
					'source_id'    => 8001,
					'segment_keys' => array( 'title' ),
				),
			),
			2,
			array(
				'source_type'    => 'post',
				'provider_id'    => 'openai',
				'prompt_profile' => 'default',
				'prompt_version' => '1',
				'created_by'     => 1,
			)
		);

		$this->assertNotInstanceOf( WP_Error::class, $bulk );

		$results = $coordinator->cancel_batch( $bulk['batch_id'] );
		$this->assertCount( 1, $results );
		$this->assertSame( JobStatuses::CANCELLED, $results[0]->status );
	}
}
