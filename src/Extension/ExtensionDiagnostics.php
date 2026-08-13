<?php
/**
 * Bounded extension registration diagnostics.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Safe counters and last failure reason for WP-CLI / tests.
 */
final class ExtensionDiagnostics {

	public const COUNTER_EXTENSION_REGISTERED = 'extension_registered';

	public const COUNTER_META_REGISTERED = 'meta_registered';

	public const COUNTER_BLOCK_REGISTERED = 'block_registered';

	public const COUNTER_REGISTRATION_REJECTED = 'registration_rejected';

	public const COUNTER_RESOLVER_MISS = 'resolver_miss';

	public const COUNTER_RESOLVER_HIT = 'resolver_hit';

	public const COUNTER_CALLBACK_FAILURE = 'callback_failure';

	/**
	 * Named registration and resolver counters.
	 *
	 * @var array<string, int>
	 */
	private array $counters = array();

	/**
	 * Last bounded registration failure reason.
	 *
	 * @var string|null
	 */
	private ?string $last_failure = null;

	/**
	 * Increments one named counter.
	 *
	 * @param string $counter Counter name.
	 */
	public function increment( string $counter ): void {
		$this->counters[ $counter ] = ( $this->counters[ $counter ] ?? 0 ) + 1;
	}

	/**
	 * Records one bounded registration failure reason.
	 *
	 * @param string $reason Bounded failure reason.
	 */
	public function record_failure( string $reason ): void {
		$this->last_failure = substr( $reason, 0, 191 );
		$this->increment( self::COUNTER_REGISTRATION_REJECTED );
	}

	/**
	 * Returns the last bounded registration failure reason.
	 */
	public function last_failure(): ?string {
		return $this->last_failure;
	}

	/**
	 * Returns all named counters.
	 *
	 * @return array<string, int>
	 */
	public function counters(): array {
		return $this->counters;
	}

	/**
	 * Returns one named counter value.
	 *
	 * @param string $name Counter name.
	 */
	public function counter( string $name ): int {
		return $this->counters[ $name ] ?? 0;
	}

	/**
	 * Resets counters and last failure (tests).
	 */
	public function reset_for_tests(): void {
		$this->counters     = array();
		$this->last_failure = null;
	}
}
