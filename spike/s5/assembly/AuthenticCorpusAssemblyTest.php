<?php
/**
 * Spike S5, Phase 1b: reruns the Phase 0 assembly/round-trip verification
 * against the provisional automated corpus in spike/s5/corpus/authored/.
 *
 * THIS IS A SEPARATE EVIDENCE SET FROM PHASE 0's ADVERSARIAL FIXTURES. Do not
 * merge or average results between the two. Phase 0's hand-built adversarial
 * fixtures were specifically constructed to trigger the three documented
 * serialize_blocks(parse_blocks()) divergences (void-collapse, invalid-JSON
 * attrs, unbalanced-block recovery) and to stress the offset-extraction
 * ordering bug this spike caught and fixed. This corpus was not constructed
 * to trigger anything — see PROVENANCE_NOTICE.md — and the results below
 * confirm it does not exercise any of those divergences: it is clean,
 * canonical-form markup. That is a property of THIS corpus, not evidence that
 * the divergences don't occur in real editor output; Phase 0's findings on
 * that stand entirely unchanged.
 *
 * PROVENANCE: spike/s5/corpus/authored/PROVENANCE_NOTICE.md applies to every
 * fixture loaded here. WP-CLI-generated, not real Gutenberg browser-editor
 * output.
 *
 * Uses OffsetExtractor/Splicer directly on file content strings — NOT
 * OracleNode/OracleTree — so every one of the 13 fixtures applies here,
 * including the 3 CorpusImportTest found unrepresentable by the Phase 1a
 * oracle container model. That model's limitation is specific to the
 * generator/oracle tooling; it does not affect OffsetExtractor/Splicer, which
 * Phase 0 already proved handles arbitrary inter-child interleaving.
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

final class AuthenticCorpusAssemblyTest extends \WP_UnitTestCase {

	private const CORPUS_DIR = __DIR__ . '/../corpus/authored';

	private OffsetExtractor $extractor;
	private Splicer $splicer;

	protected function setUp(): void {
		parent::setUp();
		$this->extractor = new OffsetExtractor();
		$this->splicer   = new Splicer();
	}

	private function fixtures(): array {
		$files = glob( self::CORPUS_DIR . '/*.html' );
		sort( $files );

		return $files;
	}

	/**
	 * Parse success, byte preservation via offset verbatim-ness, and
	 * round-trip fidelity — recorded per fixture, not merged into a single
	 * pass/fail.
	 */
	public function test_every_fixture_parses_and_locates_chunks_verbatim(): void {
		$results = array();

		foreach ( $this->fixtures() as $file ) {
			$slug    = basename( $file, '.html' );
			$content = (string) file_get_contents( $file );

			// Parse success.
			$blocks = parse_blocks( $content );
			$this->assertIsArray( $blocks, "$slug must parse without error." );
			$this->assertNotSame( array(), $blocks, "$slug must produce at least one block." );

			// Byte preservation / mapping stability: every located chunk is a
			// verbatim substring at its reported offset.
			$chunks = $this->extractor->locate_chunks( $content );
			foreach ( $chunks as $chunk ) {
				$this->assertSame(
					$chunk['text'],
					substr( $content, $chunk['offset'], $chunk['length'] ),
					"$slug: chunk at {$chunk['path']} must be verbatim at its offset."
				);
			}

			// Round-trip fidelity via core's own serializer (distinct from
			// splice-based assembly, checked separately below).
			$round_tripped   = serialize_blocks( $blocks );
			$round_trip_ok   = $round_tripped === $content;

			$results[ $slug ] = array(
				'parse_success'       => true,
				'block_count'         => count( $blocks ),
				'chunk_count'         => count( $chunks ),
				'round_trip_byte_ok'  => $round_trip_ok,
				'content_bytes'       => strlen( $content ),
			);
		}

		self::record( 'parse_and_locate', $results );

		// This corpus is expected to round-trip cleanly for every fixture —
		// unlike Phase 0's adversarial set, which is expected NOT to. Assert
		// that expectation explicitly so a future change in the corpus (or a
		// mistaken future edit introducing a divergent fixture here) is
		// caught rather than silently accepted.
		foreach ( $results as $slug => $r ) {
			$this->assertTrue( $r['round_trip_byte_ok'], "$slug was expected to round-trip byte-identically (clean, canonical-form markup); it did not. This is worth a closer look, not merging into the adversarial evidence set." );
		}
	}

	/**
	 * Assembly (not just extraction): splice one located chunk per fixture
	 * and confirm every other chunk's offset/text is unaffected — the same
	 * proof SpliceAssemblyTest gives for Phase 0's adversarial fixtures, run
	 * here against real corpus-shaped content instead.
	 */
	public function test_splice_touches_only_the_targeted_chunk_in_every_fixture(): void {
		$results = array();

		foreach ( $this->fixtures() as $file ) {
			$slug    = basename( $file, '.html' );
			$content = (string) file_get_contents( $file );

			$chunks = $this->extractor->locate_chunks( $content );

			// Pick the first chunk with non-empty text as the splice target;
			// several fixtures' only non-freeform chunk is the block's own
			// opaque body (e.g. html-block, table) — any real chunk proves
			// the point equally well.
			$target = null;
			foreach ( $chunks as $c ) {
				if ( '' !== trim( $c['text'] ) ) {
					$target = $c;
					break;
				}
			}

			if ( null === $target ) {
				$results[ $slug ] = array( 'skipped' => true, 'reason' => 'no non-empty chunk to target' );
				continue;
			}

			$result = $this->splicer->splice(
				$content,
				array(
					array(
						'offset'      => $target['offset'],
						'length'      => $target['length'],
						'expected'    => $target['text'],
						'replacement' => '[[SPLICED]]',
					),
				)
			);

			$this->assertTrue( $result['ok'], "$slug: splice must succeed against its own real content ({$result['error']})." );

			// Every OTHER chunk must be byte-identical, at the same offset,
			// before and after.
			$after_chunks = $this->extractor->locate_chunks( $result['content'] );
			$untouched_ok = true;

			foreach ( $chunks as $i => $before_chunk ) {
				if ( $before_chunk['offset'] === $target['offset'] ) {
					continue; // the one chunk we deliberately changed.
				}

				// Its own offset shifts if the splice changed the length of
				// text before it; what must NOT change is its TEXT, found at
				// whatever its new offset is (the same invariant
				// SpliceAssemblyTest checks in Phase 0).
				$found = null;
				foreach ( $after_chunks as $ac ) {
					if ( $ac['path'] === $before_chunk['path'] ) {
						$found = $ac;
						break;
					}
				}

				if ( null === $found || $found['text'] !== $before_chunk['text'] ) {
					$untouched_ok = false;
				}
			}

			$results[ $slug ] = array(
				'skipped'      => false,
				'splice_ok'    => true,
				'untouched_ok' => $untouched_ok,
			);

			$this->assertTrue( $untouched_ok, "$slug: every chunk other than the spliced one must be byte-identical after splicing." );
		}

		self::record( 'splice_assembly', $results );
	}

	private static function record( string $key, array $results ): void {
		$path = self::CORPUS_DIR . '/../authentic-assembly-results.json';
		$all  = file_exists( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array();
		$all[ $key ] = $results;
		file_put_contents( $path, wp_json_encode( $all, JSON_PRETTY_PRINT ) );
	}
}
