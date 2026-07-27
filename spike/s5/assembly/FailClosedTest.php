<?php
/**
 * Spike S5, Phase 0, evidence set 1 (targeted adversarial): proves the
 * fail-closed guarantee — when the document at a recorded offset no longer
 * contains what extraction expected, assembly aborts entirely and returns the
 * document untouched, rather than applying a partial or wrong edit.
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

final class FailClosedTest extends \WP_UnitTestCase {

	private OffsetExtractor $extractor;
	private Splicer $splicer;

	protected function setUp(): void {
		parent::setUp();
		$this->extractor = new OffsetExtractor();
		$this->splicer   = new Splicer();
	}

	public function test_splice_refuses_when_expected_bytes_have_moved(): void {
		$content = '<!-- wp:paragraph --><p>First.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>Second.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );

		// Simulate a stale offset: the caller recorded this chunk's location
		// against an OLDER version of the document that has since changed
		// underneath (a race, a second concurrent save, a caching bug —
		// anything that means the offset is no longer trustworthy).
		$changed_content = '<!-- wp:paragraph --><p>First, but edited since extraction.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>Second.</p><!-- /wp:paragraph -->';

		$result = $this->splicer->splice(
			$changed_content,
			array(
				array(
					'offset'      => $chunks[1]['offset'], // stale offset from the OLD document
					'length'      => $chunks[1]['length'],
					'expected'    => $chunks[1]['text'],
					'replacement' => '<p>Andra.</p>',
				),
			)
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame(
			$changed_content,
			$result['content'],
			'On a mismatch, the document must come back completely untouched, not partially patched.'
		);
		$this->assertStringContainsString( 'Refusing to splice', (string) $result['error'] );
	}

	public function test_splice_refuses_the_whole_batch_when_any_single_replacement_is_stale(): void {
		// Two valid replacements plus one stale one in the same call. The
		// requirement is atomic: either all apply, or none do — a partial
		// application would leave some segments translated and others not,
		// with no record of which succeeded.
		$content = '<!-- wp:paragraph --><p>A.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>B.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>C.</p><!-- /wp:paragraph -->';

		$chunks = $this->extractor->locate_chunks( $content );
		$this->assertCount( 3, $chunks );

		$result = $this->splicer->splice(
			$content,
			array(
				array(
					'offset'      => $chunks[0]['offset'],
					'length'      => $chunks[0]['length'],
					'expected'    => $chunks[0]['text'],
					'replacement' => '<p>X.</p>',
				),
				array(
					'offset'      => $chunks[1]['offset'],
					'length'      => $chunks[1]['length'],
					'expected'    => 'This is not what is actually there.',
					'replacement' => '<p>Y.</p>',
				),
				array(
					'offset'      => $chunks[2]['offset'],
					'length'      => $chunks[2]['length'],
					'expected'    => $chunks[2]['text'],
					'replacement' => '<p>Z.</p>',
				),
			)
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( $content, $result['content'], 'Two valid replacements must not be applied when a third in the same batch is stale.' );
	}

	public function test_splice_refuses_an_out_of_bounds_range(): void {
		$content = '<!-- wp:paragraph --><p>Short.</p><!-- /wp:paragraph -->';

		$result = $this->splicer->splice(
			$content,
			array(
				array(
					'offset'      => strlen( $content ) - 2,
					'length'      => 50,
					'expected'    => 'anything',
					'replacement' => 'x',
				),
			)
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( $content, $result['content'] );
		$this->assertStringContainsString( 'out of bounds', (string) $result['error'] );
	}

	public function test_extractor_throws_when_a_chunk_cannot_be_found_at_or_after_the_cursor(): void {
		// Under the corrected, order-preserving walk, every chunk's offset is
		// guaranteed to land at or after the cursor left by the previous
		// chunk — the parser builds innerContent by consecutive substr() calls
		// advancing through the document, so for any real parse_blocks()
		// output reached via the public locate_chunks() entry point, this
		// exception is unreachable. That is itself a spike finding: the guard
		// is defense-in-depth against a broken caller, not a live failure mode
		// of the extractor on real input.
		//
		// To prove the guard actually fires when that invariant is violated,
		// this test drives the protected walk_block() directly with a cursor
		// deliberately advanced past the only occurrence of the target text.
		$content = '<!-- wp:paragraph --><p>Unique original phrase.</p><!-- /wp:paragraph -->';

		$extractor = new class() extends OffsetExtractor {
			public function locate_from( string $content, int $start_cursor ): array {
				$blocks  = parse_blocks( $content );
				$cursor  = $start_cursor;
				$results = array();
				foreach ( $blocks as $index => $block ) {
					$this->walk_block( $block, $content, $cursor, (string) $index, $results );
				}
				return $results;
			}
		};

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/not found in the document at or after offset/' );

		$extractor->locate_from( $content, strlen( $content ) );
	}
}
