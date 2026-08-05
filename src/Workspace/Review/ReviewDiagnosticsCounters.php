<?php
/**
 * Bounded, low-cardinality Review Workflow diagnostic counters (ADR-0015 §13).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

/**
 * Fixed-key option-backed counters for cross-request review diagnostics.
 *
 * Deliberately not a metrics table: the key set is closed and small (never
 * grows with post/segment/user identity), matching the "bounded diagnostics,
 * no high-cardinality persistent metrics" freeze. Counts that are cheap to
 * derive at query time (pending/approved/rejected totals, pending age) live
 * in {@see \AIMultilingual\Translation\Store} instead of here.
 */
final class ReviewDiagnosticsCounters {

	public const OPTION = 'aiml_review_diagnostics_counters';

	public const CONFLICTS             = 'conflicts';
	public const APPROVAL_FAILURES     = 'approval_failures';
	public const QA_BLOCKED_APPROVALS  = 'qa_blocked_approvals';
	public const TM_WRITE_BACK_SUCCESS = 'tm_write_back_success';
	public const TM_WRITE_BACK_FAILURE = 'tm_write_back_failure';

	/**
	 * Every known counter key.
	 *
	 * @return list<string>
	 */
	public static function keys(): array {
		return array(
			self::CONFLICTS,
			self::APPROVAL_FAILURES,
			self::QA_BLOCKED_APPROVALS,
			self::TM_WRITE_BACK_SUCCESS,
			self::TM_WRITE_BACK_FAILURE,
		);
	}

	/**
	 * Increments one bounded counter by one and persists it.
	 *
	 * Unknown keys are ignored rather than growing the option's key set.
	 *
	 * @param string $key Counter key from {@see keys()}.
	 */
	public function increment( string $key ): void {
		if ( ! in_array( $key, self::keys(), true ) ) {
			return;
		}

		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}

		$counters         = $this->raw_counters();
		$counters[ $key ] = ( $counters[ $key ] ?? 0 ) + 1;

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
		$counters = array_fill_keys( self::keys(), 0 );

		foreach ( $this->raw_counters() as $key => $value ) {
			if ( array_key_exists( $key, $counters ) ) {
				$counters[ $key ] = max( 0, (int) $value );
			}
		}

		return $counters;
	}

	/**
	 * Resets every counter to zero (operator action).
	 */
	public function reset(): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}

		update_option(
			self::OPTION,
			array(
				'counters'   => array_fill_keys( self::keys(), 0 ),
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
