<?php
/**
 * Spike S5 — Strategy F UUID-specific lifecycle cases.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Oracle/IdGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/OracleNode.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/OracleTree.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/Builders.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFContract.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidInjector.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyF.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFEvaluator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFUuidSync.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Strategy\StrategyF;
use AIMultilingual\Spike\S5\Strategy\StrategyFContract;
use AIMultilingual\Spike\S5\Strategy\StrategyFEvaluator;
use AIMultilingual\Spike\S5\Strategy\UuidBlockWalker;
use AIMultilingual\Spike\S5\Strategy\UuidGenerator;
use AIMultilingual\Spike\S5\Strategy\UuidInjector;

final class StrategyFUuidCasesTest extends \WP_UnitTestCase {

	private static array $all_results = array();

	public static function tearDownAfterClass(): void {
		file_put_contents(
			__DIR__ . '/../corpus/strategy-f-uuid-cases.json',
			wp_json_encode( self::$all_results, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	public function test_document_with_no_uuids(): void {
		$ids  = new IdGenerator();
		$tree = new OracleTree( array( Builders::paragraph( $ids, 'No UUID yet.' ) ) );
		$prep = StrategyF::prepare( $tree->to_content() );
		self::$all_results['no_uuids'] = array(
			'generated'        => $prep['inject_stats']['uuids_generated'],
			'content_changed'  => $prep['inject_stats']['content_changed'],
			'segment_count'    => count( $prep['segments'] ),
		);
		$this->assertSame( 1, $prep['inject_stats']['uuids_generated'] );
		$this->assertTrue( $prep['inject_stats']['content_changed'] );
	}

	public function test_second_injection_pass_idempotent(): void {
		$ids     = new IdGenerator();
		$content = ( new OracleTree( array( Builders::paragraph( $ids, 'Stable.' ) ) ) )->to_content();
		$first   = UuidInjector::inject( $content );
		$second  = UuidInjector::inject( $first['content'] );
		self::$all_results['second_pass'] = array(
			'first_changed'  => $first['stats']['content_changed'],
			'second_changed' => $second['stats']['content_changed'],
			'preserved'      => $second['stats']['uuids_preserved'],
		);
		$this->assertTrue( $first['stats']['content_changed'] );
		$this->assertFalse( $second['stats']['content_changed'] );
	}

	public function test_duplicate_uuid_on_siblings_repaired(): void {
		$uuid    = UuidGenerator::v4();
		$content = '<!-- wp:paragraph {"aimlBlockId":"' . $uuid . '"} --><p>A.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph {"aimlBlockId":"' . $uuid . '"} --><p>B.</p><!-- /wp:paragraph -->';
		$result  = UuidInjector::inject( $content );
		$counts  = UuidBlockWalker::count_uuids( $result['content'] );
		self::$all_results['duplicate_siblings'] = array(
			'regenerated' => $result['stats']['uuids_regenerated'],
			'duplicate_after' => $counts,
		);
		$this->assertSame( 1, $result['stats']['uuids_regenerated'] );
		$this->assertSame( array(), $result['duplicate_uuids'] );
		$this->assertCount( 2, $counts );
		foreach ( $counts as $count ) {
			$this->assertSame( 1, $count );
		}
	}

	public function test_malformed_uuid_replaced(): void {
		$content = '<!-- wp:paragraph {"aimlBlockId":"not-a-uuid"} --><p>Bad.</p><!-- /wp:paragraph -->';
		$result  = UuidInjector::inject( $content );
		$uuid    = UuidBlockWalker::walk_eligible( $result['content'] )[0]['uuid'];
		self::$all_results['malformed'] = array(
			'malformed_replaced' => $result['stats']['malformed_replaced'],
			'valid_after'          => StrategyFContract::is_valid_uuid( $uuid ),
		);
		$this->assertSame( 1, $result['stats']['malformed_replaced'] );
		$this->assertTrue( StrategyFContract::is_valid_uuid( $uuid ) );
	}

	public function test_delete_then_insert_identical_text_zero_fp(): void {
		$ids  = new IdGenerator();
		$old  = Builders::paragraph( $ids, 'Call 555-0100.' );
		$tree = new OracleTree( array( $old ) );
		$result = StrategyFEvaluator::evaluate( $tree, static function ( OracleTree $t ) use ( $old, $ids ) {
			$t->delete( $old->id );
			$t->insert( null, 0, Builders::paragraph( $ids, 'Call 555-0100.' ) );
		} );
		self::$all_results['delete_insert_identical'] = $result['metrics'];
		$this->assertSame( 0, $result['metrics']['rendered_false_positive'] );
	}
}
