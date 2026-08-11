<?php
/**
 * JobsOperationAdmission unit tests (OTL.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobsOperationAdmission;
use AIMultilingual\Jobs\RequestedActions;
use PHPUnit\Framework\TestCase;

/**
 * TI.6 authoritative admission — not OTL policy.
 */
final class JobsOperationAdmissionTest extends TestCase {

	public function test_resume_allowed_only_when_paused_and_cancel_cap(): void {
		$admission = new JobsOperationAdmission( $this->items_stub( array() ) );
		$job       = (object) array(
			'job_id'           => 1,
			'status'           => JobStatuses::PAUSED,
			'requested_action' => RequestedActions::NONE,
		);

		$denied = $admission->admit(
			JobsOperationAdmission::OP_RESUME,
			$job,
			array(
				'can_cancel' => false,
				'can_run'    => true,
			)
		);
		$this->assertFalse( $denied['allowed'] );
		$this->assertSame( JobsOperationAdmission::REASON_CAPABILITY_DENIED, $denied['reason_code'] );

		$allowed = $admission->admit(
			JobsOperationAdmission::OP_RESUME,
			$job,
			array(
				'can_cancel' => true,
				'can_run'    => true,
			)
		);
		$this->assertTrue( $allowed['allowed'] );
		$this->assertSame( JobsOperationAdmission::SCOPE_JOB, $allowed['mutation_scope'] );

		$running = (object) array(
			'job_id'           => 2,
			'status'           => JobStatuses::RUNNING,
			'requested_action' => RequestedActions::NONE,
		);
		$nope    = $admission->admit( JobsOperationAdmission::OP_RESUME, $running, array( 'can_cancel' => true ) );
		$this->assertFalse( $nope['allowed'] );
	}

	public function test_retry_failed_requires_failed_items_and_run_cap(): void {
		$empty    = new JobsOperationAdmission( $this->items_stub( array() ) );
		$job      = (object) array(
			'job_id' => 9,
			'status' => JobStatuses::FAILED,
		);
		$no_items = $empty->admit( JobsOperationAdmission::OP_RETRY_FAILED, $job, array( 'can_run' => true ) );
		$this->assertFalse( $no_items['allowed'] );
		$this->assertSame( JobsOperationAdmission::REASON_NO_FAILED_ITEMS, $no_items['reason_code'] );

		$with = new JobsOperationAdmission(
			$this->items_stub(
				array(
					(object) array(
						'item_id' => 1,
						'status'  => ItemStatuses::FAILED,
					),
				)
			)
		);
		$ok   = $with->admit( JobsOperationAdmission::OP_RETRY_FAILED, $job, array( 'can_run' => true ) );
		$this->assertTrue( $ok['allowed'] );

		$completed = (object) array(
			'job_id' => 10,
			'status' => JobStatuses::COMPLETED,
		);
		$blocked   = $with->admit( JobsOperationAdmission::OP_RETRY_FAILED, $completed, array( 'can_run' => true ) );
		$this->assertFalse( $blocked['allowed'] );
	}

	public function test_mutation_scope_is_always_job(): void {
		$admission = new JobsOperationAdmission( $this->items_stub( array() ) );
		$result    = $admission->admit( JobsOperationAdmission::OP_RESUME, null, array() );
		$this->assertSame( JobsOperationAdmission::SCOPE_JOB, $result['mutation_scope'] );
	}

	/**
	 * @param object[] $failed Failed items.
	 */
	private function items_stub( array $failed ): object {
		return new class( $failed ) {
			/**
			 * @param object[] $failed Failed rows.
			 */
			public function __construct( private array $failed ) {
			}

			/**
			 * @param int         $job_id Job id.
			 * @param string|null $status Status.
			 * @return object[]
			 */
			public function list_by_job( int $job_id, ?string $status = null ): array {
				unset( $job_id );
				if ( ItemStatuses::FAILED === $status ) {
					return $this->failed;
				}
				return array();
			}
		};
	}
}
