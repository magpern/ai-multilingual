<?php
/**
 * Spike S5 — Strategy F adversarial cases.
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
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFEvaluator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFRenderGate.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFSuppressionReason.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFContract.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidInjector.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFUuidSync.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyF.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyF;
use AIMultilingual\Spike\S5\Strategy\StrategyFContract;
use AIMultilingual\Spike\S5\Strategy\StrategyFEvaluator;
use AIMultilingual\Spike\S5\Strategy\StrategyFReconciler;
use AIMultilingual\Spike\S5\Strategy\UuidGenerator;

final class StrategyFAdversarialTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-f-adversarial.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	private function assert_zero_false_positive( string $case, OracleTree $tree, callable $op ): void {
		$result = StrategyFEvaluator::evaluate( $tree, $op );
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
		$this->assert_zero_false_positive( 'identical_blocks_move', $tree, static fn( OracleTree $t ) => $t->reorder( null, 0, 1 ) );
	}

	public function test_one_orphan_multiple_identical_new_candidates(): void {
		$uuid_old = UuidGenerator::v4();
		$uuid_a   = UuidGenerator::v4();
		$uuid_b   = UuidGenerator::v4();
		$rows     = array(
			StrategyFContract::segment_key( $uuid_old ) => array(
				'block_name' => 'core/paragraph', 'source_hash' => ReconciliationSimulator::source_hash( 'Shared.' ),
				'translated_text' => 'TRANS:Shared.', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '',
			),
		);
		$after = array(
			StrategyFContract::segment_key( $uuid_a ) => array( 'block_name' => 'core/paragraph', 'text' => 'Shared.', 'uuid' => $uuid_a ),
			StrategyFContract::segment_key( $uuid_b ) => array( 'block_name' => 'core/paragraph', 'text' => 'Shared.', 'uuid' => $uuid_b ),
		);
		$sync = StrategyFReconciler::sync_source( $rows, $after );
		self::$all_results['one_orphan_two_new'] = $sync['stats'];
		$this->assertSame( 1, $sync['stats']['orphaned'] );
	}

	public function test_multiple_orphans_one_new_candidate(): void {
		$text   = 'Ambiguous.';
		$uuid1  = UuidGenerator::v4();
		$uuid2  = UuidGenerator::v4();
		$uuid3  = UuidGenerator::v4();
		$rows   = array(
			StrategyFContract::segment_key( $uuid1 ) => array( 'block_name' => 'core/paragraph', 'source_hash' => ReconciliationSimulator::source_hash( $text ), 'translated_text' => 'T1', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '' ),
			StrategyFContract::segment_key( $uuid2 ) => array( 'block_name' => 'core/paragraph', 'source_hash' => ReconciliationSimulator::source_hash( $text ), 'translated_text' => 'T2', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '' ),
		);
		$after  = array( StrategyFContract::segment_key( $uuid3 ) => array( 'block_name' => 'core/paragraph', 'text' => $text, 'uuid' => $uuid3 ) );
		$sync   = StrategyFReconciler::sync_source( $rows, $after );
		self::$all_results['two_orphans_one_new'] = $sync['stats'];
		$this->assertSame( 2, $sync['stats']['orphaned'] );
	}

	public function test_uuid_preserved_on_insert(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Unique A.' );
		$b    = Builders::paragraph( $ids, 'Unique B.' );
		$tree = new OracleTree( array( $a ) );
		$result = StrategyFEvaluator::evaluate( $tree, static function ( OracleTree $t ) use ( $ids, $b ) {
			$t->insert( null, 1, $b );
		} );
		self::$all_results['uuid_preserved_insert'] = $result['metrics'];
		$this->assertSame( 0, $result['metrics']['rendered_false_positive'] );
		$this->assertGreaterThanOrEqual( 1, $result['metrics']['correctly_rendered'] );
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
		$this->assert_zero_false_positive( 'duplicate_uuid_swap', $tree, static fn( OracleTree $t ) => $t->reorder( null, 0, 1 ) );
	}

	public function test_reviewed_restored_after_uuid_persists(): void {
		$ids  = new IdGenerator();
		$a    = Builders::paragraph( $ids, 'Alpha.' );
		$tree = new OracleTree( array( $a ) );
		$result = StrategyFEvaluator::evaluate( $tree, static function ( OracleTree $t ) use ( $a ) {
			$t->edit_text( $a->id, 'Alpha edited.' );
			$t->edit_text( $a->id, 'Alpha.' );
		} );
		self::$all_results['restored_after_edit_undo'] = array(
			'rendered_fp' => $result['metrics']['rendered_false_positive'],
			'correct'     => $result['metrics']['correctly_rendered'],
		);
		$this->assertSame( 0, $result['metrics']['rendered_false_positive'] );
		$this->assertGreaterThanOrEqual( 1, $result['metrics']['correctly_rendered'] );
	}
}
