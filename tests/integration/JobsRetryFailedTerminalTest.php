<?php
/**
 * Terminal retry-failed integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobStatuses;
use WP_Error;

/**
 * Verifies explicit operator reopening of eligible terminal jobs.
 */
final class JobsRetryFailedTerminalTest extends AimlTestCase {

	public function test_failed_and_completed_with_errors_can_requeue_failed_items(): void {
		foreach ( array( JobStatuses::FAILED, JobStatuses::COMPLETED_WITH_ERRORS ) as $status ) {
			$jobs    = new BackgroundTranslationJobRepository();
			$items   = new BackgroundTranslationItemRepository();
			$service = new BackgroundTranslationJobService( $jobs, $items );
			$suffix  = $status . '-' . wp_generate_uuid4();
			$job     = $jobs->insert(
				array(
					'job_type'        => 'translate_selected',
					'status'          => $status,
					'idempotency_key' => hash( 'sha256', 'retry|' . $suffix ),
					'source_type'     => 'post',
					'source_id'       => abs( crc32( $suffix ) ),
					'language_id'     => 2,
					'lock_key'        => hash( 'sha256', 'lock|' . $suffix ),
					'active_lock_key' => null,
					'finished_at'     => current_time( 'mysql', true ),
				)
			);
			$this->assertNotInstanceOf( WP_Error::class, $job );
			$item = $items->insert(
				array(
					'job_id'             => (int) $job->job_id,
					'segment_key'        => 'title',
					'status'             => ItemStatuses::FAILED,
					'last_error_code'    => 'provider_error',
					'last_error_message' => 'Previous failure',
				)
			);
			$this->assertNotInstanceOf( WP_Error::class, $item );

			$result = $service->retry_failed_items( (int) $job->job_id );

			$this->assertNotInstanceOf( WP_Error::class, $result );
			$this->assertSame( JobStatuses::QUEUED, $result->status );
			$requeued = $items->find( (int) $item->item_id );
			$this->assertSame( ItemStatuses::QUEUED, $requeued->status );
			$this->assertSame( 'provider_error', $requeued->last_error_code );
		}
	}
}
