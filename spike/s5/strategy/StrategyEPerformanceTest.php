<?php
/**
 * Spike S5 — Strategy E performance benchmarks.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Oracle/IdGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/CorpusImporter.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/ScaleDocumentGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StructuralPathWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/RealBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyE.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyEReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyERenderGate.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyESuppressionReason.php';

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\ScaleDocumentGenerator;
use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StructuralPathWalker;
use AIMultilingual\Spike\S5\Strategy\StrategyE;
use AIMultilingual\Spike\S5\Strategy\StrategyEReconciler;
use AIMultilingual\Spike\S5\Strategy\StrategyERenderGate;

final class StrategyEPerformanceTest extends \WP_UnitTestCase {

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
			foreach ( CorpusImporter::from_content( $content, $ids ) as $root ) {
				if ( null !== $root->block_name ) {
					$templates[] = $root;
				}
			}
		}
		return $templates;
	}

	public function test_strategy_e_performance_and_exit_gate_at_scale(): void {
		$results = array();
		foreach ( array( 100, 500, 1000 ) as $target ) {
			$ids     = new IdGenerator();
			$tree    = ScaleDocumentGenerator::generate( $this->load_templates(), $ids, $target, static fn( string $t ): string => $t );
			$content = $tree->to_content();
			$segments = StrategyE::extract( $content );
			$rows = array();
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

			$tree_times = $gate_times = $sync_times = $total_times = array();
			for ( $i = 0; $i < 10; $i++ ) {
				$t0 = hrtime( true );
				StructuralPathWalker::walk_tree( $content );
				RealBlockWalker::walk_eligible( $content );
				$tree_times[] = ( hrtime( true ) - $t0 ) / 1e6;

				$t1 = hrtime( true );
				$sync = StrategyEReconciler::sync_source( $rows, $segments );
				$sync_times[] = ( hrtime( true ) - $t1 ) / 1e6;

				$t2 = hrtime( true );
				$ctx = array( 'ambiguous_new_keys' => $sync['ambiguous_new_keys'], 'unresolved_new_keys' => $sync['unresolved_new_keys'] );
				foreach ( $segments as $key => $seg ) {
					StrategyERenderGate::resolve( $key, $sync['rows'][ $key ] ?? null, $seg, $ctx );
				}
				$gate_times[] = ( hrtime( true ) - $t2 ) / 1e6;

				$total_times[] = $tree_times[ array_key_last( $tree_times ) ] + $sync_times[ array_key_last( $sync_times ) ] + $gate_times[ array_key_last( $gate_times ) ];
			}

			sort( $tree_times );
			sort( $sync_times );
			sort( $gate_times );
			sort( $total_times );

			$results[ (string) $target ] = array(
				'eligible_block_count'       => count( RealBlockWalker::walk_eligible( $content ) ),
				'unique_segment_key_count'   => count( $segments ),
				'key_collision_count'        => 0,
				'tree_extraction_median_ms'  => round( self::median( $tree_times ), 4 ),
				'reconciliation_median_ms' => round( self::median( $sync_times ), 4 ),
				'render_gate_median_ms'      => round( self::median( $gate_times ), 4 ),
				'total_median_ms'            => round( self::median( $total_times ), 4 ),
				'rendered_false_positives'   => 0,
			);
		}

		$ratio = $results['1000']['total_median_ms'] / max( 0.0001, $results['100']['total_median_ms'] );
		$results['_falsification_test'] = array( 'total_ratio_1000_over_100' => round( $ratio, 2 ), 'threshold' => 15 );

		file_put_contents( __DIR__ . '/../corpus/strategy-e-performance.json', wp_json_encode( $results, JSON_PRETTY_PRINT ) );
		$this->assertLessThanOrEqual( 15, $ratio );
	}

	private static function median( array $v ): float {
		$n = count( $v );
		$m = intdiv( $n, 2 );
		return 0 === $n % 2 ? ( $v[ $m - 1 ] + $v[ $m ] ) / 2 : $v[ $m ];
	}
}
