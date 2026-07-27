<?php
/**
 * Spike S5 — Strategy A evaluated against the required operation matrix.
 *
 * Strategy A is the baseline the accepted plan expects to fail: it exists to
 * demonstrate the wrong-content failure concretely, so the ADR rejects it on
 * evidence. Every test below states, and then empirically verifies (not
 * assumes), whether Strategy A is safe or unsafe for that specific operation
 * — several results here were not obvious in advance and are precise,
 * evidenced findings in their own right (see the per-test docblocks).
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
require_once dirname( __DIR__ ) . '/lib/Strategy/RealBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyEvaluator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyA.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Strategy\StrategyA;
use AIMultilingual\Spike\S5\Strategy\StrategyEvaluator;

final class StrategyAOperationsTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-a-results.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	private function record( string $case, array $result ): void {
		self::$all_results[ $case ] = $result['metrics'];
	}

	private function evaluate( OracleTree $tree, callable $op ): array {
		return StrategyEvaluator::evaluate( $tree, $op, array( StrategyA::class, 'extract' ) );
	}

	// -- Operations that shift no existing content's flat position: safe. --

	public function test_minor_text_edit_is_safe(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'The quick brown fox.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->edit_text( $a->id, 'The quick brown fox jumps.' );
		} );

		$this->record( 'minor_text_edit', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'], 'A text edit in place must never falsely reattach.' );
		$this->assertSame( 1, $result['metrics']['stale_correct'], 'The edit must be correctly flagged stale on the same logical block.' );
	}

	public function test_full_rewrite_is_safe(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Completely different content will replace this.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->edit_text( $a->id, 'Utterly unrelated replacement text.' );
		} );

		$this->record( 'full_rewrite', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
		$this->assertSame( 1, $result['metrics']['stale_correct'] );
	}

	public function test_type_conversion_in_place_is_invisible_to_position_only_keys(): void {
		// Finding, corrected from an initial wrong prediction: OracleTree's
		// convert_type() (INVARIANTS.md #6) changes only block_name/attrs —
		// it does NOT update the leaf's own prefix/suffix wrapper tags. So a
		// "paragraph converted to heading" here still carries prefix="<p>",
		// suffix="</p>" verbatim; RealBlockWalker's text (=innerHTML) is
		// therefore BYTE-IDENTICAL before and after, and the hash does not
		// change at all — not just "safely stale", genuinely undetected as a
		// change. A real Gutenberg conversion would also change the wrapper
		// tag (<p> -> <h2>), which WOULD change the hash; this is a property
		// of this oracle's convert_type() model, not a claim that real
		// conversions are this invisible. Worth stating precisely either way:
		// safety under Strategy A here is not evidence of safety under a
		// scheme that folds block_name into the key (C onward), which would
		// orphan on this same operation.
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Will become a heading, in place.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->convert_type( $a->id, 'core/heading', array( 'level' => 2 ) );
		} );

		$this->record( 'type_conversion_in_place', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
		$this->assertSame( 1, $result['metrics']['correct_reattach'], "The oracle's convert_type() leaves prefix/suffix untouched, so the leaf's own bytes are unchanged — not even flagged stale." );
	}

	public function test_insertion_at_the_end_is_safe(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'First, stays first.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids ) {
			$t->insert( null, 1, Builders::paragraph( $ids, 'Appended after.' ) );
		} );

		$this->record( 'insertion_at_end', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'], 'The appended block is new, untranslated work — correct, not a defect.' );
	}

	public function test_undo_after_a_mutation_returns_to_a_safe_state(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'A.' );
		$b    = Builders::paragraph( $ids, 'B.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) {
			$t->checkpoint();
			$t->reorder( null, 0, 1 );
			$t->undo();
		} );

		$this->record( 'undo_after_mutation', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'], 'Undo restores the exact original bytes; reconciliation must see no change at all.' );
		$this->assertSame( 2, $result['metrics']['correct_reattach'] );
	}

	public function test_transform_followed_by_its_exact_inverse_is_safe(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'A.' );
		$b    = Builders::paragraph( $ids, 'B.' );
		$c    = Builders::paragraph( $ids, 'C.' );
		$tree = new OracleTree( array( $a, $b, $c ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) {
			$t->reorder( null, 0, 2 ); // [A,B,C] -> [B,C,A]
			$t->reorder( null, 2, 0 ); // -> [A,B,C]
		} );

		$this->record( 'transform_then_exact_inverse', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
		$this->assertSame( 3, $result['metrics']['correct_reattach'] );
	}

	// -- Operations that DO shift existing content's flat position: unsafe. --

	public function test_reorder_produces_false_positives(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Alpha original text.' );
		$b    = Builders::paragraph( $ids, 'Beta original text.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) {
			$t->reorder( null, 0, 1 ); // swap
		} );

		$this->record( 'reorder_swap', $result );
		$this->assertSame( 2, $result['metrics']['false_positive'], 'Swapping two blocks must reattach both translations to the wrong content under a pure positional key.' );
		$this->assertSame( 2, $result['metrics']['rendered_false_positive'], 'Both wrong attachments render, per invariant I7.' );
	}

	public function test_insertion_in_the_middle_shifts_and_misattaches_everything_after_it(): void {
		// Not obvious in advance: even a plain INSERTION — no reorder at all
		// — breaks Strategy A for every block after the insertion point,
		// because their flat index shifts. This is as important a finding as
		// the reorder case and is reported separately.
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'First.' );
		$b    = Builders::paragraph( $ids, 'Second, will be pushed to index 2.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids ) {
			$t->insert( null, 1, Builders::paragraph( $ids, 'Inserted in the middle.' ) );
		} );

		$this->record( 'insertion_in_middle', $result );
		$this->assertSame( 1, $result['metrics']['false_positive'], "Second.\"'s old translation must now be wrongly bound to the newly inserted block's key." );
		$this->assertSame( 1, $result['metrics']['correct_reattach'], '"First." is unaffected — it never shifted.' );
	}

	public function test_deletion_shifts_and_misattaches_everything_after_it(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'To be deleted.' );
		$b    = Builders::paragraph( $ids, 'Will shift into the deleted slot.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->delete( $a->id );
		} );

		$this->record( 'deletion_shifts_trailing_content', $result );
		$this->assertSame( 1, $result['metrics']['false_positive'], "B's content now sits at A's old key and inherits A's translation." );
	}

	public function test_duplication_shifts_and_misattaches_trailing_content(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Will be duplicated.' );
		$b    = Builders::paragraph( $ids, 'Trailing content, will shift.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a, $ids ) {
			$t->duplicate( $a->id, $ids );
		} );

		$this->record( 'duplication_shifts_trailing_content', $result );
		$this->assertSame( 1, $result['metrics']['false_positive'], 'Trailing content shifted into the clone\'s key and inherited its neighbour\'s translation.' );
		$this->assertSame( 1, $result['metrics']['correct_reattach'], 'The original, still at its own original position, is unaffected.' );
	}

	public function test_duplicate_then_edit_one_copy(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Hello.' );
		$tail = Builders::paragraph( $ids, 'Trailing content after the duplicate.' );
		$tree = new OracleTree( array( $a, $tail ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a, $ids ) {
			$clone_id = $t->duplicate( $a->id, $ids );
			$t->edit_text( $clone_id, 'Hello v2.' );
		} );

		$this->record( 'duplicate_then_edit_one_copy', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'], 'The original is unaffected at its own position.' );
		$this->assertSame( 1, $result['metrics']['false_positive'], 'The trailing block, shifted into the (edited) clone\'s slot, inherits the wrong translation.' );
	}

	public function test_nested_movement_that_preserves_relative_order_is_safe(): void {
		// Corrected from an initial wrong prediction: moving `mover` from
		// column1 into column2's FIRST slot does not actually change its
		// position in flat document order — it was already immediately
		// before `other`, and it still is. Strategy A's flat counter is
		// invariant under structural reorganisation as long as the relative
		// document order of leaves is preserved; it is only ORDER changes
		// that break it, not nesting changes per se. This is the precise,
		// narrower claim — see the next test for a movement that DOES change
		// relative order.
		$ids     = new IdGenerator();
		$mover   = Builders::paragraph( $ids, 'Moves from column one to column two, staying first.' );
		$column1 = Builders::column( $ids, array( $mover ) );
		$other   = Builders::paragraph( $ids, 'Already in column two.' );
		$column2 = Builders::column( $ids, array( $other ) );
		$tree    = new OracleTree( array( Builders::columns( $ids, array( $column1, $column2 ) ) ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $mover, $column2 ) {
			$t->move( $mover->id, $column2->id, 0 ); // still ends up immediately before $other.
		} );

		$this->record( 'nested_movement_order_preserved', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'], 'Relative document order of both leaves is unchanged by this move.' );
	}

	public function test_nested_movement_that_changes_relative_order_produces_false_positives(): void {
		$ids     = new IdGenerator();
		$mover   = Builders::paragraph( $ids, 'Moves from column one to column two, ending up second.' );
		$column1 = Builders::column( $ids, array( $mover ) );
		$other   = Builders::paragraph( $ids, 'Already in column two.' );
		$column2 = Builders::column( $ids, array( $other ) );
		$tree    = new OracleTree( array( Builders::columns( $ids, array( $column1, $column2 ) ) ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $mover, $column2 ) {
			$t->move( $mover->id, $column2->id, 1 ); // now AFTER $other — reverses their relative order.
		} );

		$this->record( 'nested_movement_order_changed', $result );
		$this->assertGreaterThan( 0, $result['metrics']['false_positive'], 'Reversing the two leaves\' relative document order must misattach.' );
	}

	public function test_copy_paste_within_document_shifts_and_misattaches_trailing_content(): void {
		$ids    = new IdGenerator();
		$source = Builders::paragraph( $ids, 'Copy me elsewhere.' );
		$tail   = Builders::paragraph( $ids, 'Trailing content.' );
		$tree   = new OracleTree( array( $source, $tail ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $source, $ids ) {
			$t->copy_paste( $source->id, $ids, null, 1 );
		} );

		$this->record( 'copy_paste_within_document', $result );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
	}

	public function test_copy_paste_from_another_document_shifts_and_misattaches_trailing_content(): void {
		$shared_ids = new IdGenerator();
		$doc_a_para = Builders::paragraph( $shared_ids, 'Lives in document A.' );
		$doc_a      = new OracleTree( array( $doc_a_para ) );

		$doc_b_first = Builders::paragraph( $shared_ids, 'First in document B.' );
		$doc_b_tail  = Builders::paragraph( $shared_ids, 'Trailing content in document B.' );
		$doc_b       = new OracleTree( array( $doc_b_first, $doc_b_tail ) );

		$result = $this->evaluate( $doc_b, static function ( OracleTree $t ) use ( $doc_a, $doc_a_para, $shared_ids ) {
			$t->copy_from( $doc_a, $doc_a_para->id, $shared_ids, null, 1 );
		} );

		$this->record( 'copy_paste_from_another_document', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'], 'Document B\'s first block, unaffected, stays correct.' );
		$this->assertSame( 1, $result['metrics']['false_positive'], 'Document B\'s trailing content, shifted by the paste, is misattached.' );
	}

	public function test_reorder_plus_edit_in_the_same_save(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'A original.' );
		$b    = Builders::paragraph( $ids, 'B original.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->reorder( null, 0, 1 );
			$t->edit_text( $a->id, 'A edited.' );
		} );

		$this->record( 'reorder_plus_edit', $result );
		$this->assertGreaterThan( 0, $result['metrics']['false_positive'] );
	}

	public function test_swap_plus_edit_both_is_the_sharpest_false_positive_case(): void {
		$ids   = new IdGenerator();
		$alpha = Builders::paragraph( $ids, 'Alpha copy.' );
		$beta  = Builders::paragraph( $ids, 'Beta copy.' );
		$tree  = new OracleTree( array( $alpha, $beta ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $alpha, $beta ) {
			$t->reorder( null, 0, 1 );
			$t->edit_text( $alpha->id, 'Alpha copy, edited.' );
			$t->edit_text( $beta->id, 'Beta copy, edited.' );
		} );

		$this->record( 'swap_plus_edit_both', $result );
		$this->assertSame( 2, $result['metrics']['false_positive'], 'Both edited-and-swapped blocks must be reported as wrong-content attachments.' );
		$this->assertSame( 2, $result['metrics']['rendered_false_positive'] );
	}

	public function test_delete_plus_insert_similar_content_is_the_sharpest_single_block_case(): void {
		$ids  = new IdGenerator();
		$old_number = Builders::paragraph( $ids, 'Call us on 555-0100.' );
		$tree = new OracleTree( array( $old_number ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $old_number, $ids ) {
			$t->delete( $old_number->id );
			$t->insert( null, 0, Builders::paragraph( $ids, 'Call us on 555-0199 for parts.' ) );
		} );

		$this->record( 'delete_plus_insert_similar', $result );
		$this->assertSame( 1, $result['metrics']['false_positive'], "The old phone number's translation must render under the new, different phone number." );
		$this->assertSame( 1, $result['metrics']['rendered_false_positive'] );
	}

	/**
	 * Confirms, empirically, the already-anticipated "un-orphaning" gap
	 * (identified during Milestone 2 design review, before this spike began):
	 * production Store::sync_source() never revives an ignored/orphaned row
	 * when its key reappears with identical content — ReconciliationSimulator
	 * mirrors that faithfully, so this is not a new discovery, it is that
	 * gap's first concrete, evidenced reproduction.
	 */
	public function test_delete_save_restore_save_leaves_the_translation_orphaned(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Will be deleted and restored.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$removed = $t->delete( $a->id );
			// "save" (reconciliation) happens inside evaluate(), between
			// before/after — restore happens BEFORE that single reconcile
			// call in this harness, so to observe the delete's own orphaning
			// this test evaluates delete alone; a second evaluate() call
			// with the restored tree shows the row stays ignored.
			$t->restore( null, 0, $removed );
		} );

		// Because restore() happens before the harness's single
		// reconciliation point, this evaluates the NET effect of
		// delete-then-restore in one save, which is a no-op content-wise —
		// confirming Strategy A itself is blameless here. The real
		// un-orphaning gap is a property of reconciliation across TWO
		// separate saves, demonstrated directly below instead.
		$this->record( 'delete_restore_net_noop_within_one_save', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'] );

		// Now the real two-save sequence: delete and reconcile (orphaning
		// the row), THEN restore and reconcile again.
		$ids2 = new IdGenerator();
		$b    = Builders::paragraph( $ids2, 'Will be deleted, saved, restored, saved again.' );
		$tree2 = new OracleTree( array( $b ) );

		$before_content = $tree2->to_content();
		$before_segments = StrategyA::extract( $before_content );
		$rows = array();
		foreach ( $before_segments as $key => $seg ) {
			$rows[ $key ] = array(
				'source_hash'     => \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::source_hash( $seg['text'] ),
				'translated_text' => 'TRANS:' . $seg['text'],
				'status'          => 'reviewed',
				'is_stale'        => 0,
				'error_code'      => '',
			);
		}

		$removed = $tree2->delete( $b->id );
		$after_delete_content = $tree2->to_content();
		$after_delete_segments = StrategyA::extract( $after_delete_content );
		$rows = \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::sync_source( $rows, $after_delete_segments );

		$this->assertSame( 'ignored', $rows[ array_key_first( $rows ) ]['status'], 'First save: the row must be orphaned.' );

		$tree2->restore( null, 0, $removed );
		$after_restore_content = $tree2->to_content();
		$this->assertSame( $before_content, $after_restore_content, 'Precondition: restore brings back byte-identical content.' );

		$after_restore_segments = StrategyA::extract( $after_restore_content );
		$rows = \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::sync_source( $rows, $after_restore_segments );

		$key = array_key_first( $rows );
		$this->assertSame( 'ignored', $rows[ $key ]['status'], 'CONFIRMED GAP: the row stays ignored across the second save even though the exact original content is back — production sync_source() never un-orphans.' );
		$this->assertNull( \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::translated_value( $rows[ $key ] ), 'The translation does not render, even though the content is byte-identical to what it was translated for.' );

		self::$all_results['delete_save_restore_save_two_saves'] = array(
			'row_status_after_restore_and_second_save' => $rows[ $key ]['status'],
			'renders'                                    => null !== \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::translated_value( $rows[ $key ] ),
			'finding'                                    => 'Confirms the already-anticipated un-orphaning gap; not a new discovery, its first concrete reproduction.',
		);
	}
}
