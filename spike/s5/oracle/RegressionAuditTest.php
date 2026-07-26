<?php
/**
 * Spike S5: explicit regression audit confirming the separator/container
 * model amendment changed nothing about how leaf blocks, dynamic blocks,
 * reusable-block references, synced-pattern references, or single-child
 * containers import and serialize. These categories are all handled by code
 * paths the amendment did not touch (the leaf branch of
 * CorpusImporter::convert() is unchanged; a single-child container's
 * separators shape — [prefix, suffix], 2 entries for 1 child — is exactly
 * what the pre-amendment wrapper_prefix/wrapper_suffix model already
 * produced), so this audit exists to prove that claim directly rather than
 * rely on it being true by inspection.
 *
 * Phase 0's adversarial fixtures are audited separately by re-running Phase
 * 0's OWN test files unchanged (SerializerDivergenceTest,
 * OffsetExtractionTest, SpliceAssemblyTest, FailClosedTest,
 * NoOpSaveAnalysisTest) — those operate on OffsetExtractor/Splicer directly
 * over content strings, never touching OracleNode, so there is nothing
 * Oracle-specific to re-test there; the audit is simply confirming they
 * still pass unmodified, reported in the review package.
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

final class RegressionAuditTest extends \WP_UnitTestCase {

	private const CORPUS_DIR = __DIR__ . '/../corpus/authored';

	public function test_leaf_blocks_are_unaffected_by_the_separator_amendment(): void {
		// table.html and html-block.html are both single, childless blocks —
		// the LEAF branch of convert() (empty($block['innerBlocks'])) is
		// completely untouched by this amendment; confirm it still produces
		// a leaf with the whole innerHTML as one opaque text field, prefix
		// and suffix empty, exactly as before.
		foreach ( array( 'table', 'html-block' ) as $slug ) {
			$content = (string) file_get_contents( self::CORPUS_DIR . "/$slug.html" );
			$ids     = new IdGenerator();
			$roots   = CorpusImporter::from_content( $content, $ids );

			$this->assertTrue( $roots[0]->is_leaf(), "$slug must still import as a leaf." );
			$this->assertSame( '', $roots[0]->prefix, "$slug: prefix must still be empty." );
			$this->assertSame( '', $roots[0]->suffix, "$slug: suffix must still be empty." );
			$this->assertSame( array(), $roots[0]->separators, "$slug: a leaf must never carry separators." );

			$tree = new OracleTree( $roots );
			$this->assertSame( $content, $tree->to_content(), "$slug must still reconstruct byte-identically." );
		}
	}

	public function test_dynamic_blocks_still_import_as_leaves_not_zero_child_containers(): void {
		// core/latest-posts is a self-closing dynamic block with no
		// innerBlocks at all. The amendment introduces zero-child CONTAINERS
		// as a new representable shape — this test confirms a real void
		// block still routes to the LEAF branch (empty($innerBlocks) is
		// checked first in convert(), unconditionally), never the new
		// container path, which would change is_leaf()'s answer for this
		// block type and could silently break anything downstream that
		// branches on it.
		$content = (string) file_get_contents( self::CORPUS_DIR . '/dynamic-block.html' );
		$ids     = new IdGenerator();
		$roots   = CorpusImporter::from_content( $content, $ids );

		$this->assertSame( 'core/latest-posts', $roots[0]->block_name );
		$this->assertTrue( $roots[0]->is_leaf(), 'A dynamic block with no innerBlocks must still import as a leaf, not a zero-child container.' );
		$this->assertSame( array( 'postsToShow' => 5, 'displayPostDate' => true ), $roots[0]->attrs );

		$tree = new OracleTree( $roots );
		$this->assertSame( $content, $tree->to_content() );
	}

	public function test_reusable_block_references_still_import_as_leaves(): void {
		// core/block {"ref":N} — void, no innerBlocks. Same guarantee as
		// dynamic blocks: still a leaf after the amendment.
		$content = (string) file_get_contents( self::CORPUS_DIR . '/reusable-block.html' );
		$ids     = new IdGenerator();
		$roots   = CorpusImporter::from_content( $content, $ids );

		// 4 top-level entries in this fixture: ref, freeform "\n\n", ref,
		// trailing freeform "\n\n" (the same trailing-whitespace shape Phase
		// 0 confirmed on every corpus fixture) — filter to the two real
		// core/block references, which is what this test is about.
		$block_refs = array_values( array_filter( $roots, static fn( $r ) => 'core/block' === $r->block_name ) );
		$this->assertCount( 2, $block_refs, 'Two separate core/block references in this fixture.' );

		foreach ( $block_refs as $root ) {
			$this->assertTrue( $root->is_leaf(), 'A reusable-block reference must still import as a leaf.' );
			$this->assertSame( 4621, $root->attrs['ref'] );
		}

		$tree = new OracleTree( $roots );
		$this->assertSame( $content, $tree->to_content() );
	}

	public function test_synced_pattern_references_still_import_as_leaves(): void {
		$content = (string) file_get_contents( self::CORPUS_DIR . '/synced-pattern.html' );
		$ids     = new IdGenerator();
		$roots   = CorpusImporter::from_content( $content, $ids );

		$this->assertSame( 'core/block', $roots[0]->block_name );
		$this->assertTrue( $roots[0]->is_leaf(), 'A synced-pattern reference (also core/block) must still import as a leaf.' );
		$this->assertSame( 4622, $roots[0]->attrs['ref'] );

		$tree = new OracleTree( $roots );
		$this->assertSame( $content, $tree->to_content() );
	}

	public function test_single_child_containers_produce_the_same_two_slot_separators_shape_as_before(): void {
		// quote-with-citation.html: core/quote wrapping exactly one
		// core/paragraph, plus its own trailing <cite> text. Before the
		// amendment, this was modelled as wrapper_prefix + 1 child +
		// wrapper_suffix — exactly two slots. Confirm the amendment produces
		// the IDENTICAL shape (separators has exactly 2 entries: prefix then
		// suffix), not some different N for the single-child case.
		$content = (string) file_get_contents( self::CORPUS_DIR . '/quote-with-citation.html' );
		$ids     = new IdGenerator();
		$roots   = CorpusImporter::from_content( $content, $ids );

		$quote = $roots[0];
		$this->assertSame( 'core/quote', $quote->block_name );
		$this->assertCount( 1, $quote->children );
		$this->assertCount( 2, $quote->separators, 'A single-child container must have exactly 2 separator slots: prefix and suffix, unchanged from the pre-amendment model.' );
		$this->assertSame( "\n<blockquote class=\"wp-block-quote\">", $quote->separators[0] );
		$this->assertSame( "<cite>Spike S5 corpus notes</cite></blockquote>\n", $quote->separators[1] );

		$tree = new OracleTree( $roots );
		$this->assertSame( $content, $tree->to_content() );
	}
}
