<?php
/**
 * Spike S5 — Strategy A performance: extraction and reconciliation timing at
 * 100/500/1000 blocks, median of 10 runs, per the accepted plan's O(n)
 * falsification test (t(1000)/t(100) <= 15).
 *
 * Uses the automated corpus (spike/s5/corpus/authored/) via
 * ScaleDocumentGenerator, reused unchanged from Phase 1b — see
 * PROVENANCE_NOTICE.md: this is provisional/automated content, not genuine
 * editor output, and nothing here claims otherwise. Performance
 * characteristics (block count, parse cost) are not sensitive to that
 * distinction the way editor-serialization-quirk findings would be.
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
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyA.php';

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\ScaleDocumentGenerator;
use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyA;

final class StrategyAPerformanceTest extends \WP_UnitTestCase {

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

	public function test_extraction_and_reconciliation_scale_linearly(): void {
		$targets = array( 100, 500, 1000 );
		$results = array();

		foreach ( $targets as $target ) {
			$ids      = new IdGenerator();
			$variant  = static fn( string $text, int $cycle ): string => $text;
			$tree     = ScaleDocumentGenerator::generate( $this->load_templates(), $ids, $target, $variant );
			$content  = $tree->to_content();

			$extraction_times     = array();
			$reconciliation_times = array();

			// Seed rows once from the initial extraction, so reconciliation
			// timing measures a REAL sync_source() call against unchanged
			// content (the common case — most saves touch nothing), not an
			// empty-row early return.
			$t0       = hrtime( true );
			$segments = StrategyA::extract( $content );
			$extraction_times[] = ( hrtime( true ) - $t0 ) / 1e6;

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
				$s  = StrategyA::extract( $content );
				$extraction_times[] = ( hrtime( true ) - $t1 ) / 1e6;

				$t2 = hrtime( true );
				ReconciliationSimulator::sync_source( $rows, $s );
				$reconciliation_times[] = ( hrtime( true ) - $t2 ) / 1e6;
			}

			sort( $extraction_times );
			sort( $reconciliation_times );

			$results[ (string) $target ] = array(
				'segment_count'          => count( $segments ),
				'content_bytes'          => strlen( $content ),
				'extraction_median_ms'   => round( self::median( $extraction_times ), 4 ),
				'reconciliation_median_ms' => round( self::median( $reconciliation_times ), 4 ),
			);
		}

		file_put_contents(
			__DIR__ . '/../corpus/strategy-a-performance.json',
			wp_json_encode( $results, JSON_PRETTY_PRINT )
		);

		$extraction_ratio      = $results['1000']['extraction_median_ms'] / max( 0.0001, $results['100']['extraction_median_ms'] );
		$reconciliation_ratio  = $results['1000']['reconciliation_median_ms'] / max( 0.0001, $results['100']['reconciliation_median_ms'] );

		$results['_falsification_test'] = array(
			'extraction_ratio_1000_over_100'     => round( $extraction_ratio, 2 ),
			'reconciliation_ratio_1000_over_100' => round( $reconciliation_ratio, 2 ),
			'threshold'                           => 15,
		);

		file_put_contents(
			__DIR__ . '/../corpus/strategy-a-performance.json',
			wp_json_encode( $results, JSON_PRETTY_PRINT )
		);

		$this->assertLessThanOrEqual( 15, $extraction_ratio, 'O(n) falsification test: extraction must not scale worse than roughly linearly.' );
		$this->assertLessThanOrEqual( 15, $reconciliation_ratio, 'O(n) falsification test: reconciliation must not scale worse than roughly linearly.' );
	}

	private static function median( array $sorted_values ): float {
		$n = count( $sorted_values );
		$mid = intdiv( $n, 2 );

		if ( 0 === $n % 2 ) {
			return ( $sorted_values[ $mid - 1 ] + $sorted_values[ $mid ] ) / 2;
		}

		return $sorted_values[ $mid ];
	}
}
