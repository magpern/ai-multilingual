<?php
/**
 * Spike S5 prototype — deterministic logical-id source for the ground-truth
 * oracle. No randomness, no wall clock: every id is the next integer in a
 * fixed, reproducible sequence.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

/**
 * A single generator is shared across every OracleTree that must never
 * collide — in particular, both sides of a "copy/paste from another
 * document" scenario draw from the same instance, so a pasted node can never
 * coincidentally receive an id that already exists on the other side.
 */
final class IdGenerator {

	private int $next;

	public function __construct( int $start = 1 ) {
		$this->next = $start;
	}

	public function next(): int {
		return $this->next++;
	}
}
