<?php
/**
 * Spike S5 — Strategy D evaluated against the required operation matrix.
 *
 * Identity: b:<structural_path>:<block_name>:content (Strategy C).
 * Reconciliation: exact source_hash rematch via StrategyDReconciler.
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
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyDEvaluator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyDReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyD.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyD;
use AIMultilingual\Spike\S5\Strategy\StrategyDEvaluator;
use AIMultilingual\Spike\S5\Strategy\StrategyDReconciler;

final class StrategyDOperationsTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-d-results.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	/** @param array<string, mixed> $extra */
	private function record( string $case, array $result, array $extra = array() ): void {
		self::$all_results[ $case ] = array_merge(
			$result['metrics'],
			array(
				'eligible_before'      => $result['eligible_before'],
				'eligible_after'       => $result['eligible_after'],
				'segment_keys_before'  => $result['segment_keys_before'],
				'segment_keys_after'   => $result['segment_keys_after'],
				'key_collision_before' => $result['key_collision_before'],
				'key_collision_after'  => $result['key_collision_after'],
				'rematch_map'          => $result['rematch_map'] ?? array(),
			),
			$extra
		);
	}

	private function evaluate( OracleTree $tree, callable $op ): array {
		$before_content  = $tree->to_content();
		$eligible_before = count( RealBlockWalker::walk_eligible( $before_content ) );
		$keys_before     = count( StrategyD::extract( $before_content ) );

		$result = StrategyDEvaluator::evaluate( $tree, $op, array( StrategyD::class, 'extract' ) );

		$after_content  = $result['after_content'];
		$eligible_after = count( RealBlockWalker::walk_eligible( $after_content ) );
		$keys_after     = count( StrategyD::extract( $after_content ) );

		$result['eligible_before']      = $eligible_before;
		$result['eligible_after']       = $eligible_after;
		$result['segment_keys_before']  = $keys_before;
		$result['segment_keys_after']   = $keys_after;
		$result['key_collision_before'] = $keys_before < $eligible_before;
		$result['key_collision_after']  = $keys_after < $eligible_after;

		return $result;
	}

	// -- Text edits: key unchanged → safely stale (hypothesis). --

	public function test_minor_text_edit(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'The quick brown fox.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->edit_text( $a->id, 'The quick brown fox jumps.' );
		} );

		$this->record( 'minor_text_edit', $result );
		$this->assertSame( 0, $result['metrics']['false_positive'] );
		$this->assertSame( 1, $result['metrics']['stale_correct'] );
	}

	public function test_full_rewrite(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Original content.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->edit_text( $a->id, 'Completely different.' );
		} );

		$this->record( 'full_rewrite', $result );
		$this->assertSame( 1, $result['metrics']['stale_correct'] );
	}

	public function test_type_conversion_in_place(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Becomes heading.' );
		$tree = new OracleTree( array( $a ) );

		$before_keys = array_keys( StrategyD::extract( $tree->to_content() ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->convert_type( $a->id, 'core/heading', array( 'level' => 2 ) );
		} );

		$after_keys = array_keys( StrategyD::extract( $result['after_content'] ) );

		$this->record( 'type_conversion_in_place', $result, array(
			'before_keys' => $before_keys,
			'after_keys'  => $after_keys,
			'observed'    => 'block_name in key changes → orphaned old + spurious_new despite same path',
		) );
		$this->assertNotSame( $before_keys, $after_keys );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
	}

	public function test_split_text_block(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'First half. Second half.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a, $ids ) {
			$t->edit_text( $a->id, 'First half.' );
			$t->insert( null, 1, Builders::paragraph( $ids, 'Second half.' ) );
		} );

		$this->record( 'split_text_block', $result, array(
			'observed' => 'path-0 block stale_correct; new block at path 1 is spurious_new',
		) );
		$this->assertSame( 1, $result['metrics']['stale_correct'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
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

		$this->record( 'merge_text_blocks', $result, array(
			'observed' => 'survivor at path 0 stale_correct; deleted path 1 orphaned',
		) );
		$this->assertSame( 1, $result['metrics']['stale_correct'] );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
	}

	// -- Insertions shift trailing paths. --

	public function test_insertion_at_end(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'First.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids ) {
			$t->insert( null, 1, Builders::paragraph( $ids, 'Appended.' ) );
		} );

		$this->record( 'insertion_at_end', $result );
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

		$this->record( 'insertion_before', $result, array(
			'observed' => 'existing block shifts from path 0→1 → false_positive (Strategy A-like)',
		) );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
		$this->assertSame( 1, $result['metrics']['spurious_new'] );
	}

	public function test_insertion_in_middle(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'First.' );
		$b    = Builders::paragraph( $ids, 'Second.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids ) {
			$t->insert( null, 1, Builders::paragraph( $ids, 'Middle.' ) );
		} );

		$this->record( 'insertion_in_middle', $result, array(
			'observed' => 'leading block stable; trailing block path shift → false_positive',
		) );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
	}

	// -- Deletions shift trailing paths. --

	public function test_deletion_current(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Deleted.' );
		$tree = new OracleTree( array( $a ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->delete( $a->id );
		} );

		$this->record( 'deletion_current', $result );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
	}

	public function test_deletion_before_trailing(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Deleted.' );
		$b    = Builders::paragraph( $ids, 'Survives.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->delete( $a->id );
		} );

		$this->record( 'deletion_before_trailing', $result, array(
			'observed' => 'trailing block path 1→0 inherits wrong translation',
		) );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
	}

	public function test_deletion_after_leading(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Survives.' );
		$b    = Builders::paragraph( $ids, 'Deleted.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $b ) {
			$t->delete( $b->id );
		} );

		$this->record( 'deletion_after_leading', $result );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
		$this->assertSame( 1, $result['metrics']['orphaned'] );
	}

	// -- Reorders change paths at root. --

	public function test_reorder_swap(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Alpha.' );
		$b    = Builders::paragraph( $ids, 'Beta.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) {
			$t->reorder( null, 0, 1 );
		} );

		$this->record( 'reorder_swap', $result, array(
			'observed' => 'paths swap → both blocks false_positive (same as Strategy A)',
		) );
		$this->assertSame( 2, $result['metrics']['false_positive'] );
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
		$this->assertSame( 3, $result['metrics']['false_positive'] );
	}

	public function test_undo_after_mutation(): void {
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

		$this->record( 'move_between_containers', $result, array(
			'observed' => 'partial rematch; one incorrect_rematch when paths collide on hash',
		) );
		$this->assertSame( 1, $result['metrics']['successful_rematch'] );
		$this->assertSame( 1, $result['metrics']['incorrect_rematch'] );
	}

	public function test_nested_movement(): void {
		$ids     = new IdGenerator();
		$mover   = Builders::paragraph( $ids, 'Nested move.' );
		$column1 = Builders::column( $ids, array( $mover ) );
		$other   = Builders::paragraph( $ids, 'Other.' );
		$column2 = Builders::column( $ids, array( $other ) );
		$tree    = new OracleTree( array( Builders::columns( $ids, array( $column1, $column2 ) ) ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $mover, $column2 ) {
			$t->move( $mover->id, $column2->id, 1 );
		} );

		$this->record( 'nested_movement', $result );
		$this->assertSame( 1, $result['metrics']['successful_rematch'] );
		$this->assertSame( 1, $result['metrics']['incorrect_rematch'] );
	}

	public function test_wrap_block(): void {
		$ids   = new IdGenerator();
		$inner = Builders::paragraph( $ids, 'Wrapped.' );
		$tree  = new OracleTree( array( $inner ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $ids, $inner ) {
			$group = Builders::group( $ids, array() );
			$t->insert( null, 0, $group );
			$t->move( $inner->id, $group->id, 0 );
		} );

		$this->record( 'wrap_block', $result, array(
			'observed' => 'path 0→0.0 rematched uniquely via exact hash',
		) );
		$this->assertSame( 1, $result['metrics']['successful_rematch'] );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
	}

	public function test_unwrap_block(): void {
		$ids      = new IdGenerator();
		$inner    = Builders::paragraph( $ids, 'Inner.' );
		$group    = Builders::group( $ids, array( $inner ) );
		$group_id = $group->id;
		$tree     = new OracleTree( array( $group ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $inner, $group_id ) {
			$t->move( $inner->id, null, 0 );
			$t->delete( $group_id );
		} );

		$this->record( 'unwrap_block', $result );
		$this->assertSame( 1, $result['metrics']['successful_rematch'] );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
	}

	public function test_change_nesting_depth(): void {
		$ids   = new IdGenerator();
		$leaf  = Builders::paragraph( $ids, 'Deep.' );
		$col   = Builders::column( $ids, array( $leaf ) );
		$tree  = new OracleTree( array( Builders::columns( $ids, array( $col ) ) ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $leaf ) {
			$t->move( $leaf->id, null, 0 );
		} );

		$this->record( 'change_nesting_depth', $result );
		$this->assertSame( 1, $result['metrics']['successful_rematch'] );
		$this->assertSame( 1, $result['metrics']['correct_reattach'] );
	}

	// -- Duplicate / copy — rematch when unique; ambiguity when duplicate hash. --

	public function test_duplication(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Duplicate me.' );
		$b    = Builders::paragraph( $ids, 'Trailing.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $a, $ids ) {
			$t->duplicate( $a->id, $ids );
		} );

		$this->record( 'duplication', $result, array(
			'observed' => 'no key collision but trailing block path shift → false_positive',
		) );
		$this->assertFalse( $result['key_collision_after'] );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
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
		$this->assertSame( 1, $result['metrics']['false_positive'] );
		$this->assertFalse( $result['key_collision_after'] );
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
		$this->assertFalse( $result['key_collision_after'] );
	}

	public function test_copy_paste_from_another_document(): void {
		$shared_ids = new IdGenerator();
		$doc_a_para = Builders::paragraph( $shared_ids, 'From A.' );
		$doc_a      = new OracleTree( array( $doc_a_para ) );

		$first = Builders::paragraph( $shared_ids, 'B first.' );
		$tail  = Builders::paragraph( $shared_ids, 'B tail.' );
		$doc_b = new OracleTree( array( $first, $tail ) );

		$result = $this->evaluate( $doc_b, static function ( OracleTree $t ) use ( $doc_a, $doc_a_para, $shared_ids ) {
			$t->copy_from( $doc_a, $doc_a_para->id, $shared_ids, null, 1 );
		} );

		$this->record( 'copy_paste_from_another_document', $result );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
		$this->assertFalse( $result['key_collision_after'] );
	}

	public function test_identical_text_same_block_type_distinct_paths(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Same words.' );
		$b    = Builders::paragraph( $ids, 'Same words.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ): void {
			// no-op
		} );

		$keys = array_keys( StrategyD::extract( $tree->to_content() ) );

		$this->record( 'identical_text_same_block_type', $result, array(
			'segment_keys' => $keys,
			'observed'     => 'distinct paths → distinct keys despite identical text',
		) );
		$this->assertCount( 2, $keys );
		$this->assertFalse( $result['key_collision_before'] );
	}

	public function test_same_text_different_block_type(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Shared body.' );
		$b    = Builders::heading( $ids, 'Shared body.', 2 );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ): void {
			// no-op
		} );

		$this->record( 'same_text_different_block_type', $result );
		$this->assertSame( 2, $result['segment_keys_before'] );
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

		$this->record( 'identical_sibling_subtrees', $result );
		$this->assertFalse( $result['key_collision_before'] );
		$this->assertSame( 2, $result['segment_keys_before'] );
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
		$this->assertSame( 2, $result['metrics']['false_positive'] );
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
		$this->assertSame( 2, $result['metrics']['false_positive'] );
	}

	public function test_delete_plus_insert_similar_one_save(): void {
		$ids  = new IdGenerator();
		$old  = Builders::paragraph( $ids, 'Call 555-0100.' );
		$tree = new OracleTree( array( $old ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $old, $ids ) {
			$t->delete( $old->id );
			$t->insert( null, 0, Builders::paragraph( $ids, 'Call 555-0199.' ) );
		} );

		$this->record( 'delete_plus_insert_similar_one_save', $result, array(
			'observed' => 'path 0 reused in one save — rendered false_positive',
		) );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
		$this->assertSame( 1, $result['metrics']['rendered_false_positive'] );
	}

	public function test_path_reuse_after_delete_then_insert_two_saves(): void {
		$ids  = new IdGenerator();
		$old  = Builders::paragraph( $ids, 'Original at path zero.' );
		$tree = new OracleTree( array( $old ) );

		$before = StrategyD::extract( $tree->to_content() );
		$key    = array_key_first( $before );
		$rows    = array(
			$key => array(
				'block_name'      => $before[ $key ]['block_name'],
				'source_hash'     => ReconciliationSimulator::source_hash( $before[ $key ]['text'] ),
				'translated_text' => 'TRANS:' . $before[ $key ]['text'],
				'status'          => 'reviewed',
				'is_stale'        => 0,
				'error_code'      => '',
			),
		);

		$tree->delete( $old->id );
		$sync = StrategyDReconciler::sync_source( $rows, StrategyD::extract( $tree->to_content() ) );
		$rows = $sync['rows'];

		$new = Builders::paragraph( $ids, 'Different content at reused path zero.' );
		$tree->insert( null, 0, $new );
		$after_segments = StrategyD::extract( $tree->to_content() );
		$sync           = StrategyDReconciler::sync_source( $rows, $after_segments );
		$rows           = $sync['rows'];

		$reconciled_key = array_key_first( $after_segments );
		$rendered       = null !== ReconciliationSimulator::translated_value( $rows[ $reconciled_key ] ?? array() );

		self::$all_results['path_reuse_delete_then_insert_two_saves'] = array(
			'reused_key'           => $reconciled_key,
			'same_key_as_before'   => $reconciled_key === $key,
			'row_status'           => $rows[ $reconciled_key ]['status'] ?? null,
			'is_stale'             => $rows[ $reconciled_key ]['is_stale'] ?? null,
			'renders'              => $rendered,
			'observed'             => 'path reuse attaches old translation to different block content',
		);

		$this->assertSame( $key, $reconciled_key );
		$this->assertSame( 1, $rows[ $reconciled_key ]['is_stale'] );
		$this->assertFalse( $rendered );
	}

	// -- Neighbours and fixtures. --

	public function test_non_translatable_container_between_eligible(): void {
		$ids   = new IdGenerator();
		$a     = Builders::paragraph( $ids, 'Before.' );
		$inner = Builders::paragraph( $ids, 'Inside group.' );
		$group = Builders::group( $ids, array( $inner ) );
		$b     = Builders::paragraph( $ids, 'After.' );
		$tree  = new OracleTree( array( $a, $group, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $b ) {
			$t->edit_text( $b->id, 'After, edited.' );
		} );

		$this->record( 'non_translatable_container_between_eligible', $result );
		$this->assertSame( 1, $result['metrics']['stale_correct'], 'Edited block at stable path.' );
	}

	public function test_dynamic_block_skipped(): void {
		$content = (string) file_get_contents( __DIR__ . '/../corpus/authored/dynamic-block.html' )
			. '<!-- wp:paragraph --><p>After dynamic.</p><!-- /wp:paragraph -->';

		$segments = StrategyD::extract( $content );

		self::$all_results['dynamic_block_skipped'] = array(
			'segment_count' => count( $segments ),
			'segment_keys'  => array_keys( $segments ),
			'observed'      => 'dynamic block occupies path index 0; paragraph at path 1',
		);

		$this->assertSame( 'b:1:core/paragraph:content', array_key_first( $segments ) );
	}

	public function test_reusable_block_reference_skipped(): void {
		$content = '<!-- wp:paragraph --><p>Before.</p><!-- /wp:paragraph -->'
			. '<!-- wp:block {"ref":42} /-->'
			. '<!-- wp:paragraph --><p>After.</p><!-- /wp:paragraph -->';

		$segments = StrategyD::extract( $content );

		self::$all_results['reusable_block_reference_skipped'] = array(
			'segment_keys' => array_keys( $segments ),
			'observed'       => 'core/block at path 1 skipped; paragraphs at 0 and 2',
		);

		$this->assertSame( array( 'b:0:core/paragraph:content', 'b:2:core/paragraph:content' ), array_keys( $segments ) );
	}

	public function test_synced_pattern_reference(): void {
		$content = (string) file_get_contents( __DIR__ . '/../corpus/authored/synced-pattern.html' );

		$segments = StrategyD::extract( $content );

		self::$all_results['synced_pattern_reference'] = array(
			'segment_count' => count( $segments ),
			'observed'      => 'synced-pattern fixture yields zero eligible segments in this spike',
		);

		$this->assertSame( 0, count( $segments ) );
	}

	public function test_delete_save_restore_save_two_saves(): void {
		$ids  = new IdGenerator();
		$b    = Builders::paragraph( $ids, 'Delete restore.' );
		$tree = new OracleTree( array( $b ) );

		$before  = StrategyD::extract( $tree->to_content() );
		$key     = array_key_first( $before );
		$removed = $tree->delete( $b->id );
		$sync    = StrategyDReconciler::sync_source(
			array(
				$key => array(
					'block_name'      => $before[ $key ]['block_name'],
					'source_hash'     => ReconciliationSimulator::source_hash( $before[ $key ]['text'] ),
					'translated_text' => 'TRANS:' . $before[ $key ]['text'],
					'status'          => 'reviewed',
					'is_stale'        => 0,
					'error_code'      => '',
				),
			),
			StrategyD::extract( $tree->to_content() )
		);
		$rows = $sync['rows'];

		$tree->restore( null, 0, $removed );
		$sync = StrategyDReconciler::sync_source( $rows, StrategyD::extract( $tree->to_content() ) );
		$rows = $sync['rows'];

		self::$all_results['delete_save_restore_save_two_saves'] = array(
			'row_status'         => $rows[ $key ]['status'] ?? null,
			'renders'            => isset( $rows[ $key ] ) && null !== ReconciliationSimulator::translated_value( $rows[ $key ] ),
			'successful_rematch' => $sync['stats']['successful_rematch'],
			'observed'           => 'restore may rematch via exact hash if unique',
		);

		$this->assertGreaterThanOrEqual( 0, $sync['stats']['successful_rematch'] );
	}

	public function test_redo_after_undo(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'A.' );
		$b    = Builders::paragraph( $ids, 'B.' );
		$tree = new OracleTree( array( $a, $b ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) {
			$t->checkpoint();
			$t->reorder( null, 0, 1 );
			$t->undo();
			$t->redo();
		} );

		$this->record( 'redo_after_undo', $result );
		$this->assertSame( 2, $result['metrics']['false_positive'] );
	}

	public function test_delete_then_insert_identical_text(): void {
		$ids  = new IdGenerator();
		$old  = Builders::paragraph( $ids, 'Call 555-0100.' );
		$tree = new OracleTree( array( $old ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $old, $ids ) {
			$t->delete( $old->id );
			$t->insert( null, 0, Builders::paragraph( $ids, 'Call 555-0100.' ) );
		} );

		$this->record( 'delete_then_insert_identical_text', $result, array(
			'observed' => 'same key reused; hash matches → false continuity on different logical block',
		) );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
	}

	public function test_delete_then_insert_different_text(): void {
		$ids  = new IdGenerator();
		$old  = Builders::paragraph( $ids, 'Original.' );
		$tree = new OracleTree( array( $old ) );

		$result = $this->evaluate( $tree, static function ( OracleTree $t ) use ( $old, $ids ) {
			$t->delete( $old->id );
			$t->insert( null, 0, Builders::paragraph( $ids, 'Replacement.' ) );
		} );

		$this->record( 'delete_then_insert_different_text', $result );
		$this->assertSame( 1, $result['metrics']['false_positive'] );
		$this->assertSame( 0, $result['metrics']['successful_rematch'] );
	}
}
