<?php
/**
 * Bounded, low-cardinality Background Translation Jobs diagnostics (plan §22).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

/**
 * Query-time job aggregates plus a fixed-key option-backed counter map.
 *
 * Deliberately not a metrics table: the counter key set is closed and small,
 * never growing with post/segment/user identity.
 */
final class BackgroundTranslationDiagnostics {

	public const OPTION = 'aiml_translation_job_diagnostics_counters';

	public const PROVIDER_ERRORS                     = 'provider_errors';
	public const STALE_SOURCE_CONFLICTS              = 'stale_source_conflicts';
	public const BUDGET_STOPS                        = 'budget_stops';
	public const ITEM_RETRIES                        = 'item_retries';
	public const CLEANUP_JOBS_DELETED                = 'cleanup_jobs_deleted';
	public const CLEANUP_ITEMS_DELETED               = 'cleanup_items_deleted';
	public const CLEANUP_ORPHANS_DELETED             = 'cleanup_orphans_deleted';
	public const STUCK_LEASES_RECOVERED              = 'stuck_leases_recovered';
	public const TM_DIRECT_REUSE                     = 'tm_direct_reuse';
	public const PROVIDER_CALLS                      = 'provider_calls';
	public const PROVIDER_INPUT_TOKENS               = 'provider_input_tokens';
	public const PROVIDER_OUTPUT_TOKENS              = 'provider_output_tokens';
	public const RETRY_AFTER_HONORS                  = 'retry_after_honors';
	public const CONCURRENCY_REJECTS                 = 'concurrency_rejects';
	public const CRASH_RECOVERY_PROVIDER_REPEAT_RISK = 'crash_recovery_provider_repeat_risk';

	/**
	 * Maximum queue age reported in seconds (30 days).
	 */
	public const QUEUE_AGE_BOUND_SECONDS = 2592000;

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
	 * Action Scheduler health probe.
	 *
	 * @var BackgroundTranslationScheduler|null
	 */
	private ?BackgroundTranslationScheduler $scheduler;

	/**
	 * Builds diagnostics.
	 *
	 * @param BackgroundTranslationJobRepository|null  $jobs       Job repository.
	 * @param BackgroundTranslationItemRepository|null $items      Item repository.
	 * @param BackgroundTranslationScheduler|null      $scheduler  AS scheduler.
	 */
	public function __construct(
		?BackgroundTranslationJobRepository $jobs = null,
		?BackgroundTranslationItemRepository $items = null,
		?BackgroundTranslationScheduler $scheduler = null
	) {
		$this->jobs      = $jobs ?? new BackgroundTranslationJobRepository();
		$this->items     = $items ?? new BackgroundTranslationItemRepository();
		$this->scheduler = $scheduler;
	}

	/**
	 * Every known counter key.
	 *
	 * @return list<string>
	 */
	public static function counter_keys(): array {
		return array(
			self::PROVIDER_ERRORS,
			self::STALE_SOURCE_CONFLICTS,
			self::BUDGET_STOPS,
			self::ITEM_RETRIES,
			self::CLEANUP_JOBS_DELETED,
			self::CLEANUP_ITEMS_DELETED,
			self::CLEANUP_ORPHANS_DELETED,
			self::STUCK_LEASES_RECOVERED,
			self::TM_DIRECT_REUSE,
			self::PROVIDER_CALLS,
			self::PROVIDER_INPUT_TOKENS,
			self::PROVIDER_OUTPUT_TOKENS,
			self::RETRY_AFTER_HONORS,
			self::CONCURRENCY_REJECTS,
			self::CRASH_RECOVERY_PROVIDER_REPEAT_RISK,
		);
	}

	/**
	 * Returns a safe diagnostics snapshot for operators.
	 *
	 * @return array<string, mixed>
	 */
	public function snapshot(): array {
		$status_counts = $this->status_counts();
		$counters      = $this->counters();
		$queue_age     = $this->jobs->queue_age_stats();
		$throughput    = $this->jobs->throughput_stats();
		$backlog       = $this->jobs->cleanup_backlog_counts();
		$stuck_leases  = $this->jobs->count_stuck_leases();

		$completed_total = (int) ( $status_counts[ JobStatuses::COMPLETED ] ?? 0 )
			+ (int) ( $status_counts[ JobStatuses::COMPLETED_WITH_ERRORS ] ?? 0 );
		$failed_total    = (int) ( $status_counts[ JobStatuses::FAILED ] ?? 0 );
		$retry_total     = (int) ( $status_counts[ JobStatuses::RETRY_WAIT ] ?? 0 );
		$item_failures   = (int) ( $counters[ self::PROVIDER_ERRORS ] ?? 0 )
			+ (int) ( $counters[ self::ITEM_RETRIES ] ?? 0 );

		$retry_rate = 0.0;
		if ( $completed_total + $failed_total + $retry_total > 0 ) {
			$retry_rate = round(
				( (int) ( $counters[ self::ITEM_RETRIES ] ?? 0 ) )
				/ max( 1, $completed_total + $failed_total + $retry_total ),
				4
			);
		}

		$provider_error_rate = 0.0;
		if ( $item_failures > 0 ) {
			$provider_error_rate = round(
				(int) ( $counters[ self::PROVIDER_ERRORS ] ?? 0 ) / max( 1, $item_failures ),
				4
			);
		}

		$health = null !== $this->scheduler
			? $this->scheduler->health()
			: array(
				'available' => false,
				'message'   => 'Action Scheduler probe not configured.',
			);

		return array(
			'status_counts'       => $status_counts,
			'queue_age'           => array(
				'count'       => (int) ( $queue_age['count'] ?? 0 ),
				'max_seconds' => min(
					self::QUEUE_AGE_BOUND_SECONDS,
					max( 0, (int) ( $queue_age['max_seconds'] ?? 0 ) )
				),
			),
			'throughput'          => $throughput,
			'retry_rate'          => $retry_rate,
			'provider_error_rate' => $provider_error_rate,
			'stale_conflicts'     => (int) ( $counters[ self::STALE_SOURCE_CONFLICTS ] ?? 0 ),
			'budget_stops'        => (int) ( $counters[ self::BUDGET_STOPS ] ?? 0 ),
			'cleanup_backlog'     => $backlog,
			'stuck_leases'        => $stuck_leases,
			'action_scheduler'    => $health,
			'counters'            => $counters,
		);
	}

	/**
	 * Job counts grouped by status (query-time).
	 *
	 * @return array<string, int>
	 */
	public function status_counts(): array {
		$counts = array_fill_keys( JobStatuses::all(), 0 );

		foreach ( JobStatuses::all() as $status ) {
			$counts[ $status ] = $this->jobs->count_by_status( $status );
		}

		return $counts;
	}

	/**
	 * Increments one bounded counter by one and persists it.
	 *
	 * Unknown keys are ignored rather than growing the option's key set.
	 *
	 * @param string $key Counter key from {@see counter_keys()}.
	 * @param int    $by  Amount to add (default 1).
	 */
	public function increment( string $key, int $by = 1 ): void {
		if ( ! in_array( $key, self::counter_keys(), true ) || $by <= 0 ) {
			return;
		}

		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}

		$counters         = $this->raw_counters();
		$counters[ $key ] = ( $counters[ $key ] ?? 0 ) + $by;

		update_option(
			self::OPTION,
			array(
				'counters'   => $counters,
				'updated_at' => gmdate( 'c' ),
			),
			false
		);
	}

	/**
	 * Returns the current counter map with every known key present.
	 *
	 * @return array<string, int>
	 */
	public function counters(): array {
		$counters = array_fill_keys( self::counter_keys(), 0 );

		foreach ( $this->raw_counters() as $key => $value ) {
			if ( array_key_exists( $key, $counters ) ) {
				$counters[ $key ] = max( 0, (int) $value );
			}
		}

		return $counters;
	}

	/**
	 * Resets every counter to zero (operator/tests).
	 */
	public function reset_counters(): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}

		update_option(
			self::OPTION,
			array(
				'counters'   => array_fill_keys( self::counter_keys(), 0 ),
				'updated_at' => gmdate( 'c' ),
			),
			false
		);
	}

	/**
	 * Loads the raw persisted counter map, tolerant of missing/malformed data.
	 *
	 * @return array<string, int>
	 */
	private function raw_counters(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$counters = is_array( $raw['counters'] ?? null ) ? $raw['counters'] : array();
		$clean    = array();

		foreach ( $counters as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$clean[ $key ] = max( 0, (int) $value );
		}

		return $clean;
	}
}
