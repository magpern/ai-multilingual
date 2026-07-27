<?php
/**
 * Spike S5, ground-truth oracle: the required individual operations, each
 * proven against the explicit old-id -> new-id oracle (snapshot_paths() +
 * diff_snapshots()), not just "the tree looks right by eye".
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

final class OracleBasicTransformsTest extends \WP_UnitTestCase {

	public function test_reorder_preserves_ids_and_moves_paths(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$c   = Builders::paragraph( $ids, 'C.' );
		$tree = new OracleTree( array( $a, $b, $c ) );

		$before = $tree->snapshot_paths();
		$tree->reorder( null, 0, 2 ); // move A to the end: [B, C, A]
		$after = $tree->snapshot_paths();

		$diff = OracleTree::diff_snapshots( $before, $after );

		$this->assertSame( array(), $diff['deleted'] );
		$this->assertSame( array(), $diff['created'] );

		// Moving A from index 0 to index 2 shifts every sibling that sat
		// after it, so B and C also change path even though the operation
		// was "move A" — all three ids are accounted for, none created or
		// destroyed, which is exactly the oracle guarantee under test.
		$this->assertSame( '0', $diff['moved'][ $a->id ]['from'] );
		$this->assertSame( '2', $diff['moved'][ $a->id ]['to'] );
		$this->assertSame( '1', $diff['moved'][ $b->id ]['from'] );
		$this->assertSame( '0', $diff['moved'][ $b->id ]['to'] );
		$this->assertSame( '2', $diff['moved'][ $c->id ]['from'] );
		$this->assertSame( '1', $diff['moved'][ $c->id ]['to'] );
		$this->assertSame( array(), $diff['unchanged'] );

		// Content really did reorder.
		$content = $tree->to_content();
		$this->assertLessThan( strpos( $content, 'A.' ), strpos( $content, 'B.' ) );
	}

	public function test_insertion_creates_a_new_id_and_does_not_disturb_existing_ones(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$tree = new OracleTree( array( $a, $b ) );

		$before = $tree->snapshot_paths();
		$new_node = Builders::paragraph( $ids, 'Inserted.' );
		$tree->insert( null, 1, $new_node );
		$after = $tree->snapshot_paths();

		$diff = OracleTree::diff_snapshots( $before, $after );

		$this->assertSame( array(), $diff['deleted'] );
		$this->assertSame( array( $new_node->id => '1' ), $diff['created'] );
		$this->assertSame( '0', $after[ $a->id ] );
		$this->assertSame( '2', $after[ $b->id ], 'B must have shifted from index 1 to index 2.' );
	}

	public function test_deletion_removes_exactly_one_id(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$tree = new OracleTree( array( $a, $b ) );

		$before = $tree->snapshot_paths();
		$tree->delete( $a->id );
		$after = $tree->snapshot_paths();

		$diff = OracleTree::diff_snapshots( $before, $after );

		$this->assertSame( array( $a->id => '0' ), $diff['deleted'] );
		$this->assertSame( array(), $diff['created'] );
		$this->assertSame( '0', $after[ $b->id ] );
	}

	public function test_duplication_creates_a_fresh_id_distinct_from_the_original(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'Repeat me.' );
		$tree = new OracleTree( array( $a ) );

		$before   = $tree->snapshot_paths();
		$clone_id = $tree->duplicate( $a->id, $ids );
		$after    = $tree->snapshot_paths();

		$this->assertNotSame( $a->id, $clone_id, 'A duplicate must never share its original\'s id.' );

		$diff = OracleTree::diff_snapshots( $before, $after );
		$this->assertSame( array( $clone_id => '1' ), $diff['created'] );
		$this->assertArrayHasKey( $a->id, $diff['unchanged'], 'The original must be untouched, at its original path.' );

		// Both paragraphs render identically, and are still distinguishable
		// only by which oracle id they carry — exactly the ambiguity a real
		// key-matching strategy has to resolve without this oracle's help.
		$content = $tree->to_content();
		$this->assertSame( 2, substr_count( $content, 'Repeat me.' ) );
	}

	public function test_minor_text_edit_preserves_the_id(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'The quick brown fox.' );
		$tree = new OracleTree( array( $a ) );

		$before = $tree->snapshot_paths();
		$tree->edit_text( $a->id, 'The quick brown fox jumps.' );
		$after = $tree->snapshot_paths();

		$this->assertSame( $before, $after, 'A text edit changes no path and creates/deletes no id.' );
		$this->assertSame( 'The quick brown fox jumps.', $tree->find( $a->id )->text );
	}

	public function test_full_rewrite_also_preserves_the_id(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'Completely different content will replace this.' );
		$tree = new OracleTree( array( $a ) );

		$before = $tree->snapshot_paths();
		$tree->edit_text( $a->id, 'Utterly unrelated replacement text, sharing not one word with the original.' );
		$after = $tree->snapshot_paths();

		$this->assertSame( $before, $after, 'Identity survives a full rewrite exactly as it survives a minor edit — that is the whole point of not keying identity on content.' );
	}

	public function test_nested_movement_out_of_a_parent_preserves_the_id(): void {
		$ids   = new IdGenerator();
		$inner = Builders::paragraph( $ids, 'Escapes the group.' );
		$group = Builders::group( $ids, array( $inner ) );
		$other = Builders::paragraph( $ids, 'Sibling.' );
		$tree  = new OracleTree( array( $group, $other ) );

		$before = $tree->snapshot_paths();
		$this->assertSame( '0.0', $before[ $inner->id ] );

		$tree->move( $inner->id, null, 1 ); // out of the group, to top level between group and $other
		$after = $tree->snapshot_paths();

		$diff = OracleTree::diff_snapshots( $before, $after );
		$this->assertArrayHasKey( $inner->id, $diff['moved'] );
		$this->assertSame( '0.0', $diff['moved'][ $inner->id ]['from'] );
		$this->assertSame( '1', $diff['moved'][ $inner->id ]['to'] );
		$this->assertSame( array(), $diff['deleted'] );
		$this->assertSame( array(), $diff['created'] );
	}

	public function test_nested_movement_into_a_parent_preserves_the_id(): void {
		$ids    = new IdGenerator();
		$mover  = Builders::paragraph( $ids, 'Will move into the group.' );
		$group  = Builders::group( $ids, array( Builders::paragraph( $ids, 'Already inside.' ) ) );
		$tree   = new OracleTree( array( $mover, $group ) );

		$before = $tree->snapshot_paths();
		$tree->move( $mover->id, $group->id, 1 );
		$after = $tree->snapshot_paths();

		$diff = OracleTree::diff_snapshots( $before, $after );
		$this->assertArrayHasKey( $mover->id, $diff['moved'] );
		$this->assertSame( '0', $diff['moved'][ $mover->id ]['from'] );
		$this->assertSame( '0.1', $diff['moved'][ $mover->id ]['to'] );
	}

	public function test_nested_movement_across_two_different_parents_preserves_the_id(): void {
		$ids     = new IdGenerator();
		$mover   = Builders::paragraph( $ids, 'Will hop from column one to column two.' );
		$column1 = Builders::column( $ids, array( $mover ) );
		$column2 = Builders::column( $ids, array( Builders::paragraph( $ids, 'Already in column two.' ) ) );
		$tree    = new OracleTree( array( Builders::columns( $ids, array( $column1, $column2 ) ) ) );

		$before = $tree->snapshot_paths();
		$this->assertSame( '0.0.0', $before[ $mover->id ] );

		$tree->move( $mover->id, $column2->id, 0 );
		$after = $tree->snapshot_paths();

		$this->assertSame( '0.1.0', $after[ $mover->id ] );
	}

	public function test_type_conversion_preserves_the_id(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'Will become a heading.' );
		$tree = new OracleTree( array( $a ) );

		$before = $tree->snapshot_paths();
		$tree->convert_type( $a->id, 'core/heading', array( 'level' => 2 ) );
		$after = $tree->snapshot_paths();

		$this->assertSame( $before, $after );

		$content = $tree->to_content();
		$this->assertStringContainsString( '<!-- wp:heading', $content );
		$this->assertStringNotContainsString( '<!-- wp:paragraph', $content );
	}

	public function test_copy_paste_within_the_same_document_creates_a_fresh_id(): void {
		$ids     = new IdGenerator();
		$source  = Builders::paragraph( $ids, 'Copy me elsewhere.' );
		$other   = Builders::paragraph( $ids, 'Unrelated.' );
		$tree    = new OracleTree( array( $source, $other ) );

		$before   = $tree->snapshot_paths();
		$pasted_id = $tree->copy_paste( $source->id, $ids, null, 2 );
		$after    = $tree->snapshot_paths();

		$this->assertNotSame( $source->id, $pasted_id );
		$diff = OracleTree::diff_snapshots( $before, $after );
		$this->assertSame( array( $pasted_id => '2' ), $diff['created'] );
		$this->assertArrayHasKey( $source->id, $diff['unchanged'] );
	}

	public function test_copy_paste_from_a_different_document_uses_a_shared_generator_to_avoid_id_collision(): void {
		$shared_ids = new IdGenerator();

		$doc_a_para = Builders::paragraph( $shared_ids, 'Lives in document A.' );
		$doc_a      = new OracleTree( array( $doc_a_para ) );

		$doc_b_para = Builders::paragraph( $shared_ids, 'Lives in document B.' );
		$doc_b      = new OracleTree( array( $doc_b_para ) );

		$this->assertNotSame( $doc_a_para->id, $doc_b_para->id, 'Precondition: the shared generator must not hand out the same id twice.' );

		$pasted_id = $doc_b->copy_from( $doc_a, $doc_a_para->id, $shared_ids, null, 1 );

		$this->assertNotSame( $doc_a_para->id, $pasted_id );
		$this->assertNotSame( $doc_b_para->id, $pasted_id );

		// The source document is completely unaffected by the paste.
		$this->assertSame( array( $doc_a_para->id => '0' ), $doc_a->snapshot_paths() );

		$after_b = $doc_b->snapshot_paths();
		$this->assertSame( '0', $after_b[ $doc_b_para->id ] );
		$this->assertSame( '1', $after_b[ $pasted_id ] );

		$content_b = $doc_b->to_content();
		$this->assertStringContainsString( 'Lives in document A.', $content_b );
		$this->assertStringContainsString( 'Lives in document B.', $content_b );
	}
}
