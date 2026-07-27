<?php
/**
 * Spike S5 — Strategy B performance: extraction, key-generation, and
 * reconciliation timing at 100/500/1000 blocks, median of 10 runs, per the
 * accepted plan's O(n) falsification test (t(1000)/t(100) <= 15).
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
require_once dirname( __DIR__ ) . '/lib/Strategy/RealBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyB.php';

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\ScaleDocumentGenerator;
use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyB;

final class StrategyBPerformanceTest extends \WP_UnitTestCase {

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

	public function test_extraction_key_generation_and_reconciliation_scale_linearly(): void {
		$targets = array( 100, 500, 1000 );
		$results = array();

		foreach ( $targets as $target ) {
			$ids     = new IdGenerator();
			$variant = static fn( string $text, int $cycle ): string => $text;
			$tree    = ScaleDocumentGenerator::generate( $this->load_templates(), $ids, $target, $variant );
			$content = $tree->to_content();

			$eligible_count       = count( RealBlockWalker::walk_eligible( $content ) );
			$extraction_times     = array();
			$key_generation_times = array();
			$reconciliation_times = array();
			$peak_memory          = array();

			$t0       = hrtime( true );
			$segments = StrategyB::extract( $content );
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
				$s  = StrategyB::extract( $content );
				$extraction_times[] = ( hrtime( true ) - $t1 ) / 1e6;

				$t2 = hrtime( true );
				self::key_generation_only( $content );
				$key_generation_times[] = ( hrtime( true ) - $t2 ) / 1e6;

				$t3 = hrtime( true );
				ReconciliationSimulator::sync_source( $rows, $s );
				$reconciliation_times[] = ( hrtime( true ) - $t3 ) / 1e6;

				$peak_memory[] = memory_get_peak_usage( true );
			}

			sort( $extraction_times );
			sort( $key_generation_times );
			sort( $reconciliation_times );
			sort( $peak_memory );

			$results[ (string) $target ] = array(
				'eligible_block_count'       => $eligible_count,
				'unique_segment_key_count'   => count( $segments ),
				'key_collision_count'          => $eligible_count - count( $segments ),
				'content_bytes'              => strlen( $content ),
				'extraction_median_ms'       => round( self::median( $extraction_times ), 4 ),
				'key_generation_median_ms'   => round( self::median( $key_generation_times ), 4 ),
				'reconciliation_median_ms'   => round( self::median( $reconciliation_times ), 4 ),
				'peak_memory_median_bytes'   => (int) self::median( $peak_memory ),
			);
		}

		$extraction_ratio     = $results['1000']['extraction_median_ms'] / max( 0.0001, $results['100']['extraction_median_ms'] );
		$key_gen_ratio        = $results['1000']['key_generation_median_ms'] / max( 0.0001, $results['100']['key_generation_median_ms'] );
		$reconciliation_ratio = $results['1000']['reconciliation_median_ms'] / max( 0.0001, $results['100']['reconciliation_median_ms'] );

		$results['_falsification_test'] = array(
			'extraction_ratio_1000_over_100'       => round( $extraction_ratio, 2 ),
			'key_generation_ratio_1000_over_100' => round( $key_gen_ratio, 2 ),
			'reconciliation_ratio_1000_over_100' => round( $reconciliation_ratio, 2 ),
			'threshold'                            => 15,
		);

		file_put_contents(
			__DIR__ . '/../corpus/strategy-b-performance.json',
			wp_json_encode( $results, JSON_PRETTY_PRINT )
		);

		$this->assertLessThanOrEqual( 15, $extraction_ratio );
		$this->assertLessThanOrEqual( 15, $key_gen_ratio );
		$this->assertLessThanOrEqual( 15, $reconciliation_ratio );
	}

	/**
	 * Key generation in isolation: walk + hash, without building segment array.
	 */
	private static function key_generation_only( string $content ): int {
		$count = 0;
		foreach ( RealBlockWalker::walk_eligible( $content ) as $block ) {
			StrategyB::segment_key( $block['block_name'], $block['text'] );
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
