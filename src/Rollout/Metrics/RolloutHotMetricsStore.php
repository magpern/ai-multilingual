<?php
/**
 * Hot operational metrics window (short retention).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout\Metrics;

/**
 * Bounded in-memory/transient counters for recent rollout health.
 */
final class RolloutHotMetricsStore {

	public const OPTION = 'aiml_rollout_hot_metrics';

	/**
	 * Maximum distinct counter keys retained.
	 */
	private const MAX_KEYS = 128;

	/**
	 * @param array<string, int> $counters Counter map.
	 */
	public function __construct(
		private array $counters = array(),
		private bool $incomplete = false,
	) {
	}

	/**
	 * Loads hot metrics from storage or returns empty store.
	 */
	public static function load(): self {
		if ( ! function_exists( 'get_option' ) ) {
			return new self();
		}

		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return new self();
		}

		$counters = is_array( $raw['counters'] ?? null ) ? $raw['counters'] : array();
		$clean    = array();

		foreach ( $counters as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$clean[ $key ] = max( 0, (int) $value );
		}

		return new self( $clean, ! empty( $raw['incomplete'] ) );
	}

	/**
	 * Increments one hot counter in-process and persists.
	 */
	public function increment( string $key, int $amount = 1 ): void {
		if ( ! RolloutMetricsRegistry::is_valid_key( $key ) && ! str_starts_with( $key, 'deny:' ) ) {
			return;
		}

		$this->counters[ $key ] = ( $this->counters[ $key ] ?? 0 ) + max( 0, $amount );

		if ( count( $this->counters ) > self::MAX_KEYS ) {
			$this->counters = array_slice( $this->counters, -self::MAX_KEYS, null, true );
		}

		$this->persist();
	}

	/**
	 * Marks telemetry incomplete without affecting counters.
	 */
	public function mark_incomplete(): void {
		$this->incomplete = true;
		$this->persist();
	}

	/**
	 * @return array<string, int>
	 */
	public function counters(): array {
		return $this->counters;
	}

	public function is_incomplete(): bool {
		return $this->incomplete;
	}

	/**
	 * Resets hot metrics (operator action).
	 */
	public function reset(): void {
		$this->counters   = array();
		$this->incomplete = false;
		$this->persist();
	}

	private function persist(): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}

		update_option(
			self::OPTION,
			array(
				'counters'    => $this->counters,
				'incomplete'  => $this->incomplete,
				'updated_at'  => gmdate( 'c' ),
			),
			false
		);
	}
}
