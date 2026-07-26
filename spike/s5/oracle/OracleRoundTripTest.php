<?php
/**
 * Spike S5, ground-truth oracle: proves the oracle tree corresponds to a real
 * WordPress parse_blocks() tree, and that logical ids never leak into the
 * serialized markup a strategy would actually see.
 *
 * Fixtures here are built directly via Builders (not editor-authored) solely
 * to prove the oracle FRAMEWORK's own mechanics — this is not, and must never
 * be labelled as, authentic corpus data.
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

use AIMultilingual\Spike\S5\Oracle\Builders;
use AIMultilingual\Spike\S5\Oracle\IdGenerator;
use AIMultilingual\Spike\S5\Oracle\OracleTree;

final class OracleRoundTripTest extends \WP_UnitTestCase {

	public function test_flat_tree_round_trips_through_real_parse_blocks(): void {
		$ids  = new IdGenerator();
		$tree = new OracleTree(
			array(
				Builders::paragraph( $ids, 'First.' ),
				Builders::paragraph( $ids, 'Second.' ),
			)
		);

		$result = $tree->verify_round_trip_shape();

		$this->assertTrue( $result['ok'], "Shape mismatch.\n" . var_export( $result, true ) );
	}

	public function test_nested_tree_round_trips_through_real_parse_blocks(): void {
		$ids  = new IdGenerator();
		$tree = new OracleTree(
			array(
				Builders::group(
					$ids,
					array(
						Builders::columns(
							$ids,
							array(
								Builders::column( $ids, array( Builders::paragraph( $ids, 'Column one.' ) ) ),
								Builders::column( $ids, array( Builders::paragraph( $ids, 'Column two.' ) ) ),
							)
						),
					)
				),
				Builders::paragraph( $ids, 'Sibling after group.' ),
			)
		);

		$result = $tree->verify_round_trip_shape();

		$this->assertTrue( $result['ok'], "Shape mismatch.\n" . var_export( $result, true ) );

		// The content really is real, parseable Gutenberg markup — not just
		// internally self-consistent.
		$content = $tree->to_content();
		$this->assertStringContainsString( '<!-- wp:group -->', $content );
		$this->assertStringContainsString( '<!-- wp:columns -->', $content );
		$this->assertStringContainsString( '<!-- wp:column -->', $content );
		$this->assertStringContainsString( 'Column one.', $content );
		$this->assertStringContainsString( 'Sibling after group.', $content );

		$reparsed = parse_blocks( $content );
		$this->assertCount( 2, $reparsed, 'Two top-level blocks: the group, and the sibling paragraph.' );
	}

	public function test_heading_level_attribute_survives_real_serialization(): void {
		$ids  = new IdGenerator();
		$tree = new OracleTree( array( Builders::heading( $ids, 'A title.', 3 ) ) );

		$content = $tree->to_content();
		$parsed  = parse_blocks( $content );

		$this->assertSame( 'core/heading', $parsed[0]['blockName'] );
		$this->assertSame( 3, $parsed[0]['attrs']['level'] );
	}

	public function test_logical_ids_never_appear_in_serialized_content(): void {
		$ids  = new IdGenerator( 42 );
		$tree = new OracleTree(
			array(
				Builders::paragraph( $ids, 'Text with the number 42 in it, coincidentally.' ),
				Builders::paragraph( $ids, 'Another paragraph.' ),
			)
		);

		$content = $tree->to_content();
		$parsed  = parse_blocks( $content );

		// The ids are 42 and 43. Proving "the id is not IN the markup" isn't
		// a naive substring search (42 legitimately appears in the text
		// above) — it's that every attrs array is exactly what the builder
		// supplied, with no extra key smuggled in, for every node.
		foreach ( $parsed as $block ) {
			$this->assertSame( array(), $block['attrs'], 'No oracle bookkeeping key was added to attrs.' );
		}

		// And the id-42 paragraph's own text is untouched — the coincidental
		// "42" in its authored text is exactly what was written, not a marker.
		$this->assertSame( 'core/paragraph', $parsed[0]['blockName'] );
		$this->assertStringContainsString( 'the number 42 in it, coincidentally', $parsed[0]['innerHTML'] );
	}

	public function test_snapshot_paths_reflects_document_order(): void {
		$ids  = new IdGenerator();
		$para1 = Builders::paragraph( $ids, 'A.' );
		$para2 = Builders::paragraph( $ids, 'B.' );
		$tree  = new OracleTree( array( $para1, $para2 ) );

		$paths = $tree->snapshot_paths();

		$this->assertSame( '0', $paths[ $para1->id ] );
		$this->assertSame( '1', $paths[ $para2->id ] );
	}

	public function test_snapshot_paths_reflects_nesting(): void {
		$ids   = new IdGenerator();
		$inner = Builders::paragraph( $ids, 'Nested.' );
		$group = Builders::group( $ids, array( $inner ) );
		$tree  = new OracleTree( array( $group ) );

		$paths = $tree->snapshot_paths();

		$this->assertSame( '0', $paths[ $group->id ] );
		$this->assertSame( '0.0', $paths[ $inner->id ] );
	}
}
