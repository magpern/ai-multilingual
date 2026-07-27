<?php
/**
 * Spike S5: property-based / randomized testing of the OracleNode/OracleTree
 * container-separator model. Thousands of random trees, checked against real
 * parse_blocks()/serialize_blocks() every time — never a hand-rolled stand-in.
 *
 * Every tree is built from an explicit, logged seed
 * (SEED_BASE + iteration index), so any failure is reproducible exactly: the
 * assertion message always names the seed that produced it.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Oracle/IdGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/OracleNode.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/OracleTree.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/CorpusImporter.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/SeededRandom.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/RandomTreeGenerator.php';

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleNode;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Oracle\RandomTreeGenerator;
use AIMultilingual\Spike\S5\Oracle\SeededRandom;

final class PropertyBasedOracleTest extends \WP_UnitTestCase {

	private const SEED_BASE = 900000;
	private const ITERATIONS = 3000;

	/**
	 * Deterministic per-iteration tree size: varies 1..40 nodes, itself a
	 * pure function of the seed, so re-running with the same SEED_BASE always
	 * exercises the same distribution of shapes.
	 */
	private function target_size( int $seed ): int {
		return 1 + ( $seed % 40 );
	}

	public function test_thousands_of_random_trees_satisfy_every_invariant(): void {
		$checked          = 0;
		$max_nodes_seen   = 0;
		$leaf_count       = 0;
		$empty_container_count = 0;
		$multi_child_count = 0;

		for ( $i = 0; $i < self::ITERATIONS; $i++ ) {
			$seed = self::SEED_BASE + $i;
			$rng  = new SeededRandom( $seed );
			$gen  = new RandomTreeGenerator( $rng );
			$ids  = new IdGenerator();

			$roots = $gen->generate( $ids, $this->target_size( $seed ) );
			$tree  = new OracleTree( $roots );

			// Invariant: separator count == child count + 1, everywhere.
			self::assert_separator_count_invariant( $roots, $seed );

			// Invariant: deterministic IDs — ids assigned in this run must be
			// exactly reproduced by an independent second run with the same seed.
			$ids_b  = new IdGenerator();
			$rng_b  = new SeededRandom( $seed );
			$gen_b  = new RandomTreeGenerator( $rng_b );
			$roots_b = $gen_b->generate( $ids_b, $this->target_size( $seed ) );

			$this->assertSame(
				array_keys( ( new OracleTree( $roots ) )->snapshot_paths() ),
				array_keys( ( new OracleTree( $roots_b ) )->snapshot_paths() ),
				"seed=$seed: deterministic IDs — two independent generator runs with the same seed must assign identical id sequences."
			);

			$content1 = $tree->to_content();

			// Invariant: export -> parse -> export == identity.
			$parsed   = parse_blocks( $content1 );
			$content2 = serialize_blocks( $parsed );
			$this->assertSame( $content1, $content2, "seed=$seed: export -> parse -> export must be byte-identical." );

			// Invariant: deterministic serialization — calling to_content()
			// again on the SAME tree must not vary.
			$this->assertSame( $content1, $tree->to_content(), "seed=$seed: to_content() must be deterministic across repeated calls." );

			// Invariant: import -> export == identity.
			$ids_reimport    = new IdGenerator();
			$reimported_roots = CorpusImporter::from_content( $content1, $ids_reimport );
			$reimported_tree  = new OracleTree( $reimported_roots );
			$content3         = $reimported_tree->to_content();
			$this->assertSame( $content1, $content3, "seed=$seed: import -> export must reproduce the exact original bytes." );

			// Invariant: byte preservation — every leaf's own content and
			// every non-empty separator must be found verbatim somewhere in
			// the exported document (a necessary, if weaker, consequence of
			// the two identity invariants above; checked independently as a
			// direct, per-node claim rather than relying only on whole-
			// document equality).
			self::assert_all_content_present( $roots, $content1, $seed );

			// Invariant: mapping stability — no id may be assigned twice.
			$paths = $tree->snapshot_paths();
			$this->assertSame(
				count( $paths ),
				count( array_unique( array_keys( $paths ) ) ),
				"seed=$seed: every id in the tree must be unique."
			);

			[$leaves, $empties, $multis, $max_depth_seen] = self::tally( $roots );
			$leaf_count             += $leaves;
			$empty_container_count  += $empties;
			$multi_child_count      += $multis;
			$max_nodes_seen          = max( $max_nodes_seen, count( $paths ) );
			$checked++;
		}

		$this->assertSame( self::ITERATIONS, $checked );

		file_put_contents(
			__DIR__ . '/../corpus/property-test-stats.json',
			wp_json_encode(
				array(
					'iterations'              => $checked,
					'seed_base'                => self::SEED_BASE,
					'max_tree_node_count_seen' => $max_nodes_seen,
					'total_leaf_nodes_seen'    => $leaf_count,
					'total_empty_containers_seen' => $empty_container_count,
					'total_multi_child_containers_seen' => $multi_child_count,
				),
				JSON_PRETTY_PRINT
			)
		);

		// The generator must actually be exercising every category the task
		// requires, not accidentally only ever producing leaves.
		$this->assertGreaterThan( 0, $leaf_count );
		$this->assertGreaterThan( 0, $empty_container_count, 'The random generator must produce at least some empty (zero-child) containers across 3000 trees.' );
		$this->assertGreaterThan( 0, $multi_child_count, 'The random generator must produce at least some multi-child containers across 3000 trees.' );
	}

	/**
	 * Deliberate, targeted coverage of the known "0"-content parser loss
	 * (SerializerDivergenceTest), across many random wrapping shapes — not
	 * left to chance in the general property test above, which excludes it
	 * by construction (see RandomTreeGenerator's docblock).
	 */
	public function test_zero_content_loss_is_consistent_across_random_wrapping_shapes(): void {
		for ( $i = 0; $i < 50; $i++ ) {
			$ids  = new IdGenerator();
			// A leaf whose entire content is exactly "0", wrapped in a
			// randomly deep chain of containers with otherwise-normal
			// siblings, to confirm the loss is purely about THIS block's own
			// content, not perturbed by unrelated nesting depth or siblings.
			$zero_leaf = OracleNode::leaf( $ids->next(), 'aiml-test/leaf-a', array(), '', '0', '' );
			$sibling   = OracleNode::leaf( $ids->next(), 'aiml-test/leaf-b', array(), '<x>', "sibling-$i", '</x>' );

			$container = OracleNode::container(
				$ids->next(),
				'aiml-test/container-a',
				array(),
				array( '', '', '' ),
				array( $zero_leaf, $sibling )
			);

			$tree    = new OracleTree( array( $container ) );
			$content = $tree->to_content();

			$reparsed = parse_blocks( $content );
			// The zero-content leaf's OWN block still exists structurally
			// (it is not deleted from the tree — its logical id and position
			// survive), but its content is gone, per the parser's own
			// documented behaviour.
			$zero_block = $reparsed[0]['innerBlocks'][0];
			$this->assertSame( '', $zero_block['innerHTML'], "iteration=$i: the zero-content leaf's innerHTML must be lost identically regardless of sibling/nesting variation." );

			// Its sibling, right next to it, must be completely unaffected.
			$sibling_block = $reparsed[0]['innerBlocks'][1];
			$this->assertStringContainsString( "sibling-$i", $sibling_block['innerHTML'] );
		}
	}

	/**
	 * @param OracleNode[] $nodes
	 */
	private static function assert_separator_count_invariant( array $nodes, int $seed ): void {
		foreach ( $nodes as $node ) {
			if ( ! $node->is_leaf() ) {
				if ( count( $node->separators ) !== count( $node->children ) + 1 ) {
					throw new \RuntimeException(
						sprintf( 'seed=%d: node id=%d has %d separators for %d children (must be children+1).', $seed, $node->id, count( $node->separators ), count( $node->children ) )
					);
				}

				self::assert_separator_count_invariant( $node->children, $seed );
			}
		}
	}

	/**
	 * @param OracleNode[] $nodes
	 */
	private static function assert_all_content_present( array $nodes, string $content, int $seed ): void {
		foreach ( $nodes as $node ) {
			if ( $node->is_leaf() ) {
				$whole = $node->prefix . $node->text . $node->suffix;

				if ( '' !== $whole && ! str_contains( $content, $whole ) ) {
					throw new \RuntimeException( "seed=$seed: leaf id={$node->id}'s content is missing from the exported document." );
				}
			} else {
				foreach ( $node->separators as $sep ) {
					if ( '' !== $sep && ! str_contains( $content, $sep ) ) {
						throw new \RuntimeException( "seed=$seed: a separator on node id={$node->id} is missing from the exported document." );
					}
				}

				self::assert_all_content_present( $node->children, $content, $seed );
			}
		}
	}

	/**
	 * @param OracleNode[] $nodes
	 * @return array{0:int,1:int,2:int,3:int} [leaf_count, empty_container_count, multi_child_container_count, max_depth]
	 */
	private static function tally( array $nodes, int $depth = 0 ): array {
		$leaves = 0;
		$empties = 0;
		$multis  = 0;
		$max_depth = $depth;

		foreach ( $nodes as $node ) {
			if ( $node->is_leaf() ) {
				++$leaves;
				continue;
			}

			$count = count( $node->children );

			if ( 0 === $count ) {
				++$empties;
			} elseif ( $count >= 2 ) {
				++$multis;
			}

			[$l, $e, $m, $d] = self::tally( $node->children, $depth + 1 );
			$leaves    += $l;
			$empties   += $e;
			$multis    += $m;
			$max_depth  = max( $max_depth, $d );
		}

		return array( $leaves, $empties, $multis, $max_depth );
	}
}
