<?php
/**
 * Compatibility status value object.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

/**
 * Bounded compatibility/lifecycle state for one integration.
 */
final class CompatibilityStatus {

	/**
	 * Builds a compatibility status.
	 *
	 * @param string $state  One of Contract::STATE_*.
	 * @param string $reason Low-cardinality reason code (no secrets/bodies).
	 * @throws \InvalidArgumentException When state is not in the frozen vocabulary.
	 */
	public function __construct(
		private string $state,
		private string $reason = '',
	) {
		if ( ! in_array( $state, Contract::compatibility_states(), true ) ) {
			throw new \InvalidArgumentException( 'Invalid integration compatibility state.' );
		}
	}

	/**
	 * Compatibility state token.
	 */
	public function state(): string {
		return $this->state;
	}

	/**
	 * Bounded reason code.
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * Whether extraction may run.
	 */
	public function allows_operation(): bool {
		return Contract::STATE_COMPATIBLE === $this->state
			|| Contract::STATE_AVAILABLE === $this->state
			|| Contract::STATE_DEGRADED === $this->state;
	}

	/**
	 * Whether overlay application is safe.
	 */
	public function allows_overlay(): bool {
		return Contract::STATE_COMPATIBLE === $this->state
			|| Contract::STATE_AVAILABLE === $this->state;
	}
}
