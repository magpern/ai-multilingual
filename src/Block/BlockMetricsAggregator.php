<?php
/**
 * Strategy F request-scoped block metrics aggregator.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Translation\BlockFrontendRenderLogger;

/**
 * Listens to Strategy F structured log hooks and aggregates counters in-process.
 */
final class BlockMetricsAggregator {

	public const COUNTER_UUID_CREATED             = 'uuid_created';
	public const COUNTER_MALFORMED_UUID_DETECTED  = 'malformed_uuid_detected';
	public const COUNTER_DUPLICATE_UUID_DETECTED  = 'duplicate_uuid_detected';
	public const COUNTER_UUID_REPAIRED            = 'uuid_repaired';
	public const COUNTER_UUID_REPAIR_FAILED       = 'uuid_repair_failed';
	public const COUNTER_EXTRACTION_STARTED       = 'extraction_started';
	public const COUNTER_EXTRACTION_COMPLETED     = 'extraction_completed';
	public const COUNTER_FIELDS_EXTRACTED         = 'fields_extracted';
	public const COUNTER_FIELDS_SKIPPED           = 'fields_skipped';
	public const COUNTER_EXTRACTION_FAILED        = 'extraction_failed';
	public const COUNTER_RENDER_ATTEMPTED         = 'render_attempted';
	public const COUNTER_RENDER_COMPLETED         = 'render_completed';
	public const COUNTER_RENDER_SKIPPED           = 'render_skipped';
	public const COUNTER_RENDER_FAILED            = 'render_failed';
	public const COUNTER_POSTS_SCANNED            = 'posts_scanned';
	public const COUNTER_POSTS_MIGRATED           = 'posts_migrated';
	public const COUNTER_POSTS_ALREADY_COMPLIANT  = 'posts_already_compliant';
	public const COUNTER_POSTS_SKIPPED            = 'posts_skipped';
	public const COUNTER_MIGRATIONS_FAILED        = 'migrations_failed';
	public const COUNTER_CONCURRENT_MODIFICATIONS = 'concurrent_modifications';
	public const COUNTER_FEATURE_FLAGS_CHANGED    = 'feature_flags_changed';

	/**
	 * In-process counter values keyed by stable metric name.
	 *
	 * @var array<string, int>
	 */
	private array $counters = array();

	/**
	 * Number of render events with valid elapsed_ms timing.
	 *
	 * @var int
	 */
	private int $render_count = 0;

	/**
	 * Sum of render elapsed_ms values.
	 *
	 * @var int
	 */
	private int $render_total_elapsed_ms = 0;

	/**
	 * Maximum render elapsed_ms value observed.
	 *
	 * @var int
	 */
	private int $render_max_elapsed_ms = 0;

	/**
	 * Malformed hook payloads ignored during aggregation.
	 *
	 * @var int
	 */
	private int $ignored_event_count = 0;

	/**
	 * Whether any hook payload was ignored.
	 *
	 * @var bool
	 */
	private bool $incomplete = false;

	/**
	 * Whether hook listeners are registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Builds an empty aggregator.
	 */
	public function __construct() {
		$this->reset();
	}

	/**
	 * Stable counter keys exposed in snapshots.
	 *
	 * @return list<string>
	 */
	public static function counter_keys(): array {
		return array(
			self::COUNTER_UUID_CREATED,
			self::COUNTER_MALFORMED_UUID_DETECTED,
			self::COUNTER_DUPLICATE_UUID_DETECTED,
			self::COUNTER_UUID_REPAIRED,
			self::COUNTER_UUID_REPAIR_FAILED,
			self::COUNTER_EXTRACTION_STARTED,
			self::COUNTER_EXTRACTION_COMPLETED,
			self::COUNTER_FIELDS_EXTRACTED,
			self::COUNTER_FIELDS_SKIPPED,
			self::COUNTER_EXTRACTION_FAILED,
			self::COUNTER_RENDER_ATTEMPTED,
			self::COUNTER_RENDER_COMPLETED,
			self::COUNTER_RENDER_SKIPPED,
			self::COUNTER_RENDER_FAILED,
			self::COUNTER_POSTS_SCANNED,
			self::COUNTER_POSTS_MIGRATED,
			self::COUNTER_POSTS_ALREADY_COMPLIANT,
			self::COUNTER_POSTS_SKIPPED,
			self::COUNTER_MIGRATIONS_FAILED,
			self::COUNTER_CONCURRENT_MODIFICATIONS,
			self::COUNTER_FEATURE_FLAGS_CHANGED,
		);
	}

	/**
	 * Registers hook listeners for the current request lifecycle.
	 */
	public function register(): void {
		if ( $this->registered || ! function_exists( 'add_action' ) ) {
			return;
		}

		$this->registered = true;

		add_action( 'aiml_block_identity_log', array( $this, 'on_identity_log' ), 10, 2 );
		add_action( 'aiml_block_extraction_log', array( $this, 'on_extraction_log' ), 10, 2 );
		add_action( 'aiml_block_frontend_render_log', array( $this, 'on_frontend_render_log' ), 10, 2 );
		add_action( 'aiml_block_migration_log', array( $this, 'on_migration_log' ), 10, 2 );
		add_action( 'aiml_settings_flag_changed', array( $this, 'on_flag_changed' ), 10, 1 );
	}

	/**
	 * Returns the current request-scoped metrics snapshot.
	 */
	public function snapshot(): BlockMetricsSnapshot {
		$render_count = $this->render_count;

		return new BlockMetricsSnapshot(
			gmdate( 'c' ),
			$this->counters,
			$render_count,
			$this->render_total_elapsed_ms,
			$render_count > 0
				? (int) round( $this->render_total_elapsed_ms / $render_count )
				: 0,
			$this->render_max_elapsed_ms,
			$this->ignored_event_count,
			$this->incomplete
		);
	}

	/**
	 * Resets all counters for test isolation.
	 */
	public function reset(): void {
		$this->counters = array();

		foreach ( self::counter_keys() as $key ) {
			$this->counters[ $key ] = 0;
		}

		$this->render_count            = 0;
		$this->render_total_elapsed_ms = 0;
		$this->render_max_elapsed_ms   = 0;
		$this->ignored_event_count     = 0;
		$this->incomplete              = false;
	}

	/**
	 * Handles block identity log events.
	 *
	 * @param mixed $event   Event name.
	 * @param mixed $context Event context.
	 */
	public function on_identity_log( $event, $context ): void {
		if ( ! is_string( $event ) || ! is_array( $context ) ) {
			$this->ignore_event();

			return;
		}

		switch ( $event ) {
			case BlockIdentityLogger::EVENT_UUID_CREATED:
				$this->increment( self::COUNTER_UUID_CREATED );
				break;
			case BlockIdentityLogger::EVENT_UUID_REPLACED_INVALID:
				$this->increment( self::COUNTER_MALFORMED_UUID_DETECTED );
				break;
			case BlockIdentityLogger::EVENT_UUID_DUPLICATE_DETECTED:
				$this->increment( self::COUNTER_DUPLICATE_UUID_DETECTED );
				break;
			case BlockIdentityLogger::EVENT_UUID_DUPLICATE_REPAIRED:
				$this->increment( self::COUNTER_UUID_REPAIRED );
				break;
			case BlockIdentityLogger::EVENT_UUID_REPAIR_FAILED:
				$this->increment( self::COUNTER_UUID_REPAIR_FAILED );
				break;
		}
	}

	/**
	 * Handles block extraction log events.
	 *
	 * @param mixed $event   Event name.
	 * @param mixed $context Event context.
	 */
	public function on_extraction_log( $event, $context ): void {
		if ( ! is_string( $event ) || ! is_array( $context ) ) {
			$this->ignore_event();

			return;
		}

		switch ( $event ) {
			case BlockExtractionLogger::EVENT_BLOCK_EXTRACTED:
				$this->increment( self::COUNTER_FIELDS_EXTRACTED );
				break;
			case BlockExtractionLogger::EVENT_FIELD_SKIPPED:
				$this->increment( self::COUNTER_FIELDS_SKIPPED );
				break;
			case BlockExtractionLogger::EVENT_ADAPTER_MISSING:
				$this->increment( self::COUNTER_EXTRACTION_FAILED );
				break;
		}
	}

	/**
	 * Handles frontend render log events.
	 *
	 * @param mixed $event   Event name.
	 * @param mixed $context Event context.
	 */
	public function on_frontend_render_log( $event, $context ): void {
		if ( ! is_string( $event ) || ! is_array( $context ) ) {
			$this->ignore_event();

			return;
		}

		switch ( $event ) {
			case BlockFrontendRenderLogger::EVENT_GATE_ALLOWED:
				$this->increment( self::COUNTER_RENDER_ATTEMPTED );
				break;
			case BlockFrontendRenderLogger::EVENT_GATE_DENIED:
				$this->increment( self::COUNTER_RENDER_SKIPPED );
				break;
			case BlockFrontendRenderLogger::EVENT_RENDER_COMPLETE:
				$this->increment( self::COUNTER_RENDER_COMPLETED );
				$this->record_render_timing( $context );
				break;
			case BlockFrontendRenderLogger::EVENT_RENDER_FAILED:
				$this->increment( self::COUNTER_RENDER_FAILED );
				$this->record_render_timing( $context );
				break;
		}
	}

	/**
	 * Handles block migration log events.
	 *
	 * @param mixed $event   Event name.
	 * @param mixed $context Event context.
	 */
	public function on_migration_log( $event, $context ): void {
		if ( ! is_string( $event ) || ! is_array( $context ) ) {
			$this->ignore_event();

			return;
		}

		switch ( $event ) {
			case BlockMigrationLogger::EVENT_STARTED:
				$this->increment( self::COUNTER_POSTS_SCANNED );
				break;
			case BlockMigrationLogger::EVENT_BATCH_COMPLETE:
				$this->increment_by( self::COUNTER_POSTS_SCANNED, $this->processed_count( $context ) );
				break;
			case BlockMigrationLogger::EVENT_POST_COMPLETE:
				$this->increment( self::COUNTER_POSTS_MIGRATED );
				break;
			case BlockMigrationLogger::EVENT_SKIPPED:
				$this->increment( self::COUNTER_POSTS_SKIPPED );
				if ( BlockMigrationEligibility::REASON_ALREADY_COMPLIANT === (string) ( $context['skip_reason'] ?? '' ) ) {
					$this->increment( self::COUNTER_POSTS_ALREADY_COMPLIANT );
				}
				break;
			case BlockMigrationLogger::EVENT_POST_FAILED:
				$this->increment( self::COUNTER_MIGRATIONS_FAILED );
				break;
			case BlockMigrationLogger::EVENT_CONCURRENT_MODIFICATION:
				$this->increment( self::COUNTER_CONCURRENT_MODIFICATIONS );
				break;
		}
	}

	/**
	 * Handles production flag change audit events.
	 *
	 * @param mixed $payload Audit payload.
	 */
	public function on_flag_changed( $payload ): void {
		if ( ! is_array( $payload ) || ! isset( $payload['flag'] ) || ! is_string( $payload['flag'] ) ) {
			$this->ignore_event();

			return;
		}

		$this->increment( self::COUNTER_FEATURE_FLAGS_CHANGED );
	}

	/**
	 * Records render timing from one frontend render event context.
	 *
	 * @param array<string, mixed> $context Event context.
	 */
	private function record_render_timing( array $context ): void {
		if ( ! array_key_exists( 'elapsed_ms', $context ) ) {
			return;
		}

		$elapsed = $this->normalize_elapsed_ms( $context['elapsed_ms'] );
		if ( null === $elapsed ) {
			$this->ignore_event();

			return;
		}

		++$this->render_count;
		$this->render_total_elapsed_ms += $elapsed;
		$this->render_max_elapsed_ms    = max( $this->render_max_elapsed_ms, $elapsed );
	}

	/**
	 * Reads a batch processed count from migration context.
	 *
	 * @param array<string, mixed> $context Migration event context.
	 */
	private function processed_count( array $context ): int {
		if ( ! isset( $context['processed'] ) ) {
			return 0;
		}

		return max( 0, (int) $context['processed'] );
	}

	/**
	 * Normalizes one elapsed millisecond value.
	 *
	 * @param mixed $value Raw elapsed value.
	 */
	private function normalize_elapsed_ms( $value ): ?int {
		if ( is_int( $value ) || is_float( $value ) ) {
			$elapsed = (int) round( (float) $value );

			return $elapsed >= 0 ? $elapsed : null;
		}

		if ( is_string( $value ) && is_numeric( $value ) ) {
			$elapsed = (int) round( (float) $value );

			return $elapsed >= 0 ? $elapsed : null;
		}

		return null;
	}

	/**
	 * Increments one counter by one.
	 *
	 * @param string $counter Stable counter name.
	 */
	private function increment( string $counter ): void {
		$this->counters[ $counter ] = ( $this->counters[ $counter ] ?? 0 ) + 1;
	}

	/**
	 * Increments one counter by an arbitrary amount.
	 *
	 * @param string $counter Stable counter name.
	 * @param int    $amount  Non-negative increment.
	 */
	private function increment_by( string $counter, int $amount ): void {
		if ( $amount <= 0 ) {
			return;
		}

		$this->counters[ $counter ] = ( $this->counters[ $counter ] ?? 0 ) + $amount;
	}

	/**
	 * Marks one malformed hook payload as ignored.
	 */
	private function ignore_event(): void {
		++$this->ignored_event_count;
		$this->incomplete = true;
	}
}
