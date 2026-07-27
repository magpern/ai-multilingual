<?php
/**
 * Spike S5 — Strategy D performance at 100/500/1000 blocks.
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
require_once dirname( __DIR__ ) . '/lib/Oracle/CorpusImporter.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/ScaleDocumentGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StructuralPathWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/RealBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyD.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyDReconciler.php';

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\ScaleDocumentGenerator;
use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StructuralPathWalker;
use AIMultilingual\Spike\S5\Strategy\StrategyD;
use AIMultilingual\Spike\S5\Strategy\StrategyDReconciler;

final class StrategyDPerformanceTest extends \WP_UnitTestCase {

	private const CORPUS_DIR = __DIR__ . '/../corpus/authored';

	private const TEMPLATE_FIXTURES = array(
		'buttons', 'dynamic-block', 'headings-and-paragraphs', 'html-block',
		'image-caption-alt', 'list-nested', 'nested-group-columns',
		'no-op-save-source', 'quote-with-citation', 'reusable-block',
		'separator-between-paragraphs', 'synced-pattern', 'table',
	);

	private function load_templates(): array {
		$templates = array();

		foreach ( self::TEMPLATE_FIXTURES as $slug ) {
			$content = (string) file_get_contents( self::CORPUS_DIR . "/$slug.html" );
			$ids     = new IdGenerator();
			$roots   = CorpusImporter::from_content( $content, $ids );

			foreach ( $roots as $root ) {
				if ( null !== $root->block_name ) {
					$templates[] = $root;
				}
			}
		}

		return $templates;
	}

	public function test_traversal_rematch_and_sync_scale_linearly(): void {
		$targets = array( 100, 500, 1000 );
		$results = array();

		foreach ( $targets as $target ) {
			$ids     = new IdGenerator();
			$variant = static fn( string $text, int $cycle ): string => $text;
			$tree    = ScaleDocumentGenerator::generate( $this->load_templates(), $ids, $target, $variant );
			$content = $tree->to_content();

			$eligible_count       = count( RealBlockWalker::walk_eligible( $content ) );
			$tree_times           = array();
			$path_times           = array();
			$rematch_times        = array();
			$sync_times           = array();
			$peak_memory          = array();

			$segments = StrategyD::extract( $content );
			$rows     = array();
			foreach ( $segments as $key => $seg ) {
				$rows[ $key ] = array(
					'block_name'      => $seg['block_name'],
					'source_hash'     => ReconciliationSimulator::source_hash( $seg['text'] ),
					'translated_text' => 'TRANS:' . $seg['text'],
					'status'          => 'reviewed',
					'is_stale'        => 0,
					'error_code'      => '',
				);
			}

			for ( $run = 0; $run < 10; $run++ ) {
				$t1 = hrtime( true );
				StructuralPathWalker::walk_tree( $content );
				$tree_times[] = ( hrtime( true ) - $t1 ) / 1e6;

				$t2 = hrtime( true );
				count( RealBlockWalker::walk_eligible( $content ) );
				$path_times[] = ( hrtime( true ) - $t2 ) / 1e6;

				$t3 = hrtime( true );
				StrategyDReconciler::sync_source( $rows, $segments );
				$rematch_times[] = ( hrtime( true ) - $t3 ) / 1e6;

				$t4 = hrtime( true );
				StrategyD::extract( $content );
				StrategyDReconciler::sync_source( $rows, $segments );
				$sync_times[] = ( hrtime( true ) - $t4 ) / 1e6;

				$peak_memory[] = memory_get_peak_usage( true );
			}

			sort( $tree_times );
			sort( $path_times );
			sort( $rematch_times );
			sort( $sync_times );
			sort( $peak_memory );

			$results[ (string) $target ] = array(
				'eligible_block_count'       => $eligible_count,
				'unique_segment_key_count'   => count( $segments ),
				'key_collision_count'        => $eligible_count - count( $segments ),
				'content_bytes'              => strlen( $content ),
				'tree_traversal_median_ms'   => round( self::median( $tree_times ), 4 ),
				'path_generation_median_ms'  => round( self::median( $path_times ), 4 ),
				'rematch_median_ms'          => round( self::median( $rematch_times ), 4 ),
				'total_sync_median_ms'       => round( self::median( $sync_times ), 4 ),
				'peak_memory_median_bytes'   => (int) self::median( $peak_memory ),
			);
		}

		$tree_ratio    = $results['1000']['tree_traversal_median_ms'] / max( 0.0001, $results['100']['tree_traversal_median_ms'] );
		$path_ratio    = $results['1000']['path_generation_median_ms'] / max( 0.0001, $results['100']['path_generation_median_ms'] );
		$rematch_ratio = $results['1000']['rematch_median_ms'] / max( 0.0001, $results['100']['rematch_median_ms'] );
		$sync_ratio    = $results['1000']['total_sync_median_ms'] / max( 0.0001, $results['100']['total_sync_median_ms'] );

		$results['_falsification_test'] = array(
			'tree_traversal_ratio_1000_over_100' => round( $tree_ratio, 2 ),
			'path_generation_ratio_1000_over_100' => round( $path_ratio, 2 ),
			'rematch_ratio_1000_over_100'         => round( $rematch_ratio, 2 ),
			'total_sync_ratio_1000_over_100'      => round( $sync_ratio, 2 ),
			'threshold'                           => 15,
		);

		file_put_contents(
			__DIR__ . '/../corpus/strategy-d-performance.json',
			wp_json_encode( $results, JSON_PRETTY_PRINT )
		);

		$this->assertLessThanOrEqual( 15, $tree_ratio );
		$this->assertLessThanOrEqual( 15, $path_ratio );
		$this->assertLessThanOrEqual( 15, $rematch_ratio );
		$this->assertLessThanOrEqual( 15, $sync_ratio );
	}

	private static function median( array $sorted_values ): float {
		$n   = count( $sorted_values );
		$mid = intdiv( $n, 2 );

		if ( 0 === $n % 2 ) {
			return ( $sorted_values[ $mid - 1 ] + $sorted_values[ $mid ] ) / 2;
		}

		return $sorted_values[ $mid ];
	}
}
