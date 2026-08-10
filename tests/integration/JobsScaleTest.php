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

	public function test_claims_five_hundred_items_without_duplicates(): void {
		$jobs  = new BackgroundTranslationJobRepository();
		$items = new BackgroundTranslationItemRepository();
		$job   = $jobs->insert(
			array(
				'job_type'        => 'translate_selected',
				'status'          => 'queued',
				'idempotency_key' => hash( 'sha256', __METHOD__ ),
				'source_type'     => 'post',
				'source_id'       => 900002,
				'language_id'     => 2,
				'lock_key'        => hash( 'sha256', 'scale-lock-500' ),
				'active_lock_key' => hash( 'sha256', 'scale-lock-500' ),
			)
		);
		$this->assertNotInstanceOf( WP_Error::class, $job );

		for ( $i = 0; $i < 500; ++$i ) {
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
		for ( $i = 0; $i < 500; ++$i ) {
			$claimed = $items->claim_next( (int) $job->job_id );
			$this->assertNotNull( $claimed );
			$claimed_ids[] = (int) $claimed->item_id;
		}

		$this->assertCount( 500, array_unique( $claimed_ids ) );
		$this->assertNull( $items->claim_next( (int) $job->job_id ) );
	}

	public function test_two_jobs_cover_one_thousand_items(): void {
		$jobs  = new BackgroundTranslationJobRepository();
		$items = new BackgroundTranslationItemRepository();
		$total = 0;

		for ( $j = 0; $j < 2; ++$j ) {
			$job = $jobs->insert(
				array(
					'job_type'        => 'translate_selected',
					'status'          => 'queued',
					'idempotency_key' => hash( 'sha256', __METHOD__ . '|' . $j ),
					'source_type'     => 'post',
					'source_id'       => 910000 + $j,
					'language_id'     => 2,
					'lock_key'        => hash( 'sha256', 'scale-lock-1k-' . $j ),
					'active_lock_key' => hash( 'sha256', 'scale-lock-1k-' . $j ),
				)
			);
			$this->assertNotInstanceOf( WP_Error::class, $job );

			for ( $i = 0; $i < 500; ++$i ) {
				$inserted = $items->insert(
					array(
						'job_id'      => (int) $job->job_id,
						'segment_key' => sprintf( 'segment-%03d', $i ),
						'status'      => ItemStatuses::QUEUED,
					)
				);
				$this->assertNotInstanceOf( WP_Error::class, $inserted );
				++$total;
			}
		}

		$this->assertSame( 1000, $total );
	}
}
