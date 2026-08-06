<?php
/**
 * Job-level lease claim, heartbeat, release, and stale recovery.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use WP_Error;

/**
 * Option B lock model lease operations (plan §7).
 */
final class JobLeaseService {

	/**
	 * Job repository.
	 *
	 * @var BackgroundTranslationJobRepository
	 */
	private BackgroundTranslationJobRepository $jobs;

	/**
	 * Item repository.
	 *
	 * @var BackgroundTranslationItemRepository
	 */
	private BackgroundTranslationItemRepository $items;

	/**
	 * Progress reconciler.
	 *
	 * @var JobProgressReconciler
	 */
	private JobProgressReconciler $reconciler;

	/**
	 * Builds the lease service.
	 *
	 * @param BackgroundTranslationJobRepository|null  $jobs       Job repository.
	 * @param BackgroundTranslationItemRepository|null $items      Item repository.
	 * @param JobProgressReconciler|null               $reconciler Progress reconciler.
	 */
	public function __construct(
		?BackgroundTranslationJobRepository $jobs = null,
		?BackgroundTranslationItemRepository $items = null,
		?JobProgressReconciler $reconciler = null
	) {
		$this->jobs       = $jobs ?? new BackgroundTranslationJobRepository();
		$this->items      = $items ?? new BackgroundTranslationItemRepository();
		$this->reconciler = $reconciler ?? new JobProgressReconciler( $this->jobs, $this->items );
	}

	/**
	 * Active lock key value for a newly created non-terminal job.
	 *
	 * @param string $lock_key Stable object+language lock key.
	 */
	public function active_lock_for_create( string $lock_key ): string {
		return $lock_key;
	}

	/**
	 * Atomically claim a job lease.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $owner_token Worker token.
	 * @param int    $ttl_seconds Lease TTL (default 300).
	 * @return object|WP_Error|null
	 */
	public function claim( int $job_id, string $owner_token, int $ttl_seconds = JobBounds::DEFAULT_LEASE_TTL_SECONDS ) {
		$now = current_time( 'mysql', true );

		return $this->jobs->claim_lease( $job_id, $owner_token, $ttl_seconds, $now );
	}

	/**
	 * Extend lease expiry for the owning worker.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $owner_token Worker token.
	 * @param int    $ttl_seconds Lease TTL.
	 * @return object|WP_Error|null
	 */
	public function heartbeat( int $job_id, string $owner_token, int $ttl_seconds = JobBounds::DEFAULT_LEASE_TTL_SECONDS ) {
		$now = current_time( 'mysql', true );

		return $this->jobs->heartbeat_lease( $job_id, $owner_token, $ttl_seconds, $now );
	}

	/**
	 * Release lease fields without clearing active_lock_key.
	 *
	 * @param int    $job_id      Job id.
	 * @param string $owner_token Worker token.
	 * @return object|WP_Error|null
	 */
	public function release( int $job_id, string $owner_token ) {
		return $this->jobs->release_lease( $job_id, $owner_token );
	}

	/**
	 * Clear active_lock_key when a job reaches a terminal status.
	 *
	 * @param int $job_id Job id.
	 * @return object|WP_Error|null
	 */
	public function clear_active_lock_on_terminal( int $job_id ) {
		return $this->jobs->update(
			$job_id,
			array(
				'active_lock_key'    => null,
				'lease_owner'        => '',
				'lease_expires_at'   => null,
				'lease_heartbeat_at' => null,
			)
		);
	}

	/**
	 * Reclaim expired leases and reset orphaned running items.
	 *
	 * @param string|null $now Optional UTC datetime override (tests).
	 * @return list<object> Reclaimed job rows.
	 */
	public function recover_stale_leases( ?string $now = null ): array {
		$now   = $now ?? current_time( 'mysql', true );
		$stale = $this->jobs->find_stale_leases( $now );
		$out   = array();

		foreach ( $stale as $job ) {
			$this->items->reset_running_to_queued( (int) $job->job_id );

			$released = $this->jobs->update(
				(int) $job->job_id,
				array(
					'lease_owner'        => '',
					'lease_expires_at'   => null,
					'lease_heartbeat_at' => null,
				)
			);

			if ( is_wp_error( $released ) ) {
				continue;
			}

			$this->reconciler->reconcile( (int) $job->job_id );

			$fresh = $this->jobs->find( (int) $job->job_id );
			if ( null !== $fresh ) {
				$out[] = $fresh;
			}
		}

		return $out;
	}
}
