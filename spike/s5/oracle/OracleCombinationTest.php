<?php
/**
 * Spike S5, ground-truth oracle: the adversarial COMBINATIONS — the cases
 * that break naive reconciliation strategies precisely because two things
 * happen in the same save, not one. Each test proves what the oracle says
 * really happened, which is exactly what a Phase 1c strategy's guess will
 * later be checked against (strategy evaluation itself is out of scope here).
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
require_once dirname( __DIR__ ) . '/lib/Oracle/Builders.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;

final class OracleCombinationTest extends \WP_UnitTestCase {

	public function test_reorder_plus_edit_in_the_same_save(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A original.' );
		$b   = Builders::paragraph( $ids, 'B original.' );
		$tree = new OracleTree( array( $a, $b ) );

		$before = $tree->snapshot_paths();

		$tree->reorder( null, 0, 1 ); // [A,B] -> [B,A]
		$tree->edit_text( $a->id, 'A edited.' );

		$after = $tree->snapshot_paths();
		$diff  = OracleTree::diff_snapshots( $before, $after );

		// The oracle's ground truth: A is still A (same id), it moved AND its
		// text changed. No id was created or destroyed. A real strategy that
		// keys on content would see A's old text vanish and new text appear
		// at a new position with no exact-hash match available — the correct,
		// safe behaviour for it is to orphan the old translation, not guess.
		// This test documents what "correct" means; it does not grade a
		// strategy against it.
		$this->assertSame( array(), $diff['created'] );
		$this->assertSame( array(), $diff['deleted'] );
		$this->assertArrayHasKey( $a->id, $diff['moved'] );
		$this->assertSame( '0', $diff['moved'][ $a->id ]['from'] );
		$this->assertSame( '1', $diff['moved'][ $a->id ]['to'] );
		$this->assertSame( 'A edited.', $tree->find( $a->id )->text );
	}

	public function test_swap_plus_edit_both(): void {
		// The design-review finding this directly documents: a swap where
		// BOTH sides are also edited is exactly the case where a
		// positional/patience-style fallback and a similarity-maximising
		// assignment can disagree. The oracle records the one true answer.
		$ids = new IdGenerator();
		$alpha = Builders::paragraph( $ids, 'Alpha copy.' );
		$beta  = Builders::paragraph( $ids, 'Beta copy.' );
		$tree  = new OracleTree( array( $alpha, $beta ) );

		$before = $tree->snapshot_paths();

		$tree->reorder( null, 0, 1 ); // swap: [Alpha,Beta] -> [Beta,Alpha]
		$tree->edit_text( $alpha->id, 'Alpha copy, edited.' );
		$tree->edit_text( $beta->id, 'Beta copy, edited.' );

		$after = $tree->snapshot_paths();
		$diff  = OracleTree::diff_snapshots( $before, $after );

		$this->assertSame( array(), $diff['created'] );
		$this->assertSame( array(), $diff['deleted'] );
		$this->assertSame( '0', $diff['moved'][ $alpha->id ]['from'] );
		$this->assertSame( '1', $diff['moved'][ $alpha->id ]['to'] );
		$this->assertSame( '1', $diff['moved'][ $beta->id ]['from'] );
		$this->assertSame( '0', $diff['moved'][ $beta->id ]['to'] );
		$this->assertSame( 'Alpha copy, edited.', $tree->find( $alpha->id )->text );
		$this->assertSame( 'Beta copy, edited.', $tree->find( $beta->id )->text );
	}

	public function test_duplicate_plus_edit_one_copy(): void {
		$ids = new IdGenerator();
		$original = Builders::paragraph( $ids, 'Hello.' );
		$tree = new OracleTree( array( $original ) );

		$before = $tree->snapshot_paths();
		$clone_id = $tree->duplicate( $original->id, $ids );
		$tree->edit_text( $clone_id, 'Hello v2.' );
		$after = $tree->snapshot_paths();

		$diff = OracleTree::diff_snapshots( $before, $after );

		// Ground truth: exactly one new id (the clone), the original
		// completely untouched at its original path with its original text.
		// This is the case ADR-0013's confidence model must get right: after
		// an exact-hash bind of the original, the clone is a genuinely new
		// segment, not something to be matched against anything.
		$this->assertSame( array( $clone_id => '1' ), $diff['created'] );
		$this->assertSame( array(), $diff['deleted'] );
		$this->assertArrayHasKey( $original->id, $diff['unchanged'] );
		$this->assertSame( 'Hello.', $tree->find( $original->id )->text );
		$this->assertSame( 'Hello v2.', $tree->find( $clone_id )->text );
	}

	public function test_delete_plus_insert_similar_content_in_the_same_save(): void {
		// The sharpest adversarial case from the design review: deleting one
		// block and inserting a DIFFERENT but similar-looking one in the same
		// save is indistinguishable, by content alone, from an edit. The
		// oracle is unambiguous: this is a deletion and a creation, two
		// distinct ids, never one id continuing under new content — that is
		// exactly why a similarity-based reconciliation tier is dangerous
		// (F1 in the design review) unless it never renders unconfirmed.
		$ids = new IdGenerator();
		$old_number = Builders::paragraph( $ids, 'Call us on 555-0100.' );
		$tree = new OracleTree( array( $old_number ) );

		$before = $tree->snapshot_paths();

		$tree->delete( $old_number->id );
		$new_number = Builders::paragraph( $ids, 'Call us on 555-0199 for parts.' );
		$tree->insert( null, 0, $new_number );

		$after = $tree->snapshot_paths();
		$diff  = OracleTree::diff_snapshots( $before, $after );

		$this->assertSame( array( $old_number->id => '0' ), $diff['deleted'] );
		$this->assertSame( array( $new_number->id => '0' ), $diff['created'] );
		$this->assertNotSame(
			$old_number->id,
			$new_number->id,
			'These must never be treated as the same logical block, however similar the text looks.'
		);
	}
}
