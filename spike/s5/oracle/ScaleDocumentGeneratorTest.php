<?php
/**
 * Spike S5: proves ScaleDocumentGenerator's MECHANICS only — determinism,
 * correct per-repetition ids, and valid real Gutenberg markup at scale — using
 * a tiny, synthetic, hand-built template set.
 *
 * NOT AUTHENTIC CORPUS DATA. Nothing generated here may be cited as evidence
 * about real editor-authored content. Once the corpus checklist
 * (spike/s5/corpus/CHECKLIST.md) is fulfilled, the 100/500/1000-block
 * documents used for the actual performance measurements in the spike report
 * are built by re-invoking this SAME generator with those authentic blocks as
 * the template cycle — a separate step, not performed by this test file.
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
require_once dirname( __DIR__ ) . '/lib/Oracle/Builders.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/ScaleDocumentGenerator.php';

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\ScaleDocumentGenerator;

final class ScaleDocumentGeneratorTest extends \WP_UnitTestCase {

	/**
	 * A tiny, synthetic 2-block cycle. Deliberately not a realistic document
	 * — proving the machine works does not require realistic content, and
	 * mistaking this for corpus evidence is exactly what the class docblocks
	 * throughout this file warn against.
	 *
	 * @return array{0: \AIMultilingual\Spike\S5\Oracle\OracleNode[], 1: IdGenerator}
	 */
	private function tiny_synthetic_templates(): array {
		$ids = new IdGenerator();

		return array(
			array(
				Builders::paragraph( $ids, 'Template paragraph.' ),
				Builders::heading( $ids, 'Template heading.', 2 ),
			),
			$ids,
		);
	}

	private function variant(): callable {
		return static fn( string $text, int $cycle ): string => "{$text} (cycle {$cycle})";
	}

	public function test_reaches_at_least_the_target_block_count(): void {
		[$templates, $ids] = $this->tiny_synthetic_templates();

		$tree = ScaleDocumentGenerator::generate( $templates, $ids, 10, $this->variant() );

		$actual = ScaleDocumentGenerator::actual_block_count( $tree );

		$this->assertGreaterThanOrEqual( 10, $actual );
		// Never truncates mid-cycle: the overshoot can be at most one whole
		// template's worth of nodes (2, here) short of exact.
		$this->assertLessThanOrEqual( 10 + 2, $actual );
	}

	public function test_generated_content_is_valid_and_round_trips_through_real_parse_blocks(): void {
		[$templates, $ids] = $this->tiny_synthetic_templates();

		$tree = ScaleDocumentGenerator::generate( $templates, $ids, 20, $this->variant() );

		$content = $tree->to_content();
		$parsed  = parse_blocks( $content );

		$this->assertCount( count( $tree->roots() ), $parsed, 'Every generated root must be independently reparseable as one top-level block.' );

		foreach ( $parsed as $block ) {
			$this->assertContains( $block['blockName'], array( 'core/paragraph', 'core/heading' ) );
		}
	}

	public function test_generation_is_fully_deterministic(): void {
		[$templates_a, $ids_a] = $this->tiny_synthetic_templates();
		[$templates_b, $ids_b] = $this->tiny_synthetic_templates();

		$tree_a = ScaleDocumentGenerator::generate( $templates_a, $ids_a, 50, $this->variant() );
		$tree_b = ScaleDocumentGenerator::generate( $templates_b, $ids_b, 50, $this->variant() );

		$this->assertSame(
			$tree_a->to_content(),
			$tree_b->to_content(),
			'Two independent runs with fresh, identically-seeded inputs must produce byte-identical output — no hidden randomness.'
		);
	}

	public function test_each_repetition_receives_fresh_ids_and_distinguishable_text(): void {
		[$templates, $ids] = $this->tiny_synthetic_templates();

		$tree = ScaleDocumentGenerator::generate( $templates, $ids, 8, $this->variant() );

		$all_ids = array_keys( $tree->snapshot_paths() );
		$this->assertSame( count( $all_ids ), count( array_unique( $all_ids ) ), 'No two nodes across any repetition may share an id.' );

		$content = $tree->to_content();
		$this->assertStringContainsString( 'Template paragraph. (cycle 0)', $content );
		$this->assertStringContainsString( 'Template paragraph. (cycle 2)', $content, 'The second repetition of the cycle (index 2: templates has 2 entries per cycle) must carry distinguishable text, not a byte-identical duplicate of cycle 0.' );
	}

	public function test_refuses_an_empty_template_set(): void {
		$ids = new IdGenerator();

		$this->expectException( \RuntimeException::class );

		ScaleDocumentGenerator::generate( array(), $ids, 10, $this->variant() );
	}
}
