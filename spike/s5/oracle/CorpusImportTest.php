<?php
/**
 * Spike S5, Phase 1b: imports every fixture in spike/s5/corpus/authored/
 * through CorpusImporter and records, per fixture, whether it fits the
 * accepted Phase 1a OracleNode container model or exposes the
 * inter-child-separator shape that model cannot represent.
 *
 * This is diagnostic/import-tooling evidence, not Strategy A-G evaluation:
 * no reconciliation, matching, or identity heuristic is exercised here.
 *
 * PROVENANCE: spike/s5/corpus/authored/PROVENANCE_NOTICE.md applies to every
 * fixture loaded here. This corpus was generated via WP-CLI, not the real
 * Gutenberg browser editor — see that notice before drawing any conclusion
 * about editor behaviour from these results.
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

use AIMultilingual\Spike\S5\Oracle\CorpusImporter;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;
use AIMultilingual\Spike\S5\Oracle\UnsupportedContainerShapeException;

final class CorpusImportTest extends \WP_UnitTestCase {

	private const CORPUS_DIR = __DIR__ . '/../corpus/authored';

	/**
	 * Confirmed fitting: leaves, or containers with at most one child (no
	 * between-child separator possible with 0 or 1 children).
	 */
	private const EXPECTED_FITTING = array(
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
		'no-op-save-after',
	);

	/**
	 * Confirmed NOT fitting: containers with 2+ children carrying a
	 * Gutenberg-conventional separator chunk between them.
	 */
	private const EXPECTED_UNSUPPORTED = array(
		'buttons',              // core/buttons has 2 core/button children with a "\n\n" separator.
		'nested-group-columns', // the inner core/columns has 2 core/column children with a "\n\n" separator.
		'list-nested',          // both the outer list (3 items) and its nested sub-list (2 items) separate siblings this way.
	);

	public function test_every_fixture_imports_or_fails_exactly_as_expected(): void {
		$fitting    = array();
		$unsupported = array();

		foreach ( glob( self::CORPUS_DIR . '/*.html' ) as $file ) {
			$slug    = basename( $file, '.html' );
			$content = (string) file_get_contents( $file );
			$ids     = new IdGenerator();

			try {
				$roots = CorpusImporter::from_content( $content, $ids );
				$tree  = new OracleTree( $roots );

				// A fixture that imports must also round-trip through real
				// parse_blocks() with the same shape the oracle believes it
				// has — otherwise the import silently lost information even
				// though no exception was thrown.
				$result = $tree->verify_round_trip_shape();
				$this->assertTrue( $result['ok'], "$slug imported without error but failed shape verification:\n" . var_export( $result, true ) );

				$fitting[] = $slug;
			} catch ( UnsupportedContainerShapeException $e ) {
				$unsupported[ $slug ] = $e->getMessage();
			}
		}

		sort( $fitting );
		$unsupported_slugs = array_keys( $unsupported );
		sort( $unsupported_slugs );

		$expected_fitting = self::EXPECTED_FITTING;
		sort( $expected_fitting );
		$expected_unsupported = self::EXPECTED_UNSUPPORTED;
		sort( $expected_unsupported );

		$this->assertSame( $expected_fitting, $fitting, "Which fixtures import cleanly must match what was confirmed by direct innerContent inspection.\nUnsupported were: " . wp_json_encode( $unsupported, JSON_PRETTY_PRINT ) );
		$this->assertSame( $expected_unsupported, $unsupported_slugs );

		// The exact reason must name the real mechanism (a separator between
		// two children), not just "something went wrong".
		foreach ( $unsupported as $slug => $message ) {
			$this->assertStringContainsString( 'between its first child', $message, "Fixture $slug's failure reason must be the documented inter-child-separator shape." );
		}
	}

	public function test_a_fitting_single_child_container_imports_correctly(): void {
		$content = (string) file_get_contents( self::CORPUS_DIR . '/quote-with-citation.html' );
		$ids     = new IdGenerator();

		$roots = CorpusImporter::from_content( $content, $ids );
		$tree  = new OracleTree( $roots );

		$this->assertTrue( $tree->verify_round_trip_shape()['ok'] );
		$this->assertSame( 'core/quote', $roots[0]->block_name );
		$this->assertCount( 1, $roots[0]->children );
		$this->assertSame( 'core/paragraph', $roots[0]->children[0]->block_name );
	}

	public function test_a_multi_child_container_throws_with_a_precise_reason(): void {
		$content = (string) file_get_contents( self::CORPUS_DIR . '/buttons.html' );
		$ids     = new IdGenerator();

		try {
			CorpusImporter::from_content( $content, $ids );
			$this->fail( 'Expected UnsupportedContainerShapeException.' );
		} catch ( UnsupportedContainerShapeException $e ) {
			$this->assertStringContainsString( 'core/buttons', $e->getMessage() );
			$this->assertStringContainsString( '\n\n', $e->getMessage() );
		}
	}
}
