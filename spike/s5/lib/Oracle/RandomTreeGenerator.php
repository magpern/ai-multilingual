<?php
/**
 * Spike S5 prototype — builds random OracleNode trees from a seeded PRNG, for
 * property-based testing of the container/separator model.
 *
 * Deliberately synthetic: block names are drawn from a fixed
 * `aiml-test/*` namespace, never `core/*`, so every generated tree's
 * round-trip reasoning is unaffected by strip_core_block_namespace() (a real,
 * Phase-0-confirmed divergence that is irrelevant to what THIS generator
 * exists to stress — the separator model, not namespace handling).
 *
 * Deliberately avoids two things that would make a "round trip" failure
 * meaningless noise rather than a real finding:
 *  - `<`, `>`, `-` are excluded from every random alphabet, so no randomly
 *    assembled string can ever accidentally spell "<!-- wp:" and be
 *    misparsed as a real block boundary. Adversarial malformed-markup
 *    coverage is Phase 0's job (hand-built, deliberate), not this
 *    generator's.
 *  - a leaf's full content (prefix+text+suffix) and a zero-child container's
 *    sole separator are never left as '' or exactly "0" — both are real,
 *    already-documented parser-level content losses (see
 *    SerializerDivergenceTest::test_content_that_is_the_string_zero_is_lost_at_parse_time),
 *    not something this generator's general round-trip property should
 *    stumble into by chance. A DEDICATED, deliberate test exercises that
 *    case directly instead — see PropertyBasedOracleTest's zero-content
 *    tests.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

final class RandomTreeGenerator {

	private const SAFE_ASCII      = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', ' ', '.', ',', '!', '?', '_', '0', '1', '2', '3' );
	private const WHITESPACE_ONLY = array( ' ', "\t", "\n" );
	private const UTF8_CHARS      = array( 'é', 'ñ', 'ß', 'Ω', 'β', '中', '文', '日', '本', '😀', '🎉', '漢' );

	private SeededRandom $rng;

	public function __construct( SeededRandom $rng ) {
		$this->rng = $rng;
	}

	/**
	 * Builds one random tree with roughly $target_nodes nodes (leaves +
	 * containers combined), returned as a one-element roots array (a single
	 * root container, unless $target_nodes is small enough to land on a
	 * single leaf).
	 *
	 * @return OracleNode[]
	 */
	public function generate( IdGenerator $ids, int $target_nodes, int $max_depth = 6 ): array {
		$budget = max( 1, $target_nodes );

		return array( $this->generate_node( $ids, 0, $max_depth, $budget ) );
	}

	private function generate_node( IdGenerator $ids, int $depth, int $max_depth, int &$budget ): OracleNode {
		--$budget;

		$force_leaf = $depth >= $max_depth || $budget <= 0;

		if ( $force_leaf || $this->rng->bool( 0.4 ) ) {
			return $this->generate_leaf( $ids );
		}

		// 0..4 children: 0 (empty container), 1 (single-child), 2-4 (multi-child).
		$child_count = $this->rng->int_range( 0, 4 );
		$children    = array();

		for ( $i = 0; $i < $child_count; $i++ ) {
			if ( $budget <= 0 ) {
				break;
			}

			$children[] = $this->generate_node( $ids, $depth + 1, $max_depth, $budget );
		}

		$separators = $this->generate_separators( count( $children ) );

		return OracleNode::container(
			$ids->next(),
			$this->rng->choice( array( 'aiml-test/container-a', 'aiml-test/container-b', 'aiml-test/container-c' ) ),
			array(),
			$separators,
			$children
		);
	}

	private function generate_leaf( IdGenerator $ids ): OracleNode {
		do {
			$prefix = $this->random_content_piece();
			$text   = $this->random_content_piece();
			$suffix = $this->random_content_piece();
			$whole  = $prefix . $text . $suffix;
		} while ( '' === $whole || '0' === $whole );

		return OracleNode::leaf(
			$ids->next(),
			$this->rng->choice( array( 'aiml-test/leaf-a', 'aiml-test/leaf-b', 'aiml-test/leaf-c' ) ),
			array(),
			$prefix,
			$text,
			$suffix
		);
	}

	/**
	 * @return string[] Exactly $child_count + 1 entries.
	 */
	private function generate_separators( int $child_count ): array {
		$n          = $child_count + 1;
		$separators = array();

		for ( $i = 0; $i < $n; $i++ ) {
			$separators[] = $this->random_separator();
		}

		if ( 0 === $child_count ) {
			// The single slot doubles as the whole block's content — must
			// not collide with the same empty()-truthy parser loss a leaf
			// with no content would hit (see class docblock).
			while ( '' === $separators[0] || '0' === $separators[0] ) {
				$separators[0] = $this->random_separator( true );
			}
		}

		return $separators;
	}

	/**
	 * One of: empty, whitespace-only, UTF-8, long, or mixed-ASCII — covering
	 * every separator style the task requires, chosen per-slot so a single
	 * generated tree can (and typically will) mix several styles across its
	 * containers.
	 *
	 * @param bool $forbid_empty Used only when regenerating a zero-child
	 *                           container's sole separator, to avoid an
	 *                           infinite loop always landing back on empty.
	 */
	private function random_separator( bool $forbid_empty = false ): string {
		$style = $this->rng->int_range( 0, $forbid_empty ? 3 : 4 );

		switch ( $style ) {
			case 0:
				return $forbid_empty ? $this->rng->string_from( self::SAFE_ASCII, 1, 3 ) : '';
			case 1:
				return $this->rng->string_from( self::WHITESPACE_ONLY, 1, 4 );
			case 2:
				return $this->rng->string_from( self::UTF8_CHARS, 1, 5 );
			case 3:
				return $this->rng->string_from( self::SAFE_ASCII, 200, 600 );
			default:
				// Mixed: concatenate two different styles.
				$a = $this->rng->string_from( self::SAFE_ASCII, 1, 4 );
				$b = $this->rng->string_from( self::UTF8_CHARS, 1, 3 );
				$c = $this->rng->string_from( self::WHITESPACE_ONLY, 0, 2 );
				return $a . $c . $b;
		}
	}

	private function random_content_piece(): string {
		return $this->rng->string_from( self::SAFE_ASCII, 0, 8 );
	}
}
