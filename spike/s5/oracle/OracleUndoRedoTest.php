<?php
/**
 * Spike S5, ground-truth oracle: undo/redo, an operation followed by its
 * exact inverse, and the delete -> save -> restore -> save sequence that
 * models what the production Store::sync_source() "un-orphaning" fix must
 * get right — the restored block's id must be identical to what it was
 * before deletion, not a fresh one.
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

final class OracleUndoRedoTest extends \WP_UnitTestCase {

	public function test_undo_restores_the_exact_prior_state(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$tree = new OracleTree( array( $a, $b ) );

		$before_content = $tree->to_content();
		$before_paths   = $tree->snapshot_paths();

		$tree->checkpoint();
		$tree->reorder( null, 0, 1 );

		$this->assertNotSame( $before_content, $tree->to_content(), 'Precondition: the operation must actually have changed something.' );

		$tree->undo();

		$this->assertSame( $before_content, $tree->to_content() );
		$this->assertSame( $before_paths, $tree->snapshot_paths() );
	}

	public function test_redo_reapplies_what_undo_reverted(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$tree = new OracleTree( array( $a, $b ) );

		$tree->checkpoint();
		$tree->reorder( null, 0, 1 );
		$after_reorder_content = $tree->to_content();

		$tree->undo();
		$this->assertNotSame( $after_reorder_content, $tree->to_content() );

		$tree->redo();
		$this->assertSame( $after_reorder_content, $tree->to_content() );
	}

	public function test_undo_of_a_deletion_brings_back_the_same_id(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$tree = new OracleTree( array( $a, $b ) );

		$before = $tree->snapshot_paths();

		$tree->checkpoint();
		$tree->delete( $a->id );
		$this->assertArrayNotHasKey( $a->id, $tree->snapshot_paths() );

		$tree->undo();

		$this->assertSame( $before, $tree->snapshot_paths(), 'Undoing a delete must restore the exact same id at the exact same path.' );
	}

	/**
	 * "Transform followed by exact inverse" as its own combination, distinct
	 * from the generic undo() convenience above: this calls the true inverse
	 * OPERATION directly (a second reorder), not the history mechanism.
	 */
	public function test_reorder_followed_by_its_exact_inverse_returns_to_the_original_state(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$c   = Builders::paragraph( $ids, 'C.' );
		$tree = new OracleTree( array( $a, $b, $c ) );

		$before = $tree->snapshot_paths();

		$tree->reorder( null, 0, 2 ); // [A,B,C] -> [B,C,A]
		$this->assertNotSame( $before, $tree->snapshot_paths() );

		$tree->reorder( null, 2, 0 ); // [B,C,A] -> [A,B,C]

		$this->assertSame( $before, $tree->snapshot_paths(), 'Moving an item forward then immediately back by the same amount must restore the original arrangement exactly.' );
	}

	public function test_type_conversion_followed_by_its_exact_inverse_restores_the_original_type(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'Round trip me.' );
		$tree = new OracleTree( array( $a ) );

		$tree->convert_type( $a->id, 'core/heading', array( 'level' => 2 ) );
		$this->assertSame( 'core/heading', $tree->find( $a->id )->block_name );

		$tree->convert_type( $a->id, 'core/paragraph', array() );

		$this->assertSame( 'core/paragraph', $tree->find( $a->id )->block_name );
		$this->assertSame( array(), $tree->find( $a->id )->attrs );
	}

	/**
	 * The sequence the production "un-orphaning" fix (Store::sync_source())
	 * must handle: delete a block, "save" (content is serialized — modelled
	 * here simply as calling to_content(), the same thing a real save would
	 * persist), restore the SAME block, "save" again. The restored block's id
	 * must be identical to its pre-delete id — a lookalike inserted instead of
	 * a genuine restore must NOT receive that id (that is a different,
	 * unsafe, path this test does not exercise).
	 */
	public function test_delete_save_restore_save_preserves_the_original_id(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'Will be deleted and restored.' );
		$b   = Builders::paragraph( $ids, 'Stays put throughout.' );
		$tree = new OracleTree( array( $a, $b ) );

		$before_delete_paths = $tree->snapshot_paths();

		// Delete.
		$removed = $tree->delete( $a->id );
		$this->assertSame( $a->id, $removed->id, 'delete() must return the exact node removed, id intact.' );

		// Save #1: the document now has only B. Confirm the id is truly gone.
		$after_delete_content = $tree->to_content();
		$this->assertStringNotContainsString( 'Will be deleted and restored.', $after_delete_content );
		$this->assertArrayNotHasKey( $a->id, $tree->snapshot_paths() );

		// Restore: the SAME node object (same id), reinserted at its original path.
		$tree->restore( null, 0, $removed );

		// Save #2.
		$after_restore_paths = $tree->snapshot_paths();

		$this->assertSame( $before_delete_paths, $after_restore_paths, 'After restore, every id must be back at its original path.' );
		$this->assertStringContainsString( 'Will be deleted and restored.', $tree->to_content() );
	}
}
