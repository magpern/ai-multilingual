<?php
/**
 * Spike S5 prototype — a small, self-contained deterministic PRNG (xorshift32)
 * for property-based testing.
 *
 * Deliberately not PHP's mt_rand()/rand(): those draw from global,
 * process-wide state that other code (PHPUnit itself, other tests) can
 * perturb, which would make a failing case's seed useless for reproduction.
 * This class's only state is the instance itself, seeded explicitly by the
 * caller — same seed, same call sequence, same output, forever, independent
 * of anything else running in the process.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

final class SeededRandom {

	private int $state;

	public function __construct( int $seed ) {
		// xorshift32 requires a non-zero state.
		$this->state = 0 === $seed ? 1 : ( $seed & 0xFFFFFFFF );
	}

	/**
	 * Raw next value in [0, 0xFFFFFFFF].
	 */
	public function next(): int {
		$x = $this->state;
		$x ^= ( $x << 13 ) & 0xFFFFFFFF;
		$x ^= ( $x >> 17 );
		$x ^= ( $x << 5 ) & 0xFFFFFFFF;
		$this->state = $x & 0xFFFFFFFF;

		return $this->state;
	}

	/**
	 * Inclusive integer range.
	 */
	public function int_range( int $min, int $max ): int {
		if ( $max <= $min ) {
			return $min;
		}

		return $min + ( $this->next() % ( $max - $min + 1 ) );
	}

	public function bool( float $probability_true = 0.5 ): bool {
		return ( $this->next() / 0xFFFFFFFF ) < $probability_true;
	}

	/**
	 * @param mixed[] $items
	 * @return mixed
	 */
	public function choice( array $items ) {
		if ( array() === $items ) {
			throw new \RuntimeException( 'Cannot choose from an empty list.' );
		}

		return $items[ $this->int_range( 0, count( $items ) - 1 ) ];
	}

	/**
	 * A random string drawn from the given codepoint pool (an array of
	 * single "characters", which may themselves be multi-byte UTF-8
	 * sequences — the caller controls the alphabet entirely, so this can
	 * produce ASCII, whitespace-only, UTF-8, or any mix).
	 *
	 * @param string[] $alphabet
	 */
	public function string_from( array $alphabet, int $min_len, int $max_len ): string {
		$len    = $this->int_range( $min_len, $max_len );
		$result = '';

		for ( $i = 0; $i < $len; $i++ ) {
			$result .= $this->choice( $alphabet );
		}

		return $result;
	}
}
