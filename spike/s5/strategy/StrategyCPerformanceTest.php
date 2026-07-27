<?php
/**
 * Spike S5 — Strategy C performance: tree traversal, path generation,
 * key generation, and reconciliation at 100/500/1000 blocks.
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
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyC.php';

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\ScaleDocumentGenerator;
use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StructuralPathWalker;
use AIMultilingual\Spike\S5\Strategy\StrategyC;

final class StrategyCPerformanceTest extends \WP_UnitTestCase {

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

	public function test_traversal_path_key_generation_and_reconciliation_scale_linearly(): void {
		$targets = array( 100, 500, 1000 );
		$results = array();

		foreach ( $targets as $target ) {
			$ids     = new IdGenerator();
			$variant = static fn( string $text, int $cycle ): string => $text;
			$tree    = ScaleDocumentGenerator::generate( $this->load_templates(), $ids, $target, $variant );
			$content = $tree->to_content();

			$eligible_count         = count( RealBlockWalker::walk_eligible( $content ) );
			$tree_traversal_times   = array();
			$path_generation_times  = array();
			$key_generation_times   = array();
			$extraction_times       = array();
			$reconciliation_times   = array();
			$peak_memory            = array();

			$t0       = hrtime( true );
			$segments = StrategyC::extract( $content );
			$extraction_times[] = ( hrtime( true ) - $t0 ) / 1e6;
			$peak_memory[]      = memory_get_peak_usage( true );

			$rows = array();
			foreach ( $segments as $key => $seg ) {
				$rows[ $key ] = array(
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
				$tree_traversal_times[] = ( hrtime( true ) - $t1 ) / 1e6;

				$t2 = hrtime( true );
				self::path_generation_only( $content );
				$path_generation_times[] = ( hrtime( true ) - $t2 ) / 1e6;

				$t3 = hrtime( true );
				self::key_generation_only( $content );
				$key_generation_times[] = ( hrtime( true ) - $t3 ) / 1e6;

				$t4 = hrtime( true );
				$s  = StrategyC::extract( $content );
				$extraction_times[] = ( hrtime( true ) - $t4 ) / 1e6;

				$t5 = hrtime( true );
				ReconciliationSimulator::sync_source( $rows, $s );
				$reconciliation_times[] = ( hrtime( true ) - $t5 ) / 1e6;

				$peak_memory[] = memory_get_peak_usage( true );
			}

			sort( $tree_traversal_times );
			sort( $path_generation_times );
			sort( $key_generation_times );
			sort( $extraction_times );
			sort( $reconciliation_times );
			sort( $peak_memory );

			$results[ (string) $target ] = array(
				'eligible_block_count'         => $eligible_count,
				'unique_segment_key_count'     => count( $segments ),
				'key_collision_count'          => $eligible_count - count( $segments ),
				'content_bytes'                => strlen( $content ),
				'tree_traversal_median_ms'     => round( self::median( $tree_traversal_times ), 4 ),
				'path_generation_median_ms'    => round( self::median( $path_generation_times ), 4 ),
				'key_generation_median_ms'     => round( self::median( $key_generation_times ), 4 ),
				'extraction_median_ms'         => round( self::median( $extraction_times ), 4 ),
				'reconciliation_median_ms'     => round( self::median( $reconciliation_times ), 4 ),
				'peak_memory_median_bytes'     => (int) self::median( $peak_memory ),
			);
		}

		$tree_ratio           = $results['1000']['tree_traversal_median_ms'] / max( 0.0001, $results['100']['tree_traversal_median_ms'] );
		$path_ratio           = $results['1000']['path_generation_median_ms'] / max( 0.0001, $results['100']['path_generation_median_ms'] );
		$key_gen_ratio        = $results['1000']['key_generation_median_ms'] / max( 0.0001, $results['100']['key_generation_median_ms'] );
		$extraction_ratio     = $results['1000']['extraction_median_ms'] / max( 0.0001, $results['100']['extraction_median_ms'] );
		$reconciliation_ratio = $results['1000']['reconciliation_median_ms'] / max( 0.0001, $results['100']['reconciliation_median_ms'] );

		$results['_falsification_test'] = array(
			'tree_traversal_ratio_1000_over_100'   => round( $tree_ratio, 2 ),
			'path_generation_ratio_1000_over_100'  => round( $path_ratio, 2 ),
			'key_generation_ratio_1000_over_100'   => round( $key_gen_ratio, 2 ),
			'extraction_ratio_1000_over_100'       => round( $extraction_ratio, 2 ),
			'reconciliation_ratio_1000_over_100'   => round( $reconciliation_ratio, 2 ),
			'threshold'                              => 15,
		);

		file_put_contents(
			__DIR__ . '/../corpus/strategy-c-performance.json',
			wp_json_encode( $results, JSON_PRETTY_PRINT )
		);

		$this->assertLessThanOrEqual( 15, $tree_ratio );
		$this->assertLessThanOrEqual( 15, $path_ratio );
		$this->assertLessThanOrEqual( 15, $key_gen_ratio );
		$this->assertLessThanOrEqual( 15, $extraction_ratio );
		$this->assertLessThanOrEqual( 15, $reconciliation_ratio );
	}

	/** Path generation: eligible walk (filter + path already assigned). */
	private static function path_generation_only( string $content ): int {
		return count( RealBlockWalker::walk_eligible( $content ) );
	}

	/** Key generation in isolation: walk eligible + segment_key. */
	private static function key_generation_only( string $content ): int {
		$count = 0;
		foreach ( RealBlockWalker::walk_eligible( $content ) as $block ) {
			StrategyC::segment_key( $block['path'], $block['block_name'] );
			++$count;
		}

		return $count;
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
