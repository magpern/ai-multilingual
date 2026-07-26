<?php
/**
 * Spike S5, Phase 0, evidence set 1: TARGETED ADVERSARIAL ASSEMBLY TESTS.
 *
 * These fixtures are hand-constructed, not editor-authored — the authentic
 * Gutenberg corpus does not exist yet (see the corpus-authoring hold point in
 * spike/s5/corpus/CHECKLIST.md). They exist only to confirm or refute, against
 * real WP 7.0.2 core, the specific serializer divergences identified while
 * reading wp-includes/blocks.php and class-wp-block-parser.php during
 * planning. Once the editor-authored corpus lands (Phase 1b), this evidence
 * set is re-run against it as a SEPARATE, distinctly labelled evidence set —
 * see AuthenticCorpusAssemblyTest.php — before the assembly recommendation is
 * finalized. Do not conflate the two: a case that never occurs in real editor
 * output is a documented edge case, not a live risk, and the report must say
 * which is which.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

/**
 * Confirms serialize_blocks(parse_blocks($c)) === $c does NOT hold in general,
 * and documents exactly where it diverges.
 *
 * The "0"-content case, precisely, since an earlier pass at this file
 * mislocated it: (1) `parse_blocks()` itself OMITS the "0" span from
 * `innerContent`/`innerHTML` — this is a PARSE-time omission
 * (`class-wp-block-parser.php:349,373`, `! empty( $html )`), not something
 * `serialize_blocks()` does. (2) Consequently, no extractor built on
 * `parse_blocks()` can ever locate or translate that span — the information
 * is gone before extraction starts, regardless of assembly strategy. (3) That
 * said, byte-splice assembly (OffsetExtractor + Splicer; see
 * SpliceAssemblyTest) still preserves the original "0" content correctly
 * WHEN THAT BLOCK IS NOT ITSELF A TRANSLATION TARGET — splicing only ever
 * touches the byte ranges it was explicitly told to replace, so a "0" block
 * sitting untranslated elsewhere in the document survives untouched, exactly
 * as authored, even though nothing can ever generate a replacement FOR it.
 */
final class SerializerDivergenceTest extends \WP_UnitTestCase {

	private function round_trip( string $content ): string {
		return serialize_blocks( parse_blocks( $content ) );
	}

	// -- Data-losing cases: these must be treated as corruption, never as an --
	// -- acceptable divergence. --

	/**
	 * Corrected finding: the loss of a literal "0" body is NOT a serializer
	 * effect. It happens one step earlier, inside the PARSER itself.
	 * WP_Block_Parser::add_block_from_stack() (and ::add_inner_block(), for a
	 * "0" immediately preceding a nested block) guards the innerHTML/
	 * innerContent append with `! empty( $html )`
	 * (class-wp-block-parser.php:373 and :349), and PHP's `empty("0")` is
	 * `true`. So parse_blocks() itself returns innerHTML === '' and
	 * innerContent === array() for this input — the content is gone before
	 * serialize_blocks() is ever called, and before any extractor built on
	 * parse_blocks() ever sees it.
	 *
	 * The practical consequence is sharper than a round-trip byte diff: no
	 * splice-based assembly strategy can rescue this content either, because
	 * extraction itself has nothing to locate. This is a narrow, acceptable
	 * limitation — a block whose entire raw body is the single character "0"
	 * — to record explicitly rather than silently drop segments for.
	 */
	public function test_content_that_is_the_string_zero_is_lost_at_parse_time(): void {
		$original = '<!-- wp:html -->0<!-- /wp:html -->';

		$blocks = parse_blocks( $original );

		$this->assertSame(
			'',
			$blocks[0]['innerHTML'],
			'Precondition: parse_blocks() must already have discarded the literal "0" — ' .
			'this is a parse-time loss, not a serialize-time one.'
		);
		$this->assertSame( array(), $blocks[0]['innerContent'] );

		$round_tripped = $this->round_trip( $original );

		$this->assertNotSame( $original, $round_tripped );
		$this->assertMatchesRegularExpression(
			'#<!-- wp:html\s*/-->#',
			$round_tripped,
			'Serialization of the already-emptied block produces the void (self-closing) delimiter.'
		);
	}

	public function test_invalid_attribute_json_drops_all_attributes(): void {
		// Syntactically matches the parser's permissive attrs token (a brace
		// blob before the closing "-->"), but is not valid JSON: single quotes.
		$original = "<!-- wp:paragraph {'bad':1} -->\n<p>Text.</p>\n<!-- /wp:paragraph -->";

		$blocks = parse_blocks( $original );

		$this->assertNull(
			$blocks[0]['attrs'],
			'Precondition: json_decode() of the invalid attrs blob must fail to null.'
		);

		$round_tripped = $this->round_trip( $original );

		$this->assertStringNotContainsString(
			"'bad'",
			$round_tripped,
			'The unparseable attribute text must not survive the round trip.'
		);
		$this->assertMatchesRegularExpression(
			'#<!-- wp:paragraph -->#',
			$round_tripped,
			'serialize_block() replaces null attrs with array(), so no attribute blob should be emitted at all.'
		);
	}

	public function test_unclosed_block_reparents_its_following_sibling(): void {
		$original = "<!-- wp:group -->\n<div class=\"wp-block-group\">\n<!-- wp:paragraph -->\n<p>Inside.</p>\n<!-- /wp:paragraph -->\n</div>\n"
			. "<!-- wp:paragraph -->\n<p>Meant to be a sibling, after the group.</p>\n<!-- /wp:paragraph -->";

		$blocks = parse_blocks( $original );

		// If the parser's implicit-closer recovery reparented the trailing
		// paragraph, there is exactly one top-level block (the group), and the
		// "sibling" paragraph is really the group's second child.
		$this->assertCount(
			1,
			$blocks,
			'Precondition: the unclosed wp:group must have absorbed the following paragraph as recovery, leaving one top-level block.'
		);
		$this->assertCount(
			2,
			$blocks[0]['innerBlocks'],
			'The paragraph meant to follow the group must have been reparented inside it.'
		);
	}

	// -- Byte-diverging but content-preserving cases: not corruption, but --
	// -- proof that a naive string-equality check on the whole document --
	// -- cannot be used as a correctness oracle for assembly. --

	public function test_core_namespace_prefix_is_stripped_on_serialize(): void {
		$original = "<!-- wp:core/paragraph -->\n<p>Text.</p>\n<!-- /wp:core/paragraph -->";

		$round_tripped = $this->round_trip( $original );

		$this->assertNotSame( $original, $round_tripped );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $round_tripped );
		$this->assertStringNotContainsString( 'wp:core/paragraph', $round_tripped );
	}

	public function test_empty_attrs_object_is_dropped(): void {
		$original = "<!-- wp:paragraph {} -->\n<p>Text.</p>\n<!-- /wp:paragraph -->";

		$round_tripped = $this->round_trip( $original );

		$this->assertNotSame( $original, $round_tripped );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $round_tripped );
	}

	public function test_delimiter_whitespace_is_normalized_to_one_space(): void {
		$original = "<!--   wp:paragraph   -->\n<p>Text.</p>\n<!--   /wp:paragraph   -->";

		$blocks = parse_blocks( $original );
		$this->assertSame( 'core/paragraph', $blocks[0]['blockName'], 'Precondition: the parser must tolerate the extra whitespace.' );

		$round_tripped = $this->round_trip( $original );

		$this->assertNotSame( $original, $round_tripped );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $round_tripped );
	}

	public function test_empty_json_object_becomes_empty_array(): void {
		$original = "<!-- wp:paragraph {\"style\":{\"typography\":{}}} -->\n<p>Text.</p>\n<!-- /wp:paragraph -->";

		$blocks = parse_blocks( $original );
		$this->assertSame( array(), $blocks[0]['attrs']['style']['typography'], 'Precondition: json_decode(assoc) turns {} into an empty PHP array.' );

		$round_tripped = $this->round_trip( $original );

		$this->assertNotSame( $original, $round_tripped );
		$this->assertStringContainsString( '"typography":[]', $round_tripped );
		$this->assertStringNotContainsString( '"typography":{}', $round_tripped );
	}

	// -- Control case: fully well-formed, canonical-form input should --
	// -- round-trip byte-identically. This is the case editor-authored --
	// -- content is expected to land in, and Phase 1b tests that claim. --

	public function test_canonical_well_formed_input_round_trips_byte_identically(): void {
		$original = "<!-- wp:paragraph -->\n<p>Plain, canonical, well-formed.</p>\n<!-- /wp:paragraph -->";

		$this->assertSame( $original, $this->round_trip( $original ) );
	}
}
