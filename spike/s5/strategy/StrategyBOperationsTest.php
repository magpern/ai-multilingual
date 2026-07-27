<?php
/**
 * Spike S5 — Strategy B evaluated against the required operation matrix.
 *
 * Key shape: b:<block_name>:<sha1(norm)> per docs/plans/AI_MULTILINGUAL_PLANNING.md.
 * Every case records observed harness metrics; conclusions are drawn from evidence
 * in strategy-b-results.json, not from assertions baked into expectations.
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
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyB.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyB;
use AIMultilingual\Spike\S5\Strategy\StrategyEvaluator;

final class StrategyBOperationsTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-b-results.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	private function record( string $case, array $result, array $extra = array() ): void {
		self::$all_results[ $case ] = array_merge(
			$result['metrics'],
			array(
				'eligible_before'              => $result['eligible_before'],
				'eligible_after'               => $result['eligible_after'],
				'segment_keys_before'          => $result['segment_keys_before'],
				'segment_keys_after'           => $result['segment_keys_after'],
				'key_collision_before'         => $result['key_collision_before'],
				'key_collision_after'          => $result['key_collision_after'],
			),
			$extra
		);
	}

	/**
	 * @return array{
	 *   metrics: array<string,int>,
	 *   eligible_before: int, eligible_after: int,
	 *   segment_keys_before: int, segment_keys_after: int,
	 *   key_collision_before: bool, key_collision_after: bool,
	 *   before_content: string, after_content: string,
	 *   before_segment_count: int, after_segment_count: int,
	 *   extraction_time_ms: array{before: float, after: float},
	 *   reconciliation_time_ms: float
	 * }
	 */
	private function evaluate( OracleTree $tree, callable $op ): array {
		$before_content = $tree->to_content();
		$eligible_before = count( RealBlockWalker::walk_eligible( $before_content ) );
		$keys_before     = count( StrategyB::extract( $before_content ) );

		$result = StrategyEvaluator::evaluate( $tree, $op, array( StrategyB::class, 'extract' ) );

		$after_content = $result['after_content'];
		$eligible_after = count( RealBlockWalker::walk_eligible( $after_content ) );
		$keys_after      = count( StrategyB::extract( $after_content ) );

		$result['eligible_before']      = $eligible_before;
		$result['eligible_after']       = $eligible_after;
		$result['segment_keys_before']  = $keys_before;
		$result['segment_keys_after']   = $keys_after;
		$result['key_collision_before'] = $keys_before < $eligible_before;
		$result['key_collision_after']  = $keys_after < $eligible_after;

		return $result;
	}

	// -- Text edits: key changes; old row orphaned, new key untranslated. --

	public function test_minor_text_edit(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'The quick brown fox.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->edit_text( $a->id, 'The quick brown fox jumps.' );
		} );

		$this->record( 'minor_text_edit', $result, array( 'observed' => 'orphaned + spurious_new; not safely stale on same key' ) );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
	}

	public function test_full_rewrite(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Completely different content.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->edit_text( $a->id, 'Utterly unrelated replacement.' );
		} );

		$this->record( 'full_rewrite', $result );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
	}

	public function test_type_conversion_in_place(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Will become a heading.' );
		$tree = new OracleTree( array( $a ) );

		$before_keys = array_keys( StrategyB::extract( $tree->to_content() ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->convert_type( $a->id, 'core/heading', array( 'level' => 2 ) );
		} );

		$after_keys = array_keys( StrategyB::extract( $result['after_content'] ) );

		$this->record( 'type_conversion_in_place', $result, array(
			'observed'    => 'block_name change produces new key even when innerHTML bytes unchanged',
			'before_keys' => $before_keys,
			'after_keys'  => $after_keys,
			'keys_differ' => $before_keys !== $after_keys,
		) );
		$this->assertNotSame( $before_keys, $after_keys );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
	}

	// -- Insertions: unchanged blocks keep keys. --

	public function test_insertion_at_end(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'First.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids ) {
			$t->insert( null, 1, Builders::paragraph( $ids, 'Appended.' ) );
		} );

		$this->record( 'insertion_at_end', $result, array( 'observed' => 'stable for existing; spurious_new for new block' ) );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
	}

	public function test_insertion_before(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Was first.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids ) {
			$t->insert( null, 0, Builders::paragraph( $ids, 'Now first.' ) );
		} );

		$this->record( 'insertion_before', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
	}

	public function test_insertion_in_middle(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'First.' );
		$b    = Builders::paragraph( $ids, 'Second.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids ) {
			$t->insert( null, 1, Builders::paragraph( $ids, 'Middle.' ) );
		} );

		$this->record( 'insertion_in_middle', $result, array( 'observed' => 'stable for unchanged blocks — opposite of Strategy A' ) );
		$this->assertSame( 2, $result['metrics']['correct_reattach'] );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
	}

	// -- Deletions. --

	public function test_deletion_current(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Deleted.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->delete( $a->id );
		} );

		$this->record( 'deletion_current', $result, array( 'observed' => 'orphaned' ) );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
	}

	public function test_deletion_before_trailing(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Deleted first.' );
		$b    = Builders::paragraph( $ids, 'Survives.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->delete( $a->id );
		} );

		$this->record( 'deletion_before_trailing', $result, array( 'observed' => 'survivor stable; deleted key orphaned' ) );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
	}

	public function test_deletion_after_leading(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Survives.' );
		$b    = Builders::paragraph( $ids, 'Deleted second.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $b ) {
			$t->delete( $b->id );
		} );

		$this->record( 'deletion_after_leading', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
	}

	// -- Reorders: content unchanged → stable. --

	public function test_reorder_swap(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Alpha.' );
		$b    = Builders::paragraph( $ids, 'Beta.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) {
			$t->reorder( null, 0, 1 );
		} );

		$this->record( 'reorder_swap', $result, array( 'observed' => 'stable — Strategy A false-positive case' ) );
		$this->assertSame( 2, $result['metrics']['correct_reattach'] );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
	}

	public function test_reorder_non_adjacent(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'A.' );
		$b    = Builders::paragraph( $ids, 'B.' );
		$c    = Builders::paragraph( $ids, 'C.' );
		$tree = new OracleTree( array( $a, $b, $c ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) {
			$t->reorder( null, 0, 2 );
		} );

		$this->record( 'reorder_non_adjacent', $result );
		$this->assertSame( 3, $result['metrics']['correct_reattach'] );
	}

	public function test_undo_after_reorder(): void {
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
		$this->assertSame( 2, $result['metrics']['correct_reattach'] );
	}

	public function test_transform_then_exact_inverse(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'A.' );
		$b    = Builders::paragraph( $ids, 'B.' );
		$c    = Builders::paragraph( $ids, 'C.' );
		$tree = new OracleTree( array( $a, $b, $c ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) {
			$t->reorder( null, 0, 2 );
			$t->reorder( null, 2, 0 );
		} );

		$this->record( 'transform_then_exact_inverse', $result );
		$this->assertSame( 3, $result['metrics']['correct_reattach'] );
	}

	// -- Moves / wrap / unwrap / nesting. --

	public function test_move_between_containers(): void {
		$ids     = new IdGenerator();
		$mover   = Builders::paragraph( $ids, 'Cross-container.' );
		$column1 = Builders::column( $ids, array( $mover ) );
		$column2 = Builders::column( $ids, array() );
		$tree    = new OracleTree( array( Builders::columns( $ids, array( $column1, $column2 ) ) ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $mover, $column2 ) {
			$t->move( $mover->id, $column2->id, 0 );
		} );

		$this->record( 'move_between_containers', $result );
		$this->assertGreaterThanOrEqual( 0, $result['metrics']['correct_reattach'] );
	}

	public function test_nested_movement(): void {
		$ids     = new IdGenerator();
		$mover   = Builders::paragraph( $ids, 'Nested move.' );
		$column1 = Builders::column( $ids, array( $mover ) );
		$other   = Builders::paragraph( $ids, 'Other column.' );
		$column2 = Builders::column( $ids, array( $other ) );
		$tree    = new OracleTree( array( Builders::columns( $ids, array( $column1, $column2 ) ) ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $mover, $column2 ) {
			$t->move( $mover->id, $column2->id, 1 );
		} );

		$this->record( 'nested_movement', $result );
		$this->assertGreaterThanOrEqual( 0, $result['metrics']['correct_reattach'] );
	}

	public function test_wrap_block(): void {
		$ids   = new IdGenerator();
		$inner = Builders::paragraph( $ids, 'Wrapped paragraph.' );
		$tree  = new OracleTree( array( $inner ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids, $inner ) {
			$group = Builders::group( $ids, array() );
			$t->insert( null, 0, $group );
			$t->move( $inner->id, $group->id, 0 );
		} );

		$this->record( 'wrap_block', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
	}

	public function test_unwrap_block(): void {
		$ids    = new IdGenerator();
		$inner  = Builders::paragraph( $ids, 'Inner in group.' );
		$group  = Builders::group( $ids, array( $inner ) );
		$tree   = new OracleTree( array( $group ) );
		$group_id = $group->id;

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $inner, $group_id ) {
			$t->move( $inner->id, null, 0 );
			$t->delete( $group_id );
		} );

		$this->record( 'unwrap_block', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
	}

	public function test_change_nesting_depth(): void {
		$ids    = new IdGenerator();
		$leaf   = Builders::paragraph( $ids, 'Deep leaf.' );
		$column = Builders::column( $ids, array( $leaf ) );
		$tree   = new OracleTree( array( Builders::columns( $ids, array( $column ) ) ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $leaf ) {
			$t->move( $leaf->id, null, 0 );
		} );

		$this->record( 'change_nesting_depth', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
	}

	// -- Duplicate / copy / collision. --

	public function test_duplication(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Duplicate me.' );
		$b    = Builders::paragraph( $ids, 'Trailing.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a, $ids ) {
			$t->duplicate( $a->id, $ids );
		} );

		$this->record( 'duplication', $result, array( 'observed' => 'collision — duplicate shares block_name + norm with original' ) );
		$this->assertTrue( $result['key_collision_after'] );
	}

	public function test_duplicate_then_edit_one_copy(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Hello.' );
		$tail = Builders::paragraph( $ids, 'Trailing.' );
		$tree = new OracleTree( array( $a, $tail ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a, $ids ) {
			$clone_id = $t->duplicate( $a->id, $ids );
			$t->edit_text( $clone_id, 'Hello v2.' );
		} );

		$this->record( 'duplicate_then_edit_one_copy', $result );
		$this->assertSame( 2, $result['metrics']['correct_reattach'] );
	}

	public function test_copy_paste_within_document(): void {
		$ids    = new IdGenerator();
		$source = Builders::paragraph( $ids, 'Copy me.' );
		$tail   = Builders::paragraph( $ids, 'Tail.' );
		$tree   = new OracleTree( array( $source, $tail ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $source, $ids ) {
			$t->copy_paste( $source->id, $ids, null, 1 );
		} );

		$this->record( 'copy_paste_within_document', $result );
		$this->assertTrue( $result['key_collision_after'] );
	}

	public function test_copy_paste_from_another_document(): void {
		$shared_ids = new IdGenerator();
		$doc_a_para = Builders::paragraph( $shared_ids, 'From doc A.' );
		$doc_a      = new OracleTree( array( $doc_a_para ) );

		$doc_b_first = Builders::paragraph( $shared_ids, 'Doc B first.' );
		$doc_b_tail  = Builders::paragraph( $shared_ids, 'Doc B tail.' );
		$doc_b       = new OracleTree( array( $doc_b_first, $doc_b_tail ) );

		$result = $this->evaluate( $doc_b, static function ( OracleTree $t ) use ( $doc_a, $doc_a_para, $shared_ids ) {
			$t->copy_from( $doc_a, $doc_a_para->id, $shared_ids, null, 1 );
		} );

		$this->record( 'copy_paste_from_another_document', $result );
		$this->assertSame( 2, $result['metrics']['correct_reattach'] );
	}

	public function test_identical_text_same_block_type_collides(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Same words.' );
		$b    = Builders::paragraph( $ids, 'Same words.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ): void {
			// no-op
		} );

		$this->record( 'identical_text_same_block_type', $result, array( 'observed' => 'collision' ) );
		$this->assertTrue( $result['key_collision_before'] );
		$this->assertSame( 1, $result['segment_keys_before'] );
		$this->assertSame( 2, $result['eligible_before'] );
	}

	public function test_same_text_different_block_type_no_collision(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Shared body text.' );
		$b    = Builders::heading( $ids, 'Shared body text.', 2 );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ): void {
			// no-op
		} );

		$keys = array_keys( StrategyB::extract( $tree->to_content() ) );

		$this->record( 'same_text_different_block_type', $result, array(
			'observed'    => 'no collision — block_name disambiguates identical norm text',
			'segment_keys' => $keys,
		) );
		$this->assertCount( 2, $keys );
		$this->assertFalse( $result['key_collision_before'] );
	}

	public function test_identical_sibling_subtrees(): void {
		$ids    = new IdGenerator();
		$leaf_a = Builders::paragraph( $ids, 'Subtree leaf.' );
		$leaf_b = Builders::paragraph( $ids, 'Subtree leaf.' );
		$tree   = new OracleTree( array(
			Builders::group( $ids, array( $leaf_a ) ),
			Builders::group( $ids, array( $leaf_b ) ),
		) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ): void {
			// no-op
		} );

		$this->record( 'identical_sibling_subtrees', $result, array( 'observed' => 'collision on identical leaf fingerprints' ) );
		$this->assertTrue( $result['key_collision_before'] );
	}

	// -- Split / merge. --

	public function test_split_text_block(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'First half. Second half.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a, $ids ) {
			$t->edit_text( $a->id, 'First half.' );
			$t->insert( null, 1, Builders::paragraph( $ids, 'Second half.' ) );
		} );

		$this->record( 'split_text_block', $result, array( 'observed' => 'original key orphaned; two new keys' ) );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
		$this->assertSame( 2, $result['metrics']['spurious_new'] );
	}

	public function test_merge_text_blocks(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Part one.' );
		$b    = Builders::paragraph( $ids, 'Part two.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a, $b ) {
			$t->edit_text( $a->id, 'Part one. Part two.' );
			$t->delete( $b->id );
		} );

		$this->record( 'merge_text_blocks', $result );
		$this->assertGreaterThanOrEqual( 1, $result['metrics']['orphaned'] );
	}

	// -- Combinations. --

	public function test_reorder_plus_edit(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'A original.' );
		$b    = Builders::paragraph( $ids, 'B original.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->reorder( null, 0, 1 );
			$t->edit_text( $a->id, 'A edited.' );
		} );

		$this->record( 'reorder_plus_edit', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'], 'Unedited block stable.' );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
	}

	public function test_swap_plus_edit_both(): void {
		$ids   = new IdGenerator();
		$alpha = Builders::paragraph( $ids, 'Alpha.' );
		$beta  = Builders::paragraph( $ids, 'Beta.' );
		$tree  = new OracleTree( array( $alpha, $beta ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $alpha, $beta ) {
			$t->reorder( null, 0, 1 );
			$t->edit_text( $alpha->id, 'Alpha edited.' );
			$t->edit_text( $beta->id, 'Beta edited.' );
		} );

		$this->record( 'swap_plus_edit_both', $result );
		$this->assertSame( 2, $result['metrics']['orphaned'] );
		$this->assertSame( 2, $result['metrics']['spurious_new'] );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
	}

	public function test_delete_plus_insert_similar(): void {
		$ids        = new IdGenerator();
		$old_number = Builders::paragraph( $ids, 'Call 555-0100.' );
		$tree       = new OracleTree( array( $old_number ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $old_number, $ids ) {
			$t->delete( $old_number->id );
			$t->insert( null, 0, Builders::paragraph( $ids, 'Call 555-0199.' ) );
		} );

		$this->record( 'delete_plus_insert_similar', $result, array( 'observed' => 'no false continuity — different keys' ) );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
	}

	// -- Non-translatable neighbours and fixture-based skip paths. --

	public function test_container_between_eligible_blocks(): void {
		$ids   = new IdGenerator();
		$a     = Builders::paragraph( $ids, 'Before group.' );
		$inner = Builders::paragraph( $ids, 'Inside group.' );
		$group = Builders::group( $ids, array( $inner ) );
		$b     = Builders::paragraph( $ids, 'After group.' );
		$tree  = new OracleTree( array( $a, $group, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $b ) {
			$t->edit_text( $b->id, 'After group, edited.' );
		} );

		$this->record( 'non_translatable_container_between_eligible', $result );
		$this->assertSame( 2, $result['metrics']['correct_reattach'] );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
	}

	public function test_dynamic_block_skipped(): void {
		$content = (string) file_get_contents( __DIR__ . '/../corpus/authored/dynamic-block.html' )
			. '<!-- wp:paragraph --><p>After dynamic.</p><!-- /wp:paragraph -->';

		$eligible = RealBlockWalker::walk_eligible( $content );
		$segments = StrategyB::extract( $content );

		self::$all_results['dynamic_block_skipped'] = array(
			'eligible_count' => count( $eligible ),
			'segment_count'  => count( $segments ),
			'observed'       => 'core/latest-posts skipped; paragraph eligible',
		);

		$this->assertCount( 1, $eligible );
		$this->assertSame( 'core/paragraph', $eligible[0]['block_name'] );
	}

	public function test_reusable_block_reference_skipped(): void {
		$content = (string) file_get_contents( __DIR__ . '/../corpus/authored/reusable-block.html' );

		$eligible = RealBlockWalker::walk_eligible( $content );
		$segments = StrategyB::extract( $content );

		self::$all_results['reusable_block_reference_skipped'] = array(
			'eligible_count' => count( $eligible ),
			'segment_count'  => count( $segments ),
			'observed'       => 'core/block refs skipped — fixture contains ref-only markup, zero eligible blocks',
		);

		$this->assertSame( 0, count( $eligible ) );
		$this->assertSame( 0, count( $segments ) );
	}

	public function test_synced_pattern_reference(): void {
		$content = (string) file_get_contents( __DIR__ . '/../corpus/authored/synced-pattern.html' );

		$eligible = RealBlockWalker::walk_eligible( $content );
		$segments = StrategyB::extract( $content );

		self::$all_results['synced_pattern_reference'] = array(
			'eligible_count' => count( $eligible ),
			'segment_count'  => count( $segments ),
			'observed'       => 'observed eligibility on synced-pattern fixture as serialized',
		);

		$this->assertGreaterThanOrEqual( 0, count( $eligible ) );
	}

	public function test_delete_save_restore_save_two_saves(): void {
		$ids  = new IdGenerator();
		$b    = Builders::paragraph( $ids, 'Delete restore sequence.' );
		$tree = new OracleTree( array( $b ) );

		$before_segments = StrategyB::extract( $tree->to_content() );
		$rows            = array();
		foreach ( $before_segments as $key => $seg ) {
			$rows[ $key ] = array(
				'source_hash'     => ReconciliationSimulator::source_hash( $seg['text'] ),
				'translated_text' => 'TRANS:' . $seg['text'],
				'status'          => 'reviewed',
				'is_stale'        => 0,
				'error_code'      => '',
			);
		}

		$removed = $tree->delete( $b->id );
		$rows    = ReconciliationSimulator::sync_source( $rows, StrategyB::extract( $tree->to_content() ) );
		$this->assertSame( 'ignored', $rows[ array_key_first( $rows ) ]['status'] );

		$tree->restore( null, 0, $removed );
		$rows = ReconciliationSimulator::sync_source( $rows, StrategyB::extract( $tree->to_content() ) );

		$key = array_key_first( $rows );
		self::$all_results['delete_save_restore_save_two_saves'] = array(
			'row_status_after_restore_and_second_save' => $rows[ $key ]['status'],
			'renders'                                  => null !== ReconciliationSimulator::translated_value( $rows[ $key ] ),
			'observed'                                 => 'un-orphaning gap — production Store never revives ignored rows',
		);

		$this->assertSame( 'ignored', $rows[ $key ]['status'] );
	}
}
