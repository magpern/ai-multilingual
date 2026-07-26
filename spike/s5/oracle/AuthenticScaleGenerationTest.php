<?php
/**
 * Spike S5, Phase 1b: generates the 100/500/1000-block documents required by
 * the accepted plan, using ONLY blocks imported from the provisional
 * automated corpus (spike/s5/corpus/authored/) as ScaleDocumentGenerator
 * templates — no synthetic Builders content.
 *
 * PROVENANCE: see spike/s5/corpus/authored/PROVENANCE_NOTICE.md. This corpus
 * is WP-CLI-generated, not real editor output; nothing produced here may be
 * cited as evidence about genuine Gutenberg editor serialization behaviour.
 *
 * Excluded from the template cycle, and why:
 *  - buttons, nested-group-columns, list-nested: CorpusImportTest confirms
 *    these contain a container with 2+ children separated by a Gutenberg-
 *    conventional inter-child chunk, a shape the accepted Phase 1a
 *    OracleNode model cannot represent. Documented as a finding, not routed
 *    around.
 *  - no-op-save-after: byte-identical to no-op-save-source (see
 *    PROVENANCE_NOTICE.md — produced via `wp post update`, not a real
 *    editor re-save), so including both would only duplicate one shape in
 *    the template cycle without adding real variety.
 *  - each fixture's own trailing blockName=null freeform chunk (the "\n\n"
 *    at end of file — see Phase 0's confirmed finding on this): not a
 *    translatable block, excluded from the template list so it isn't
 *    counted as one.
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

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Oracle\ScaleDocumentGenerator;

final class AuthenticScaleGenerationTest extends \WP_UnitTestCase {

	private const CORPUS_DIR = __DIR__ . '/../corpus/authored';

	private const TEMPLATE_FIXTURES = array(
		'headings-and-paragraphs',
		'html-block',
		'image-caption-alt',
		'table',
		'separator-between-paragraphs',
		'reusable-block',
		'synced-pattern',
		'dynamic-block',
		'quote-with-citation',
		'no-op-save-source',
	);

	/**
	 * @return \AIMultilingual\Spike\S5\Oracle\OracleNode[]
	 */
	private function load_templates(): array {
		$templates = array();

		foreach ( self::TEMPLATE_FIXTURES as $slug ) {
			$content = (string) file_get_contents( self::CORPUS_DIR . '/' . $slug . '.html' );
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

	/**
	 * @dataProvider target_sizes
	 */
	public function test_generates_and_verifies_a_scale_document( int $target ): void {
		$templates = $this->load_templates();
		$this->assertNotEmpty( $templates, 'Precondition: at least one authentic template must have imported.' );

		$ids     = new IdGenerator();
		$variant = static fn( string $text, int $cycle ): string => $text; // no text mutation needed for this measurement; distinct ids alone make repetitions distinguishable.

		$mem_before = memory_get_usage( true );
		$t0         = hrtime( true );

		$tree = ScaleDocumentGenerator::generate( $templates, $ids, $target, $variant );

		$generation_ns = hrtime( true ) - $t0;
		$mem_after     = memory_get_usage( true );

		$actual_count = ScaleDocumentGenerator::actual_block_count( $tree );
		$content      = $tree->to_content();

		$t1     = hrtime( true );
		$parsed = parse_blocks( $content );
		$parse_ns = hrtime( true ) - $t1;

		$round_trip = $tree->verify_round_trip_shape();

		$reparsed_leaf_and_container_count = self::count_real_blocks( $parsed );

		$all_ids  = array_keys( $tree->snapshot_paths() );
		$unique_ids = count( array_unique( $all_ids ) );

		$record = array(
			'target_block_count'      => $target,
			'actual_block_count'      => $actual_count,
			'reparsed_block_count'    => $reparsed_leaf_and_container_count,
			'content_bytes'           => strlen( $content ),
			'generation_time_ms'      => round( $generation_ns / 1e6, 3 ),
			'reparse_time_ms'         => round( $parse_ns / 1e6, 3 ),
			'memory_delta_bytes'      => $mem_after - $mem_before,
			'round_trip_shape_ok'     => $round_trip['ok'],
			'total_ids'               => count( $all_ids ),
			'unique_ids'              => $unique_ids,
			'ids_are_unique'          => $unique_ids === count( $all_ids ),
		);

		self::record_result( $target, $record );

		// -- Assertions: this is verification, not just measurement. --

		$this->assertGreaterThanOrEqual( $target, $actual_count, 'Generator must reach at least the target block count.' );
		$this->assertSame( $actual_count, $reparsed_leaf_and_container_count, 'Every node the oracle believes it created must independently reparse as a real block via parse_blocks() — block count preservation.' );
		$this->assertTrue( $round_trip['ok'], "Nesting/shape preservation failed for target=$target." );
		$this->assertTrue( $record['ids_are_unique'], 'Mapping stability: no id may be assigned twice across the whole generated document.' );
		$this->assertCount( count( $tree->roots() ), $parsed, 'Every generated root must be independently reparseable as one top-level block (no cross-root corruption at scale).' );
	}

	public function target_sizes(): array {
		return array(
			'100 blocks'  => array( 100 ),
			'500 blocks'  => array( 500 ),
			'1000 blocks' => array( 1000 ),
		);
	}

	public function test_generation_is_deterministic_across_independent_runs_at_1000_blocks(): void {
		$templates_a = $this->load_templates();
		$templates_b = $this->load_templates();

		$ids_a = new IdGenerator();
		$ids_b = new IdGenerator();

		$variant = static fn( string $text, int $cycle ): string => $text;

		$tree_a = ScaleDocumentGenerator::generate( $templates_a, $ids_a, 1000, $variant );
		$tree_b = ScaleDocumentGenerator::generate( $templates_b, $ids_b, 1000, $variant );

		$this->assertSame(
			$tree_a->to_content(),
			$tree_b->to_content(),
			'Two independent runs against the same authentic templates must produce byte-identical documents.'
		);
	}

	private static function count_real_blocks( array $blocks ): int {
		$total = 0;

		foreach ( $blocks as $b ) {
			if ( null === $b['blockName'] ) {
				continue; // freeform/whitespace, not a block the oracle created.
			}

			++$total;

			if ( ! empty( $b['innerBlocks'] ) ) {
				$total += self::count_real_blocks( $b['innerBlocks'] );
			}
		}

		return $total;
	}

	/**
	 * Persists results to a JSON file so exact numbers are inspectable
	 * outside the test run output, not just eyeballed from console text.
	 */
	private static function record_result( int $target, array $record ): void {
		$path  = dirname( __DIR__ ) . '/corpus/authentic-scale-results.json';
		$all   = file_exists( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array();
		$all[ (string) $target ] = $record;
		ksort( $all );
		file_put_contents( $path, wp_json_encode( $all, JSON_PRETTY_PRINT ) );
	}
}
