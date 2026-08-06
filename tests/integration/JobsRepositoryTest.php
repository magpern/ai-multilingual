<?php
/**
 * Background Translation Jobs repository integration tests (J1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\JobCheckpoint;
use WP_Error;

/**
 * Jobs J1 — repository CRUD, uniqueness, and checkpoint bounds.
 */
final class JobsRepositoryTest extends AimlTestCase {

	private BackgroundTranslationJobRepository $jobs;

	private BackgroundTranslationItemRepository $items;

	protected function setUp(): void {
		parent::setUp();
		$this->jobs  = new BackgroundTranslationJobRepository();
		$this->items = new BackgroundTranslationItemRepository();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function base_job_data( string $suffix ): array {
		return array(
			'job_type'        => 'translate_selected',
			'idempotency_key' => 'idem-' . $suffix,
			'source_type'     => 'post',
			'source_id'       => 100,
			'language_id'     => 2,
			'lock_key'        => 'post:100:2',
		);
	}

	public function test_job_insert_and_find(): void {
		$row = $this->jobs->insert( $this->base_job_data( 'insert' ) );

		$this->assertNotInstanceOf( WP_Error::class, $row );
		$this->assertSame( 'translate_selected', $row->job_type );
		$this->assertSame( 'queued', $row->status );
		$this->assertNotEmpty( $row->created_at );
		$this->assertNotEmpty( $row->updated_at );

		$found = $this->jobs->find( (int) $row->job_id );
		$this->assertNotNull( $found );
		$this->assertSame( 'idem-insert', $found->idempotency_key );
	}

	public function test_find_by_idempotency_key(): void {
		$row = $this->jobs->insert( $this->base_job_data( 'by-idem' ) );
		$this->assertNotInstanceOf( WP_Error::class, $row );

		$found = $this->jobs->find_by_idempotency_key( 'idem-by-idem' );
		$this->assertNotNull( $found );
		$this->assertSame( (int) $row->job_id, (int) $found->job_id );
	}

	public function test_idempotency_key_conflict(): void {
		$first = $this->jobs->insert( $this->base_job_data( 'conflict' ) );
		$this->assertNotInstanceOf( WP_Error::class, $first );

		$second = $this->jobs->insert( $this->base_job_data( 'conflict' ) );
		$this->assertInstanceOf( WP_Error::class, $second );
		$this->assertSame( 'job_idempotency_conflict', $second->get_error_code() );
	}

	public function test_active_lock_key_conflict(): void {
		$first = $this->jobs->insert(
			array_merge(
				$this->base_job_data( 'lock-a' ),
				array( 'active_lock_key' => 'lock-post-100-2' )
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $first );

		$second = $this->jobs->insert(
			array_merge(
				$this->base_job_data( 'lock-b' ),
				array( 'active_lock_key' => 'lock-post-100-2' )
			)
		);
		$this->assertInstanceOf( WP_Error::class, $second );
		$this->assertSame( 'job_lock_key_conflict', $second->get_error_code() );
	}

	public function test_multiple_finished_jobs_allow_null_active_lock_key(): void {
		$first  = $this->jobs->insert(
			array_merge(
				$this->base_job_data( 'finished-a' ),
				array(
					'status'          => 'completed',
					'active_lock_key' => null,
				)
			)
		);
		$second = $this->jobs->insert(
			array_merge(
				$this->base_job_data( 'finished-b' ),
				array(
					'status'          => 'completed',
					'active_lock_key' => null,
				)
			)
		);

		$this->assertNotInstanceOf( WP_Error::class, $first );
		$this->assertNotInstanceOf( WP_Error::class, $second );
	}

	public function test_find_active_by_lock_key(): void {
		$row = $this->jobs->insert(
			array_merge(
				$this->base_job_data( 'active-lock' ),
				array( 'active_lock_key' => 'active-lock-key' )
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $row );

		$found = $this->jobs->find_active_by_lock_key( 'active-lock-key' );
		$this->assertNotNull( $found );
		$this->assertSame( (int) $row->job_id, (int) $found->job_id );
	}

	public function test_job_update_rejects_forbidden_payload(): void {
		$row = $this->jobs->insert( $this->base_job_data( 'forbidden' ) );
		$this->assertNotInstanceOf( WP_Error::class, $row );

		$result = $this->jobs->update(
			(int) $row->job_id,
			array( 'source_body' => 'must not persist' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'job_invalid_payload', $result->get_error_code() );
	}

	public function test_job_checkpoint_persisted_via_repository(): void {
		$row = $this->jobs->insert(
			array_merge(
				$this->base_job_data( 'checkpoint' ),
				array(
					'checkpoint' => array(
						'checkpoint_schema_version' => 1,
						'stage'                     => 'items',
						'batch_index'               => 0,
						'segment_ids'               => array( 'seg-1' ),
						'last_item_id'              => 0,
					),
				)
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $row );
		$this->assertNotEmpty( $row->checkpoint );

		$decoded = JobCheckpoint::decode( (string) $row->checkpoint );
		$this->assertSame( 'items', $decoded['stage'] );
	}

	public function test_job_update_rejects_oversized_checkpoint(): void {
		$row = $this->jobs->insert( $this->base_job_data( 'oversized' ) );
		$this->assertNotInstanceOf( WP_Error::class, $row );

		$segments = array();
		for ( $i = 0; $i < 2000; $i++ ) {
			$segments[] = str_repeat( 'y', 20 ) . $i;
		}

		$result = $this->jobs->update(
			(int) $row->job_id,
			array(
				'checkpoint' => array(
					'segment_ids' => $segments,
				),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'job_checkpoint_too_large', $result->get_error_code() );
	}

	public function test_item_crud_and_uniqueness(): void {
		$job = $this->jobs->insert( $this->base_job_data( 'items' ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$item = $this->items->insert(
			array(
				'job_id'      => (int) $job->job_id,
				'segment_key' => 'title',
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $item );
		$this->assertSame( 'queued', $item->status );

		$dup = $this->items->insert(
			array(
				'job_id'      => (int) $job->job_id,
				'segment_key' => 'title',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $dup );
		$this->assertSame( 'job_item_duplicate', $dup->get_error_code() );

		$found = $this->items->find_by_job_and_segment( (int) $job->job_id, 'title' );
		$this->assertNotNull( $found );

		$updated = $this->items->update(
			(int) $item->item_id,
			array(
				'status'      => 'completed',
				'result_code' => 'ok',
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $updated );
		$this->assertSame( 'completed', $updated->status );
	}

	public function test_item_list_and_count_by_status(): void {
		$job = $this->jobs->insert( $this->base_job_data( 'counts' ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$this->items->insert(
			array(
				'job_id'      => (int) $job->job_id,
				'segment_key' => 'a',
				'status'      => 'queued',
			)
		);
		$this->items->insert(
			array(
				'job_id'      => (int) $job->job_id,
				'segment_key' => 'b',
				'status'      => 'completed',
			)
		);

		$queued = $this->items->list_by_job( (int) $job->job_id, 'queued' );
		$this->assertCount( 1, $queued );

		$all = $this->items->list_by_job( (int) $job->job_id );
		$this->assertCount( 2, $all );

		$counts = $this->items->count_by_status( (int) $job->job_id );
		$this->assertSame( 1, $counts['queued'] );
		$this->assertSame( 1, $counts['completed'] );
	}

	public function test_item_rejects_forbidden_payload(): void {
		$job = $this->jobs->insert( $this->base_job_data( 'item-forbidden' ) );
		$this->assertNotInstanceOf( WP_Error::class, $job );

		$result = $this->items->insert(
			array(
				'job_id'      => (int) $job->job_id,
				'segment_key' => 'x',
				'body'        => 'nope',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'job_invalid_payload', $result->get_error_code() );
	}
}
