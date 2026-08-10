<?php
/**
 * Bounded Jobs scale integration test.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\ItemStatuses;
use WP_Error;

/**
 * Verifies deterministic claiming across a 100-item workload.
 */
final class JobsScaleTest extends AimlTestCase {

	public function test_claims_one_hundred_items_without_duplicates(): void {
		$jobs  = new BackgroundTranslationJobRepository();
		$items = new BackgroundTranslationItemRepository();
		$job   = $jobs->insert(
			array(
				'job_type'        => 'translate_selected',
				'status'          => 'queued',
				'idempotency_key' => hash( 'sha256', __METHOD__ ),
				'source_type'     => 'post',
				'source_id'       => 900001,
				'language_id'     => 2,
				'lock_key'        => hash( 'sha256', 'scale-lock' ),
				'active_lock_key' => hash( 'sha256', 'scale-lock' ),
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );

		for ( $i = 0; $i < 100; ++$i ) {
			$inserted = $items->insert(
				array(
					'job_id'      => (int) $job->job_id,
					'segment_key' => sprintf( 'segment-%03d', $i ),
					'status'      => ItemStatuses::QUEUED,
				)
			);
			$this->assertNotInstanceOf( WP_Error::class, $inserted );
		}

		$claimed_ids = array();
		for ( $i = 0; $i < 100; ++$i ) {
			$claimed = $items->claim_next( (int) $job->job_id );
			$this->assertNotNull( $claimed );
			$claimed_ids[] = (int) $claimed->item_id;
		}

		$this->assertCount( 100, array_unique( $claimed_ids ) );
		$this->assertNull( $items->claim_next( (int) $job->job_id ) );
	}
}
