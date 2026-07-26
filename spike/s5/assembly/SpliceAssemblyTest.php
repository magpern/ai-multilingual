<?php
/**
 * Spike S5, Phase 0, evidence set 1 (targeted adversarial): proves that
 * OffsetExtractor + Splicer together change only the intended translatable
 * spans, leaving every other byte — including malformed markup that
 * serialize_blocks(parse_blocks()) would silently repair or destroy — exactly
 * as it was.
 *
 * Fixtures here deliberately concatenate blocks with no whitespace between
 * them, so that each authored block corresponds to exactly one located chunk
 * at a known index — OffsetExtractionTest is where the (confirmed, real)
 * behaviour of inter-block whitespace producing its own freeform chunk is
 * covered; mixing that concern into these tests would make the by-index
 * replacement wiring below unreadable without adding to what this file needs
 * to prove.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/OffsetExtractor.php';
require_once dirname( __DIR__ ) . '/lib/Splicer.php';

use AIMultilingual\Spike\S5\OffsetExtractor;
use AIMultilingual\Spike\S5\Splicer;

final class SpliceAssemblyTest extends \WP_UnitTestCase {

	private OffsetExtractor $extractor;
	private Splicer $splicer;

	protected function setUp(): void {
		parent::setUp();
		$this->extractor = new OffsetExtractor();
		$this->splicer   = new Splicer();
	}

	/**
	 * Builds a Splicer replacement entry from a located chunk plus new text,
	 * the way a real assembler would: replace only the translatable inner
	 * span, not the whole chunk, when the chunk is a full "<p>...</p>"
	 * wrapper. For these fixtures the chunk IS the translatable text, so the
	 * whole chunk is the replaced span.
	 */
	private function replacement( array $chunk, string $new_text ): array {
		return array(
			'offset'      => $chunk['offset'],
			'length'      => $chunk['length'],
			'expected'    => $chunk['text'],
			'replacement' => $new_text,
		);
	}

	public function test_splice_replaces_one_chunk_leaving_everything_else_byte_identical(): void {
		$content = '<!-- wp:paragraph --><p>First paragraph.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>Second paragraph.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );
		$this->assertCount( 2, $chunks );

		$result = $this->splicer->splice(
			$content,
			array( $this->replacement( $chunks[1], '<p>Andra stycket.</p>' ) )
		);

		$this->assertTrue( $result['ok'], (string) $result['error'] );

		$expected = '<!-- wp:paragraph --><p>First paragraph.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>Andra stycket.</p><!-- /wp:paragraph -->';

		$this->assertSame( $expected, $result['content'] );

		// The untouched first block's own chunk must be byte-identical, at
		// the same offset, in the spliced document.
		$before_chunks = $chunks;
		$after_chunks  = $this->extractor->locate_chunks( $result['content'] );

		$this->assertSame( $before_chunks[0]['offset'], $after_chunks[0]['offset'] );
		$this->assertSame( $before_chunks[0]['text'], $after_chunks[0]['text'] );
	}

	public function test_splice_with_differing_replacement_lengths_does_not_corrupt_later_or_earlier_offsets(): void {
		$content = '<!-- wp:paragraph --><p>A.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>B.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>C.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );
		$this->assertCount( 3, $chunks );

		// Middle replacement is much longer, first is shorter, last is
		// unchanged in length — this is exactly the case that breaks a naive
		// "apply in document order" splice, since earlier replacements shift
		// every later offset. Applying highest-offset-first avoids that.
		$result = $this->splicer->splice(
			$content,
			array(
				$this->replacement( $chunks[0], '<p>X.</p>' ),
				$this->replacement( $chunks[1], '<p>A much, much longer translated middle paragraph than the original.</p>' ),
				$this->replacement( $chunks[2], '<p>Z.</p>' ),
			)
		);

		$this->assertTrue( $result['ok'], (string) $result['error'] );

		$after_chunks = $this->extractor->locate_chunks( $result['content'] );
		$this->assertCount( 3, $after_chunks );
		$this->assertStringContainsString( 'X.', $after_chunks[0]['text'] );
		$this->assertStringContainsString( 'much longer translated middle', $after_chunks[1]['text'] );
		$this->assertStringContainsString( 'Z.', $after_chunks[2]['text'] );

		// Delimiters between blocks must be exactly as authored.
		$this->assertStringContainsString( '<!-- wp:paragraph --><p>X.</p><!-- /wp:paragraph -->', $result['content'] );
		$this->assertStringContainsString( '<!-- wp:paragraph --><p>Z.</p><!-- /wp:paragraph -->', $result['content'] );
	}

	public function test_splice_preserves_malformed_attribute_json_untouched(): void {
		// The same fixture that SerializerDivergenceTest proves
		// serialize_blocks(parse_blocks()) corrupts by dropping the attrs
		// entirely. Splicing must leave the malformed delimiter exactly as
		// authored, because it never calls serialize_block() at all.
		$content = "<!-- wp:paragraph {'bad':1} --><p>Text.</p><!-- /wp:paragraph -->"
			. '<!-- wp:paragraph --><p>Second.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );
		$this->assertCount( 2, $chunks );

		$result = $this->splicer->splice(
			$content,
			array( $this->replacement( $chunks[1], '<p>Andra.</p>' ) )
		);

		$this->assertTrue( $result['ok'], (string) $result['error'] );
		$this->assertStringContainsString(
			"<!-- wp:paragraph {'bad':1} --><p>Text.</p><!-- /wp:paragraph -->",
			$result['content'],
			'The malformed delimiter belonging to an untranslated block must survive byte-for-byte.'
		);
	}

	public function test_splice_leaves_an_untranslated_zero_content_html_block_untouched(): void {
		// SerializerDivergenceTest proves parse_blocks() itself already
		// discards a literal "0" body before any chunk is even produced
		// (class-wp-block-parser.php:373, `! empty($html)`). That means
		// locate_chunks() finds NOTHING to translate inside that block at
		// all — there is exactly one chunk in this document, the paragraph.
		// Splicing that one chunk must leave the "0" html block's region of
		// the ORIGINAL string completely untouched, since a byte-splice never
		// re-serializes anything outside the spans it was explicitly told to
		// replace.
		$content = '<!-- wp:html -->0<!-- /wp:html --><!-- wp:paragraph --><p>Translate me.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );
		$this->assertCount( 1, $chunks, 'The "0" html block contributes no chunk at all; only the paragraph does.' );
		$this->assertStringContainsString( 'Translate me.', $chunks[0]['text'] );

		$result = $this->splicer->splice(
			$content,
			array( $this->replacement( $chunks[0], '<p>Oversätt mig.</p>' ) )
		);

		$this->assertTrue( $result['ok'], (string) $result['error'] );
		$this->assertStringContainsString( '<!-- wp:html -->0<!-- /wp:html -->', $result['content'] );
	}

	/**
	 * Documents the accepted limitation directly, rather than only via the
	 * parser-internals assertion in SerializerDivergenceTest: a document whose
	 * ENTIRE translatable content is a single block with a literal "0" body
	 * offers no chunk to extract or splice at all. No assembly strategy can
	 * rescue this — the information is gone before extraction starts. This
	 * must be named in the ADR as a known, narrow gap, not silently produce
	 * an empty segment.
	 */
	public function test_a_document_that_is_only_zero_content_yields_no_locatable_chunk(): void {
		$content = '<!-- wp:html -->0<!-- /wp:html -->';

		$chunks = $this->extractor->locate_chunks( $content );

		$this->assertSame( array(), $chunks );
	}
}
