<?php
/**
 * Spike S5, Phase 0, evidence set 1 (targeted adversarial): validates that
 * OffsetExtractor locates every innerContent string chunk at its true byte
 * offset in the original document, including through nested blocks where a
 * naive "all of this block's chunks, then descend into children" walk order
 * mislocates chunks (caught and fixed during this same phase — see the class
 * docblock on OffsetExtractor for why children must be visited in place).
 *
 * Assertions here deliberately check ORDER and BYTE-VERBATIM CORRECTNESS of
 * located chunks rather than exact chunk counts. A wrapping block like
 * core/group contributes its own "before"/"after" wrapper-text chunks
 * whenever it has non-empty wrapper HTML around a nested child, and
 * whitespace sitting between two sibling blocks (outside either one's own
 * delimiters) is itself parsed as a separate `blockName === null` "freeform"
 * chunk — both confirmed against real core rather than assumed. Neither is a
 * translatable segment; both are source ranges/separators that OffsetExtractor
 * correctly reports because reporting every range is its job. Deciding which
 * ranges are eligible for translation is a registry/extraction-policy concern
 * for a later component, not something this offset finder — or these tests —
 * decide. See test_whitespace_between_sibling_blocks_is_its_own_null_block_name_chunk
 * for the dedicated proof. Hard-coding total counts would make these tests
 * brittle to those (correct, expected) mechanics without adding to what
 * Phase 0 needs to prove: that every offset is right and document order is
 * preserved.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/OffsetExtractor.php';

use AIMultilingual\Spike\S5\OffsetExtractor;

final class OffsetExtractionTest extends \WP_UnitTestCase {

	private OffsetExtractor $extractor;

	protected function setUp(): void {
		parent::setUp();
		$this->extractor = new OffsetExtractor();
	}

	private function assert_all_chunks_are_verbatim_at_their_offset( string $content, array $chunks ): void {
		foreach ( $chunks as $chunk ) {
			$this->assertSame(
				$chunk['text'],
				substr( $content, $chunk['offset'], $chunk['length'] ),
				sprintf( 'Chunk at path %s must be a verbatim substring at its reported offset.', $chunk['path'] )
			);
		}
	}

	/**
	 * Finds the offset of the (first) chunk whose text contains $needle, or
	 * fails the test — a content-addressed lookup so assertions about
	 * relative order do not depend on guessing the exact index a chunk lands
	 * at among wrapper/freeform chunks that are not under test.
	 */
	private function offset_of( array $chunks, string $needle ): int {
		foreach ( $chunks as $chunk ) {
			if ( str_contains( $chunk['text'], $needle ) ) {
				return $chunk['offset'];
			}
		}

		$this->fail( sprintf( 'No located chunk contains "%s".', $needle ) );
	}

	public function test_locates_a_single_flat_paragraph(): void {
		$content = '<!-- wp:paragraph --><p>Hello world.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );

		$this->assertCount( 1, $chunks );
		$this->assert_all_chunks_are_verbatim_at_their_offset( $content, $chunks );
		$this->assertStringContainsString( 'Hello world.', $chunks[0]['text'] );
	}

	public function test_locates_chunks_through_nested_blocks_in_true_document_order(): void {
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:paragraph --><p>Inside group.</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:group -->'
			. '<!-- wp:paragraph --><p>Sibling after group.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );

		$this->assert_all_chunks_are_verbatim_at_their_offset( $content, $chunks );
		$this->assertLessThan(
			$this->offset_of( $chunks, 'Sibling after group.' ),
			$this->offset_of( $chunks, 'Inside group.' ),
			'The nested chunk must be located before the following sibling, matching document order.'
		);
	}

	public function test_locates_nested_chunk_sandwiched_between_the_parents_own_text(): void {
		// This is the shape that actually breaks a "this block's own chunks
		// first, then descend" walk: the wrapping block has string content
		// both BEFORE and AFTER its nested child in innerContent, so the
		// child's chunk sits textually between two chunks that belong to the
		// parent. A parent-first walk finishes both parent chunks (advancing
		// the cursor past the second one) before ever searching for the
		// child's chunk, which then appears to be "before" the cursor.
		$content = '<!-- wp:group --><div class="wp-block-group"><p>Group intro.</p>'
			. '<!-- wp:paragraph --><p>Nested middle.</p><!-- /wp:paragraph -->'
			. '<p>Group outro.</p></div><!-- /wp:group -->';

		$chunks = $this->extractor->locate_chunks( $content );

		$this->assert_all_chunks_are_verbatim_at_their_offset( $content, $chunks );

		$intro  = $this->offset_of( $chunks, 'Group intro.' );
		$middle = $this->offset_of( $chunks, 'Nested middle.' );
		$outro  = $this->offset_of( $chunks, 'Group outro.' );

		$this->assertLessThan( $middle, $intro );
		$this->assertLessThan( $outro, $middle );
	}

	public function test_duplicate_identical_chunks_resolve_to_distinct_offsets(): void {
		$content = '<!-- wp:paragraph --><p>Repeat me.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>Repeat me.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );

		$this->assertCount( 2, $chunks, 'Exactly the two paragraphs; no inter-block whitespace in this fixture to produce a freeform chunk.' );
		$this->assertNotSame(
			$chunks[0]['offset'],
			$chunks[1]['offset'],
			'Two blocks with identical text must resolve to two distinct offsets, not both to the first occurrence.'
		);
		$this->assertLessThan( $chunks[1]['offset'], $chunks[0]['offset'] );

		$this->assert_all_chunks_are_verbatim_at_their_offset( $content, $chunks );
	}

	public function test_locates_chunks_in_a_columns_grid_in_document_order(): void {
		$content = '<!-- wp:columns --><div class="wp-block-columns">'
			. '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Column one.</p><!-- /wp:paragraph --></div><!-- /wp:column -->'
			. '<!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Column two.</p><!-- /wp:paragraph --></div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->';

		$chunks = $this->extractor->locate_chunks( $content );

		$this->assert_all_chunks_are_verbatim_at_their_offset( $content, $chunks );
		$this->assertLessThan(
			$this->offset_of( $chunks, 'Column two.' ),
			$this->offset_of( $chunks, 'Column one.' )
		);
	}

	public function test_skips_null_innercontent_positions_without_treating_them_as_chunks(): void {
		// A separator between two paragraphs contributes its own content
		// chunk (its wrapper markup), but the null markers in the OUTER
		// group's innerContent that point at each of its three children must
		// never themselves be mistaken for zero-length chunks to locate.
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:paragraph --><p>Before.</p><!-- /wp:paragraph -->'
			. '<!-- wp:separator --><hr class="wp-block-separator"/><!-- /wp:separator -->'
			. '<!-- wp:paragraph --><p>After.</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:group -->';

		$chunks = $this->extractor->locate_chunks( $content );

		$this->assert_all_chunks_are_verbatim_at_their_offset( $content, $chunks );
		$before = $this->offset_of( $chunks, 'Before.' );
		$after  = $this->offset_of( $chunks, 'After.' );
		$this->assertLessThan( $after, $before );

		foreach ( $chunks as $chunk ) {
			$this->assertNotSame( '', trim( $chunk['text'] ), 'No chunk should be a bare null/empty marker.' );
		}
	}

	/**
	 * Confirmed finding, not assumed: whitespace sitting between two sibling
	 * blocks — outside either one's own comment delimiters — is parsed by
	 * WordPress core as its own top-level block with `blockName === null`
	 * ("freeform" content), and therefore surfaces as its own chunk here. A
	 * real extractor must filter on `block_name` via a registry of
	 * translatable block types; this prototype deliberately does not filter,
	 * so this chunk is visible and must still be located correctly.
	 */
	public function test_whitespace_between_sibling_blocks_is_its_own_null_block_name_chunk(): void {
		$content = "<!-- wp:paragraph --><p>First.</p><!-- /wp:paragraph -->\n<!-- wp:paragraph --><p>Second.</p><!-- /wp:paragraph -->";

		$chunks = $this->extractor->locate_chunks( $content );

		$this->assertCount( 3, $chunks, 'First paragraph, the freeform "\n" gap, and the second paragraph.' );
		$this->assert_all_chunks_are_verbatim_at_their_offset( $content, $chunks );

		$null_name_chunks = array_values( array_filter( $chunks, static fn( $c ) => null === $c['block_name'] ) );
		$this->assertCount( 1, $null_name_chunks );
		$this->assertSame( "\n", $null_name_chunks[0]['text'] );
	}
}
