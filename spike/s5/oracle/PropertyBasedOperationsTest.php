<?php
/**
 * Spike S5: property-based testing of random OPERATION SEQUENCES
 * (insert/delete/reorder/checkpoint/undo/redo) against random trees —
 * checking that the container-separator invariant survives every mutation,
 * that ids stay unique (mapping stability), that inserted separator slots are
 * never anything but '' (no inferred content), and that undo/redo restore
 * exact byte-for-byte content, not just "close enough".
 *
 * A shadow history/redo stack of CONTENT STRINGS mirrors OracleTree's own
 * internal push/pop exactly, so this test can predict when undo()/redo()
 * should succeed or throw, and what content they must produce when they do.
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
require_once dirname( __DIR__ ) . '/lib/Oracle/SeededRandom.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/RandomTreeGenerator.php';

use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleNode;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Oracle\RandomTreeGenerator;
use AIMultilingual\Spike\S5\Oracle\SeededRandom;

final class PropertyBasedOperationsTest extends \WP_UnitTestCase {

	private const SEED_BASE       = 700000;
	private const ITERATIONS      = 2000;
	private const OPS_PER_ITERATION = 8;

	public function test_random_operation_sequences_preserve_every_invariant(): void {
		$actions_applied = array(
			'insert'     => 0,
			'delete'     => 0,
			'reorder'    => 0,
			'checkpoint' => 0,
			'undo'       => 0,
			'redo'       => 0,
		);
		$undo_verified = 0;
		$redo_verified = 0;

		for ( $iteration = 0; $iteration < self::ITERATIONS; $iteration++ ) {
			$seed = self::SEED_BASE + $iteration;
			$rng  = new SeededRandom( $seed );
			$gen  = new RandomTreeGenerator( $rng );
			$ids  = new IdGenerator();

			$roots = $gen->generate( $ids, 1 + ( $seed % 15 ) );
			$tree  = new OracleTree( $roots );

			$shadow_history = array(); // mirrors OracleTree's own $history: content strings.
			$shadow_redo    = array(); // mirrors OracleTree's own $redo_stack.

			for ( $op = 0; $op < self::OPS_PER_ITERATION; $op++ ) {
				$action = $rng->choice( array( 'insert', 'delete', 'reorder', 'checkpoint', 'undo', 'redo' ) );
				$ids_present = array_keys( $tree->snapshot_paths() );

				switch ( $action ) {
					case 'insert':
						$parent_id = $this->pick_container_or_null( $tree, $rng, $ids_present );
						$index     = $rng->int_range( 0, 3 );
						$new_node  = $gen->generate( $ids, 1 )[0];

						try {
							$tree->insert( $parent_id, $index, $new_node );
							$actions_applied['insert']++;

							// No inferred content: a freshly inserted slot's
							// separator must be exactly '', never anything
							// synthesized.
							if ( null !== $parent_id ) {
								$parent = $tree->find( $parent_id );
								if ( null !== $parent && ! $parent->is_leaf() ) {
									$clamped_index = min( $index, count( $parent->separators ) - 1 );
									$this->assertSame( '', $parent->separators[ $clamped_index ] ?? '', "seed=$seed op=$op: a newly inserted separator slot must default to '', never inferred content." );
								}
							}
						} catch ( \RuntimeException $e ) {
							// Invalid target for this random draw — no state change, nothing to verify.
						}
						break;

					case 'delete':
						if ( array() === $ids_present ) {
							break;
						}

						$target = $rng->choice( $ids_present );

						try {
							$tree->delete( $target );
							$actions_applied['delete']++;
						} catch ( \RuntimeException $e ) {
							// Deleting a node that turned out not to exist any more, etc.
						}
						break;

					case 'reorder':
						$parent_id = $this->pick_container_or_null( $tree, $rng, $ids_present );
						$siblings  = null === $parent_id ? $tree->roots() : ( $tree->find( $parent_id )->children ?? array() );

						if ( count( $siblings ) >= 2 ) {
							$from = $rng->int_range( 0, count( $siblings ) - 1 );
							$to   = $rng->int_range( 0, count( $siblings ) - 1 );

							try {
								$tree->reorder( $parent_id, $from, $to );
								$actions_applied['reorder']++;
							} catch ( \RuntimeException $e ) {
								// Ignored — this random draw did not land on a valid target.
							}
						}
						break;

					case 'checkpoint':
						$tree->checkpoint();
						$shadow_history[] = $tree->to_content();
						$shadow_redo      = array();
						$actions_applied['checkpoint']++;
						break;

					case 'undo':
						$pre_undo_content = $tree->to_content();

						try {
							$tree->undo();
							$actions_applied['undo']++;

							$expected = array_pop( $shadow_history );
							$this->assertNotNull( $expected, "seed=$seed op=$op: OracleTree's undo() succeeded but the shadow history was empty — the two are out of sync." );
							$this->assertSame( $expected, $tree->to_content(), "seed=$seed op=$op: undo() must restore the exact checkpointed content." );
							// Mirrors undo()'s own behaviour: the pre-undo
							// state becomes available to redo().
							$shadow_redo[] = $pre_undo_content;
							$undo_verified++;
						} catch ( \RuntimeException $e ) {
							$this->assertSame( array(), $shadow_history, "seed=$seed op=$op: OracleTree's undo() threw (no history) but the shadow history is non-empty — out of sync." );
						}
						break;

					case 'redo':
						$pre_redo_content = $tree->to_content();

						try {
							$tree->redo();
							$actions_applied['redo']++;

							$expected = array_pop( $shadow_redo );
							$this->assertNotNull( $expected, "seed=$seed op=$op: OracleTree's redo() succeeded but the shadow redo stack was empty — out of sync." );
							$this->assertSame( $expected, $tree->to_content(), "seed=$seed op=$op: redo() must restore the exact pre-undo content." );
							$shadow_history[] = $pre_redo_content;
							$redo_verified++;
						} catch ( \RuntimeException $e ) {
							$this->assertSame( array(), $shadow_redo, "seed=$seed op=$op: OracleTree's redo() threw (nothing to redo) but the shadow redo stack is non-empty — out of sync." );
						}
						break;
				}

				// After EVERY action (whether it applied or was skipped): the
				// structural invariant must hold, and every id must still be
				// unique — mapping stability under arbitrary mutation.
				self::assert_separator_invariant( $tree->roots(), $seed, $op );

				$paths = $tree->snapshot_paths();
				$this->assertSame(
					count( $paths ),
					count( array_unique( array_keys( $paths ) ) ),
					"seed=$seed op=$op action=$action: every id must remain unique after the operation."
				);
			}
		}

		file_put_contents(
			__DIR__ . '/../corpus/property-operations-stats.json',
			wp_json_encode(
				array(
					'iterations'          => self::ITERATIONS,
					'ops_per_iteration'   => self::OPS_PER_ITERATION,
					'actions_applied'     => $actions_applied,
					'undo_verified_count' => $undo_verified,
					'redo_verified_count' => $redo_verified,
				),
				JSON_PRETTY_PRINT
			)
		);

		$this->assertGreaterThan( 0, $actions_applied['insert'] );
		$this->assertGreaterThan( 0, $actions_applied['delete'] );
		$this->assertGreaterThan( 0, $actions_applied['reorder'] );
		$this->assertGreaterThan( 0, $undo_verified, 'At least some undo()s across 2000 iterations must have had real history to restore.' );
		$this->assertGreaterThan( 0, $redo_verified, 'At least some redo()s across 2000 iterations must have had real forward state to restore.' );
	}

	/**
	 * @param int[] $ids_present
	 */
	private function pick_container_or_null( OracleTree $tree, SeededRandom $rng, array $ids_present ): ?int {
		if ( array() === $ids_present || $rng->bool( 0.3 ) ) {
			return null; // root level.
		}

		$candidate_id = $rng->choice( $ids_present );
		$node         = $tree->find( $candidate_id );

		return ( null !== $node && ! $node->is_leaf() ) ? $node->id : null;
	}

	/**
	 * @param OracleNode[] $nodes
	 */
	private static function assert_separator_invariant( array $nodes, int $seed, int $op ): void {
		foreach ( $nodes as $node ) {
			if ( ! $node->is_leaf() ) {
				self::assertSameSeparatorCount( $node, $seed, $op );
				self::assert_separator_invariant( $node->children, $seed, $op );
			}
		}
	}

	private static function assertSameSeparatorCount( OracleNode $node, int $seed, int $op ): void {
		if ( count( $node->separators ) !== count( $node->children ) + 1 ) {
			throw new \RuntimeException(
				sprintf( 'seed=%d op=%d: node id=%d has %d separators for %d children after a random operation sequence.', $seed, $op, $node->id, count( $node->separators ), count( $node->children ) )
			);
		}
	}
}
