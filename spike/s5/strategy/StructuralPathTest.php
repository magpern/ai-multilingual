<?php
/**
 * Spike S5 — independent tests for StructuralPathWalker path semantics.
 *
 * Must pass before Strategy C evaluation results are trusted. Documents and
 * locks the path rules defined in StructuralPathWalker.php.
 *
 * THROWAWAY. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Strategy/StructuralPathWalker.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/RealBlockWalker.php';

use AIMultilingual\Spike\S5\Strategy\RealBlockWalker;
use AIMultilingual\Spike\S5\Strategy\StructuralPathWalker;

final class StructuralPathTest extends \WP_UnitTestCase {

	/**
	 * @param array<int, array{path:string,block_name:string}> $tree
	 * @return array<string, string> path => block_name
	 */
	private function paths_by_name( array $tree ): array {
		$map = array();
		foreach ( $tree as $block ) {
			$map[ $block['path'] ] = $block['block_name'];
		}

		return $map;
	}

	public function test_root_level_sibling_indexes_are_zero_based(): void {
		$content = '<!-- wp:paragraph --><p>A.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>B.</p><!-- /wp:paragraph -->';

		$map = $this->paths_by_name( StructuralPathWalker::walk_tree( $content ) );

		$this->assertSame( array( '0' => 'core/paragraph', '1' => 'core/paragraph' ), $map );
	}

	public function test_freeform_chunks_do_not_consume_sibling_indexes(): void {
		$content = "<!-- wp:paragraph --><p>A.</p><!-- /wp:paragraph -->\n<!-- wp:paragraph --><p>B.</p><!-- /wp:paragraph -->";

		$map = $this->paths_by_name( StructuralPathWalker::walk_tree( $content ) );

		$this->assertSame( array( '0' => 'core/paragraph', '1' => 'core/paragraph' ), $map );
		$this->assertCount( 2, $map, 'The freeform newline must not appear as a tree node or shift indexes.' );
	}

	public function test_containers_occupy_path_components_and_children_nest(): void {
		$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Inside.</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$map = $this->paths_by_name( StructuralPathWalker::walk_tree( $content ) );

		$this->assertArrayHasKey( '0', $map );
		$this->assertSame( 'core/group', $map['0'] );
		$this->assertSame( 'core/paragraph', $map['0.0'] );
	}

	public function test_deep_nesting_matches_real_corpus_shape(): void {
		$content = (string) file_get_contents( __DIR__ . '/../corpus/authored/nested-group-columns.html' );

		$map = $this->paths_by_name( StructuralPathWalker::walk_tree( $content ) );

		$this->assertSame( 'core/group', $map['0'] );
		$this->assertSame( 'core/columns', $map['0.0'] );
		$this->assertSame( 'core/column', $map['0.0.0'] );
		$this->assertSame( 'core/paragraph', $map['0.0.0.0'] );
		$this->assertSame( 'core/column', $map['0.0.1'] );
		$this->assertSame( 'core/paragraph', $map['0.0.1.0'] );
	}

	public function test_dynamic_blocks_occupy_indexes_but_are_marked_dynamic(): void {
		$content = '<!-- wp:paragraph --><p>Before.</p><!-- /wp:paragraph -->'
			. '<!-- wp:latest-posts {"postsToShow":3} /-->'
			. '<!-- wp:paragraph --><p>After.</p><!-- /wp:paragraph -->';

		$tree = StructuralPathWalker::walk_tree( $content );
		$map  = $this->paths_by_name( $tree );

		$this->assertSame( array( '0' => 'core/paragraph', '1' => 'core/latest-posts', '2' => 'core/paragraph' ), $map );

		$dynamic = array_values( array_filter( $tree, static fn( array $b ): bool => $b['is_dynamic'] ) );
		$this->assertCount( 1, $dynamic );
		$this->assertSame( '1', $dynamic[0]['path'] );
	}

	public function test_reusable_block_references_occupy_indexes(): void {
		$content = '<!-- wp:paragraph --><p>Before.</p><!-- /wp:paragraph -->'
			. '<!-- wp:block {"ref":42} /-->'
			. '<!-- wp:paragraph --><p>After.</p><!-- /wp:paragraph -->';

		$map = $this->paths_by_name( StructuralPathWalker::walk_tree( $content ) );

		$this->assertSame( 'core/block', $map['1'] );
		$this->assertSame( 'core/paragraph', $map['2'] );
	}

	public function test_empty_container_still_has_a_path(): void {
		$content = '<!-- wp:group --><div class="wp-block-group"></div><!-- /wp:group -->'
			. '<!-- wp:paragraph --><p>After empty group.</p><!-- /wp:paragraph -->';

		$map = $this->paths_by_name( StructuralPathWalker::walk_tree( $content ) );

		$this->assertSame( 'core/group', $map['0'] );
		$this->assertSame( 'core/paragraph', $map['1'] );
		$this->assertArrayNotHasKey( '0.0', $map );
	}

	public function test_eligible_walker_uses_same_paths_as_tree_leaves(): void {
		$content = (string) file_get_contents( __DIR__ . '/../corpus/authored/nested-group-columns.html' );

		$eligible_paths = array_column( RealBlockWalker::walk_eligible( $content ), 'path' );

		$this->assertSame( array( '0.0.0.0', '0.0.1.0' ), $eligible_paths );
	}

	public function test_deterministic_traversal_order_is_stable_across_reparse(): void {
		$content = (string) file_get_contents( __DIR__ . '/../corpus/authored/headings-and-paragraphs.html' );

		$first  = StructuralPathWalker::walk_tree( $content );
		$second = StructuralPathWalker::walk_tree( $content );

		$this->assertSame( $first, $second );
	}
}
