<?php
/**
 * Spike S5, Phase 1b: the CHECKLIST.md no-op-save/diff step, run against the
 * provisional automated corpus.
 *
 * RESULT IS EXPLICITLY INCONCLUSIVE. `no-op-save-source.html` and
 * `no-op-save-after.html` were both produced by `wp post
 * create`/`wp post update` with byte-identical content — neither the real
 * Gutenberg browser editor nor its JavaScript ran at any point (see
 * spike/s5/corpus/authored/PROVENANCE_NOTICE.md). Byte equality here only
 * demonstrates that `wp post update` does not rewrite content it was given
 * unchanged, which was never in question. The actual purpose of this step —
 * catching editor-JS normalization that fires on every save even with no
 * content change — is UNTESTED by this pair and remains open. This step must
 * be re-run through the real browser editor before its conclusion can be
 * trusted, per PROVENANCE_NOTICE.md's own recommendation.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

final class NoOpSaveAnalysisTest extends \WP_UnitTestCase {

	private const CORPUS_DIR = __DIR__ . '/../corpus/authored';

	public function test_no_op_save_diff_is_recorded_as_inconclusive(): void {
		$before = (string) file_get_contents( self::CORPUS_DIR . '/no-op-save-source.html' );
		$after  = (string) file_get_contents( self::CORPUS_DIR . '/no-op-save-after.html' );

		$byte_identical = $before === $after;
		$sha1_before    = sha1( $before );
		$sha1_after     = sha1( $after );

		$record = array(
			'byte_identical'       => $byte_identical,
			'sha1_before'          => $sha1_before,
			'sha1_after'           => $sha1_after,
			'bytes_before'         => strlen( $before ),
			'bytes_after'          => strlen( $after ),
			'conclusive'           => false,
			'reason'               => 'Both files were produced via WP-CLI (wp post create / wp post update), not a real Gutenberg browser-editor save. Byte equality here demonstrates only that wp post update does not rewrite unchanged content — it does not exercise, and therefore cannot confirm or refute, editor-JS save-time normalization. See spike/s5/corpus/authored/PROVENANCE_NOTICE.md.',
			'requirement_status'   => 'OPEN — requires re-running this step through the real browser editor.',
		);

		file_put_contents(
			self::CORPUS_DIR . '/../no-op-save-analysis.json',
			wp_json_encode( $record, JSON_PRETTY_PRINT )
		);

		// The only thing this test may assert is the mechanically observable
		// fact (byte equality or not) — never a conclusion about editor
		// behaviour, which this pair cannot speak to either way.
		$this->assertSame( $sha1_before, $sha1_after, 'Sanity check on the fixture pair itself, not a claim about editor normalization.' );
		$this->assertFalse( $record['conclusive'], 'This test must never be changed to assert conclusiveness without a real browser-editor re-save behind it.' );
	}
}
