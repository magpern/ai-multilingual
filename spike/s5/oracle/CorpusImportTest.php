<?php
/**
 * Spike S5, Phase 1b: imports every fixture in spike/s5/corpus/authored/
 * through CorpusImporter and confirms each round-trips through real
 * parse_blocks() with the exact shape the oracle believes it has, and — for
 * the three multi-child containers the ORIGINAL container model could not
 * represent — that the inter-child separator content is preserved byte-exact.
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

final class CorpusImportTest extends \WP_UnitTestCase {

	private const CORPUS_DIR = __DIR__ . '/../corpus/authored';

	private const ALL_FIXTURES = array(
		'buttons',
		'dynamic-block',
		'headings-and-paragraphs',
		'html-block',
		'image-caption-alt',
		'list-nested',
		'nested-group-columns',
		'no-op-save-after',
		'no-op-save-source',
		'quote-with-citation',
		'reusable-block',
		'separator-between-paragraphs',
		'synced-pattern',
		'table',
	);

	/**
	 * Every one of the 13 fixtures now imports cleanly. Before the container
	 * model amendment, buttons/nested-group-columns/list-nested threw
	 * UnsupportedContainerShapeException because they contain a multi-child
	 * container with a Gutenberg-conventional separator between siblings.
	 */
	public function test_all_thirteen_fixtures_import_and_round_trip_cleanly(): void {
		$imported = array();

		foreach ( glob( self::CORPUS_DIR . '/*.html' ) as $file ) {
			$slug    = basename( $file, '.html' );
			$content = (string) file_get_contents( $file );
			$ids     = new IdGenerator();

			$roots = CorpusImporter::from_content( $content, $ids );
			$tree  = new OracleTree( $roots );

			$result = $tree->verify_round_trip_shape();
			$this->assertTrue( $result['ok'], "$slug failed shape verification:\n" . var_export( $result, true ) );

			// The stronger claim this amendment exists to prove: not just
			// "the shape matches", but the exact bytes reconstruct.
			$this->assertSame( $content, $tree->to_content(), "$slug must reconstruct byte-identically." );

			$imported[] = $slug;
		}

		sort( $imported );
		$expected = self::ALL_FIXTURES;
		sort( $expected );

		$this->assertSame( $expected, $imported, 'All 13 fixtures must import; none excluded for shape reasons after the model amendment.' );
	}

	public function test_a_single_child_container_still_imports_correctly(): void {
		$content = (string) file_get_contents( self::CORPUS_DIR . '/quote-with-citation.html' );
		$ids     = new IdGenerator();

		$roots = CorpusImporter::from_content( $content, $ids );
		$tree  = new OracleTree( $roots );

		$this->assertTrue( $tree->verify_round_trip_shape()['ok'] );
		$this->assertSame( $content, $tree->to_content() );
		$this->assertSame( 'core/quote', $roots[0]->block_name );
		$this->assertCount( 1, $roots[0]->children );
		$this->assertSame( 'core/paragraph', $roots[0]->children[0]->block_name );
	}

	/**
	 * The fixture that used to be the cleanest failure case: core/buttons has
	 * 2 core/button children separated by "\n\n". Now imports with that exact
	 * separator preserved as a distinct, byte-exact separators[] entry.
	 */
	public function test_buttons_imports_with_its_inter_child_separator_preserved(): void {
		$content = (string) file_get_contents( self::CORPUS_DIR . '/buttons.html' );
		$ids     = new IdGenerator();

		$roots = CorpusImporter::from_content( $content, $ids );
		$buttons = $roots[0];

		$this->assertSame( 'core/buttons', $buttons->block_name );
		$this->assertCount( 2, $buttons->children );
		$this->assertCount( 3, $buttons->separators, 'count(children)+1.' );
		$this->assertSame( "\n\n", $buttons->separators[1], 'The separator BETWEEN the two buttons must be exactly what was in the file — "\n\n" here, verified independently via ZZInnerContentDumpTest during the original investigation.' );

		$tree = new OracleTree( $roots );
		$this->assertSame( $content, $tree->to_content() );
	}

	/**
	 * list-nested has both an outer list (3 items, 2 separators) and a nested
	 * sub-list (2 items, 1 separator) — confirms the amendment handles
	 * separators correctly at more than one nesting depth simultaneously.
	 */
	public function test_list_nested_preserves_separators_at_every_nesting_depth(): void {
		$content = (string) file_get_contents( self::CORPUS_DIR . '/list-nested.html' );
		$ids     = new IdGenerator();

		$roots = CorpusImporter::from_content( $content, $ids );
		$outer_list = $roots[0];

		$this->assertSame( 'core/list', $outer_list->block_name );
		$this->assertCount( 3, $outer_list->children );
		$this->assertCount( 4, $outer_list->separators );

		$second_item = $outer_list->children[1];
		$nested_list = $second_item->children[0];
		$this->assertSame( 'core/list', $nested_list->block_name );
		$this->assertCount( 2, $nested_list->children );
		$this->assertCount( 3, $nested_list->separators );

		$tree = new OracleTree( $roots );
		$this->assertSame( $content, $tree->to_content() );
	}
}
