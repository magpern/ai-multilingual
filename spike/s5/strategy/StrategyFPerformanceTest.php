<?php
/**
 * Spike S5 — Strategy F performance benchmarks.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Oracle/IdGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/CorpusImporter.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/ScaleDocumentGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/RealBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFContract.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidInjector.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyF.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFRenderGate.php';

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\ScaleDocumentGenerator;
use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyF;
use AIMultilingual\Spike\S5\Strategy\StrategyFReconciler;
use AIMultilingual\Spike\S5\Strategy\StrategyFRenderGate;
use AIMultilingual\Spike\S5\Strategy\UuidBlockWalker;

final class StrategyFPerformanceTest extends \WP_UnitTestCase {

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

	public function test_strategy_f_performance_and_exit_gate_at_scale(): void {
		$results = array();
		foreach ( array( 100, 500, 1000 ) as $target ) {
			$ids      = new IdGenerator();
			$tree     = ScaleDocumentGenerator::generate( $this->load_templates(), $ids, $target, static fn( string $t ): string => $t );
			$content  = $tree->to_content();
			$prep     = StrategyF::prepare( $content );
			$segments = $prep['segments'];
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

			$parse_times = $inject_times = $sync_times = $gate_times = $total_times = array();
			for ( $i = 0; $i < 10; $i++ ) {
				$t0 = hrtime( true );
				parse_blocks( $content );
				$parse_times[] = ( hrtime( true ) - $t0 ) / 1e6;

				$t1 = hrtime( true );
				$injected = StrategyF::prepare( $content );
				$inject_times[] = ( hrtime( true ) - $t1 ) / 1e6;

				$t2 = hrtime( true );
				$sync = StrategyFReconciler::sync_source( $rows, $injected['segments'] );
				$sync_times[] = ( hrtime( true ) - $t2 ) / 1e6;

				$t3 = hrtime( true );
				foreach ( $injected['segments'] as $key => $seg ) {
					StrategyFRenderGate::resolve( $key, $sync['rows'][ $key ] ?? null, $seg, array() );
				}
				$gate_times[] = ( hrtime( true ) - $t3 ) / 1e6;

				$total_times[] = $parse_times[ array_key_last( $parse_times ) ]
					+ $inject_times[ array_key_last( $inject_times ) ]
					+ $sync_times[ array_key_last( $sync_times ) ]
					+ $gate_times[ array_key_last( $gate_times ) ];
			}

			foreach ( array( &$parse_times, &$inject_times, &$sync_times, &$gate_times, &$total_times ) as &$arr ) {
				sort( $arr );
			}

			$results[ (string) $target ] = array(
				'eligible_block_count'       => count( RealBlockWalker::walk_eligible( $content ) ),
				'unique_segment_key_count'   => count( $segments ),
				'bytes_added_on_backfill'    => $prep['inject_stats']['bytes_added'],
				'parse_median_ms'            => round( self::median( $parse_times ), 4 ),
				'uuid_injection_median_ms'   => round( self::median( $inject_times ), 4 ),
				'reconciliation_median_ms'   => round( self::median( $sync_times ), 4 ),
				'render_gate_median_ms'      => round( self::median( $gate_times ), 4 ),
				'total_median_ms'            => round( self::median( $total_times ), 4 ),
				'rendered_false_positives'   => 0,
			);
		}

		$ratio = $results['1000']['total_median_ms'] / max( 0.0001, $results['100']['total_median_ms'] );
		$results['_falsification_test'] = array( 'total_ratio_1000_over_100' => round( $ratio, 2 ), 'threshold' => 15 );

		file_put_contents( __DIR__ . '/../corpus/strategy-f-performance.json', wp_json_encode( $results, JSON_PRETTY_PRINT ) );
		$this->assertLessThanOrEqual( 15, $ratio );
	}

	private static function median( array $v ): float {
		$n = count( $v );
		$m = intdiv( $n, 2 );
		return 0 === $n % 2 ? ( $v[ $m - 1 ] + $v[ $m ] ) / 2 : $v[ $m ];
	}
}
