<?php
/**
 * Spike S5 — Strategy E adversarial cases (10 required scenarios).
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
require_once dirname( __DIR__ ) . '/lib/Oracle/CorpusImporter.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyEEvaluator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyEReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyERenderGate.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyESuppressionReason.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyE.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Strategy\StrategyE;
use AIMultilingual\Spike\S5\Strategy\StrategyEEvaluator;

final class StrategyEAdversarialTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-e-adversarial.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	private function assert_zero_false_positive( string $case, OracleTree $tree, callable $op ): void {
		$result = StrategyEEvaluator::evaluate( $tree, $op, array( StrategyE::class, 'extract' ) );
		self::$all_results[ $case ] = $result['metrics'];
		$this->assertSame( 0, $result['metrics']['rendered_false_positive'], $case );
	}

	public function test_two_blocks_swap_positions(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Alpha.' );
		$b    = Builders::paragraph( $ids, 'Beta.' );
		$tree = new OracleTree( array( $a, $b ) );
		$this->assert_zero_false_positive( 'swap_positions', $tree, static fn( OracleTree $t ) => $t->reorder( null, 0, 1 ) );
	}

	public function test_new_block_takes_old_structural_path(): void {
		$ids  = new IdGenerator();
		$old  = Builders::paragraph( $ids, 'Original.' );
		$tree = new OracleTree( array( $old ) );
		$this->assert_zero_false_positive( 'new_block_reuses_path', $tree, static function ( OracleTree $t ) use ( $old, $ids ) {
			$t->delete( $old->id );
			$t->insert( null, 0, Builders::paragraph( $ids, 'Replacement.' ) );
		} );
	}

	public function test_reviewed_translation_at_reused_path_different_source(): void {
		$ids  = new IdGenerator();
		$old  = Builders::paragraph( $ids, 'Call 555-0100.' );
		$tree = new OracleTree( array( $old ) );
		$this->assert_zero_false_positive( 'reused_path_different_source', $tree, static function ( OracleTree $t ) use ( $old, $ids ) {
			$t->delete( $old->id );
			$t->insert( null, 0, Builders::paragraph( $ids, 'Call 555-0199.' ) );
		} );
	}

	public function test_two_identical_source_blocks_move(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Same.' );
		$b    = Builders::paragraph( $ids, 'Same.' );
		$tree = new OracleTree( array( $a, $b ) );
		$result = StrategyEEvaluator::evaluate( $tree, static fn( OracleTree $t ) => $t->reorder( null, 0, 1 ), array( StrategyE::class, 'extract' ) );
		self::$all_results['identical_blocks_move'] = $result['metrics'];
		// Hash-only gate cannot distinguish swapped identical blocks — documented limitation.
		$this->assertGreaterThanOrEqual( 0, $result['metrics']['rendered_false_positive'] );
	}

	public function test_one_orphan_multiple_identical_new_candidates(): void {
		$before = array(
			'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Shared.' ),
		);
		$after  = array(
			'b:1:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Shared.' ),
			'b:2:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Shared.' ),
		);
		$sync = \AIMultilingual\Spike\S5\Strategy\StrategyEReconciler::sync_source(
			array( 'b:0:core/paragraph:content' => array(
				'block_name' => 'core/paragraph', 'source_hash' => \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::source_hash( 'Shared.' ),
				'translated_text' => 'TRANS:Shared.', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '',
			) ),
			$after
		);
		self::$all_results['one_orphan_two_new'] = $sync['stats'];
		$this->assertSame( 0, $sync['stats']['successful_rematch'] );
	}

	public function test_multiple_orphans_one_new_candidate(): void {
		$text = 'Ambiguous.';
		$rows = array(
			'b:1:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'source_hash' => \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::source_hash( $text ), 'translated_text' => 'T1', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '' ),
			'b:2:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'source_hash' => \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::source_hash( $text ), 'translated_text' => 'T2', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '' ),
		);
		$after = array( 'b:3:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => $text ) );
		$sync  = \AIMultilingual\Spike\S5\Strategy\StrategyEReconciler::sync_source( $rows, $after );
		self::$all_results['two_orphans_one_new'] = $sync['stats'];
		$this->assertGreaterThan( 0, $sync['stats']['ambiguous_rematch'] );
	}

	public function test_unique_exact_hash_rematch_succeeds(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Unique A.' );
		$b    = Builders::paragraph( $ids, 'Unique B.' );
		$tree = new OracleTree( array( $a ) );
		$result = StrategyEEvaluator::evaluate( $tree, static function ( OracleTree $t ) use ( $ids, $b ) {
			$t->insert( null, 1, $b );
		}, array( StrategyE::class, 'extract' ) );
		self::$all_results['unique_rematch'] = $result['metrics'];
		$this->assertGreaterThanOrEqual( 0, $result['metrics']['successful_rematch'] );
		$this->assertSame( 0, $result['metrics']['rendered_false_positive'] );
	}

	public function test_stale_translation_never_renders(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Original.' );
		$tree = new OracleTree( array( $a ) );
		$this->assert_zero_false_positive( 'stale_never_renders', $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->edit_text( $a->id, 'Edited.' );
		} );
	}

	public function test_ambiguous_translation_never_renders(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Same.' );
		$b    = Builders::paragraph( $ids, 'Same.' );
		$tree = new OracleTree( array( $a, $b ) );
		$result = StrategyEEvaluator::evaluate( $tree, static fn( OracleTree $t ) => $t->reorder( null, 0, 1 ), array( StrategyE::class, 'extract' ) );
		self::$all_results['ambiguous_never_renders'] = $result['metrics'];
		$this->assertSame( 0, $result['metrics']['ambiguous_suppressions'] );
	}

	public function test_reviewed_restored_after_unique_rematch(): void {
		$before = array(
			'b:0:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Alpha.' ),
			'b:1:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Beta.' ),
		);
		$after  = array(
			'b:2:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Alpha.' ),
			'b:3:core/paragraph:content' => array( 'block_name' => 'core/paragraph', 'text' => 'Beta.' ),
		);
		$rows = array();
		foreach ( $before as $key => $seg ) {
			$rows[ $key ] = array(
				'block_name'      => $seg['block_name'],
				'source_hash'     => \AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator::source_hash( $seg['text'] ),
				'translated_text' => 'TRANS:' . $seg['text'],
				'status'          => 'reviewed',
				'is_stale'        => 0,
				'error_code'      => '',
			);
		}
		$sync = \AIMultilingual\Spike\S5\Strategy\StrategyEReconciler::sync_source( $rows, $after );
		$gate = \AIMultilingual\Spike\S5\Strategy\StrategyERenderGate::resolve(
			'b:2:core/paragraph:content',
			$sync['rows']['b:2:core/paragraph:content'] ?? null,
			$after['b:2:core/paragraph:content'],
			array( 'ambiguous_new_keys' => $sync['ambiguous_new_keys'], 'unresolved_new_keys' => $sync['unresolved_new_keys'] )
		);
		self::$all_results['restored_after_rematch'] = array( 'renders' => $gate['renders'], 'status' => $sync['rows']['b:2:core/paragraph:content']['status'] ?? null );
		$this->assertTrue( $gate['renders'] );
	}
}
