<?php
/**
 * Spike S5, Strategy A-G harness: proves RealBlockWalker and
 * ReconciliationSimulator are themselves correct on simple, hand-verified
 * cases BEFORE any strategy's evaluation results are trusted.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Strategy/RealBlockWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';

use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;

final class StrategyHarnessTest extends \WP_UnitTestCase {

	public function test_walker_skips_freeform_blocks(): void {
		$content = "<!-- wp:paragraph --><p>A.</p><!-- /wp:paragraph -->\n<!-- wp:paragraph --><p>B.</p><!-- /wp:paragraph -->";

		$eligible = RealBlockWalker::walk_eligible( $content );

		$this->assertCount( 2, $eligible, 'The freeform "\n" between the two paragraphs must not be counted.' );
		$this->assertSame( 'core/paragraph', $eligible[0]['block_name'] );
		$this->assertStringContainsString( 'A.', $eligible[0]['text'] );
		$this->assertStringContainsString( 'B.', $eligible[1]['text'] );
	}

	public function test_walker_skips_containers_themselves_but_descends_into_children(): void {
		$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Inside.</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$eligible = RealBlockWalker::walk_eligible( $content );

		$this->assertCount( 1, $eligible, 'The group itself must not be eligible; only its paragraph child.' );
		$this->assertSame( 'core/paragraph', $eligible[0]['block_name'] );
	}

	public function test_walker_skips_dynamic_blocks(): void {
		$content = '<!-- wp:latest-posts {"postsToShow":5} /-->';

		$eligible = RealBlockWalker::walk_eligible( $content );

		$this->assertSame( array(), $eligible, 'core/latest-posts is dynamic; its saved (empty) innerHTML never renders and must not be offered as a segment.' );
	}

	public function test_walker_skips_reusable_block_references(): void {
		$content = '<!-- wp:block {"ref":42} /-->';

		$eligible = RealBlockWalker::walk_eligible( $content );

		$this->assertSame( array(), $eligible, 'core/block references are dynamic (resolved at render time), never eligible.' );
	}

	public function test_walker_skips_genuinely_empty_content(): void {
		// A self-closing block has no innerHTML at all. Note this walker's
		// emptiness check is a simple trim()-on-the-whole-string, matching
		// production Extractor::extract()'s own simplistic check
		// ('' === trim($source)) — it does NOT strip HTML tags first, so
		// "<p></p>" (a paragraph with no TEXT but real wrapper markup) is
		// NOT considered empty by this check, matching that same production
		// behaviour rather than a stricter one this evaluator does not claim.
		$content = '<!-- wp:separator /-->';

		$eligible = RealBlockWalker::walk_eligible( $content );

		$this->assertSame( array(), $eligible );
	}

	public function test_walker_does_not_treat_html_wrapped_but_textless_content_as_empty(): void {
		// Documents the simplification above directly, rather than leaving
		// it implicit: matches production's actual (simplistic) emptiness
		// check, which this evaluator deliberately mirrors rather than
		// improves on.
		$content = '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';

		$eligible = RealBlockWalker::walk_eligible( $content );

		$this->assertCount( 1, $eligible, '"<p></p>" is non-empty as a bare string, so it is offered as a segment, matching Extractor::extract()\'s own trim()-only check.' );
	}

	public function test_reconciliation_marks_unchanged_row_untouched(): void {
		$rows = array(
			'block:0' => array( 'source_hash' => ReconciliationSimulator::source_hash( 'Hello.' ), 'translated_text' => 'TRANS:Hello.', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '' ),
		);
		$new_segments = array( 'block:0' => array( 'block_name' => 'core/paragraph', 'text' => 'Hello.' ) );

		$result = ReconciliationSimulator::sync_source( $rows, $new_segments );

		$this->assertSame( 0, $result['block:0']['is_stale'] );
		$this->assertSame( 'TRANS:Hello.', $result['block:0']['translated_text'] );
	}

	public function test_reconciliation_marks_changed_row_stale_but_keeps_translation(): void {
		$rows = array(
			'block:0' => array( 'source_hash' => ReconciliationSimulator::source_hash( 'Hello.' ), 'translated_text' => 'TRANS:Hello.', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '' ),
		);
		$new_segments = array( 'block:0' => array( 'block_name' => 'core/paragraph', 'text' => 'Goodbye.' ) );

		$result = ReconciliationSimulator::sync_source( $rows, $new_segments );

		$this->assertSame( 1, $result['block:0']['is_stale'], 'Invariant I6: a source change flags stale.' );
		$this->assertSame( 'TRANS:Hello.', $result['block:0']['translated_text'], 'Invariant I6: translated_text is never overwritten by a source change.' );
		$this->assertSame( 'reviewed', $result['block:0']['status'], 'Invariant I6: status is never overwritten by a source change.' );
	}

	public function test_reconciliation_orphans_a_vanished_key_without_deleting_it(): void {
		$rows = array(
			'block:0' => array( 'source_hash' => ReconciliationSimulator::source_hash( 'Hello.' ), 'translated_text' => 'TRANS:Hello.', 'status' => 'reviewed', 'is_stale' => 0, 'error_code' => '' ),
		);

		$result = ReconciliationSimulator::sync_source( $rows, array() );

		$this->assertSame( ReconciliationSimulator::STATUS_IGNORED, $result['block:0']['status'] );
		$this->assertSame( 'orphaned', $result['block:0']['error_code'] );
		$this->assertArrayHasKey( 'block:0', $result, 'Orphaning must never delete the row.' );
	}

	public function test_reconciliation_does_nothing_when_there_are_no_existing_rows(): void {
		$result = ReconciliationSimulator::sync_source( array(), array( 'block:0' => array( 'block_name' => 'x', 'text' => 'y' ) ) );

		$this->assertSame( array(), $result, "Matches Store::sync_source()'s early return: extraction alone never creates rows." );
	}

	public function test_translated_value_withholds_ignored_and_missing_but_returns_stale(): void {
		$stale_but_reviewed = array( 'status' => 'reviewed', 'is_stale' => 1, 'translated_text' => 'X' );
		$ignored            = array( 'status' => ReconciliationSimulator::STATUS_IGNORED, 'is_stale' => 0, 'translated_text' => 'X' );
		$missing            = array( 'status' => ReconciliationSimulator::STATUS_MISSING, 'is_stale' => 0, 'translated_text' => '' );

		$this->assertSame( 'X', ReconciliationSimulator::translated_value( $stale_but_reviewed ), 'Invariant I7: stale still renders.' );
		$this->assertNull( ReconciliationSimulator::translated_value( $ignored ) );
		$this->assertNull( ReconciliationSimulator::translated_value( $missing ) );
	}
}
