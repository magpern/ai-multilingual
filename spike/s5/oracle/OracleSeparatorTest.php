<?php
/**
 * Spike S5: focused tests for the OracleNode container model amendment —
 * arbitrary byte-exact content before the first child, between every pair of
 * children, and after the last. Every case here is checked against REAL
 * serialize_block()/parse_blocks(), never a hand-rolled stand-in.
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
use AIMultilingual\Spike\S5\Oracle\OracleNode;
use AIMultilingual\Spike\S5\Oracle\OracleTree;

final class OracleSeparatorTest extends \WP_UnitTestCase {

	public function test_zero_children_with_inner_content(): void {
		$ids  = new IdGenerator();
		$node = OracleNode::container( $ids->next(), 'core/html', array(), array( '<div>raw content, no nested blocks</div>' ), array() );

		$this->assertFalse( $node->is_leaf() );
		$this->assertCount( 0, $node->children );
		$this->assertCount( 1, $node->separators );

		$content = serialize_blocks( array( $node->to_parsed_array() ) );
		$this->assertSame( '<!-- wp:html --><div>raw content, no nested blocks</div><!-- /wp:html -->', $content );

		$reparsed = parse_blocks( $content );
		$this->assertSame( 'core/html', $reparsed[0]['blockName'] );
		$this->assertSame( '<div>raw content, no nested blocks</div>', $reparsed[0]['innerHTML'] );
		$this->assertSame( array(), $reparsed[0]['innerBlocks'] );
	}

	public function test_one_child_with_prefix_and_suffix_content(): void {
		$ids   = new IdGenerator();
		$child = Builders::paragraph( $ids, 'Only child.' );
		$node  = OracleNode::container( $ids->next(), 'core/quote', array(), array( '<blockquote>', '<cite>Attribution</cite></blockquote>' ), array( $child ) );

		$this->assertCount( 1, $node->children );
		$this->assertCount( 2, $node->separators );

		$content  = serialize_blocks( array( $node->to_parsed_array() ) );
		$expected = '<!-- wp:quote --><blockquote><!-- wp:paragraph --><p>Only child.</p><!-- /wp:paragraph --><cite>Attribution</cite></blockquote><!-- /wp:quote -->';
		$this->assertSame( $expected, $content );

		$reparsed = parse_blocks( $content );
		$this->assertSame( 'core/quote', $reparsed[0]['blockName'] );
		$this->assertCount( 1, $reparsed[0]['innerBlocks'] );
	}

	public function test_two_children_with_one_separator(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'First.' );
		$b   = Builders::paragraph( $ids, 'Second.' );
		$node = OracleNode::container( $ids->next(), 'core/group', array(), array( '<div>', '  <!-- a mid-document comment, not whitespace -->  ', '</div>' ), array( $a, $b ) );

		$content = serialize_blocks( array( $node->to_parsed_array() ) );
		$this->assertStringContainsString( '<!-- a mid-document comment, not whitespace -->', $content );

		$reparsed = parse_blocks( $content );
		$this->assertSame( 'core/group', $reparsed[0]['blockName'] );
		$this->assertCount( 2, $reparsed[0]['innerBlocks'] );

		// Byte-exact: the separator sits between the two children in the
		// actual serialized output, untouched.
		$first_end  = strpos( $content, '<!-- /wp:paragraph -->' );
		$second_start = strpos( $content, '<!-- wp:paragraph -->', $first_end );
		$between    = substr( $content, $first_end + strlen( '<!-- /wp:paragraph -->' ), $second_start - ( $first_end + strlen( '<!-- /wp:paragraph -->' ) ) );
		$this->assertSame( '  <!-- a mid-document comment, not whitespace -->  ', $between );
	}

	public function test_three_children_with_distinct_separators(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$c   = Builders::paragraph( $ids, 'C.' );

		$separators = array( 'PREFIX>', '<SEP-ONE>', '<SEP-TWO>', '<SUFFIX' );
		$node = OracleNode::container( $ids->next(), 'core/group', array(), $separators, array( $a, $b, $c ) );

		$this->assertSame( $separators, $node->separators, 'Every separator must be distinguishable and stored exactly as given — no two collapsed or confused.' );

		$content = serialize_blocks( array( $node->to_parsed_array() ) );
		$this->assertStringContainsString( 'PREFIX>', $content );
		$this->assertStringContainsString( '<SEP-ONE>', $content );
		$this->assertStringContainsString( '<SEP-TWO>', $content );
		$this->assertStringContainsString( '<SUFFIX', $content );

		// Document order: PREFIX before SEP-ONE before SEP-TWO before SUFFIX.
		$positions = array(
			strpos( $content, 'PREFIX>' ),
			strpos( $content, '<SEP-ONE>' ),
			strpos( $content, '<SEP-TWO>' ),
			strpos( $content, '<SUFFIX' ),
		);
		$sorted = $positions;
		sort( $sorted );
		$this->assertSame( $sorted, $positions, 'The four distinct separators must appear in the order they were assigned.' );
	}

	public function test_empty_string_separators_produce_no_extra_bytes(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$node = OracleNode::container( $ids->next(), 'core/group', array(), array( '', '', '' ), array( $a, $b ) );

		$content = serialize_blocks( array( $node->to_parsed_array() ) );

		// No wrapper div at all — matches Builders' pre-amendment behaviour
		// for containers with genuinely empty separators, confirming
		// backward compatibility.
		$this->assertSame(
			'<!-- wp:group --><!-- wp:paragraph --><p>A.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>B.</p><!-- /wp:paragraph --><!-- /wp:group -->',
			$content
		);
	}

	public function test_newline_newline_separator_preserved_verbatim(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$node = OracleNode::container( $ids->next(), 'core/buttons', array(), array( '', "\n\n", '' ), array( $a, $b ) );

		$content = serialize_blocks( array( $node->to_parsed_array() ) );
		$this->assertStringContainsString( "<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->", $content );

		// Not trimmed, not collapsed to a single \n, not normalized to a space.
		$reparsed = parse_blocks( $content );
		$gap      = null;
		foreach ( $reparsed[0]['innerContent'] as $chunk ) {
			if ( is_string( $chunk ) && "\n\n" === $chunk ) {
				$gap = $chunk;
			}
		}
		$this->assertSame( "\n\n", $gap, 'Real re-parse must see the exact two-newline gap, not a normalized substitute.' );
	}

	public function test_non_whitespace_inter_child_content_is_preserved_not_classified_as_translatable(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		// Deliberately not whitespace: an HTML comment with real words in it,
		// proving nothing here special-cases "looks like blank space".
		$node = OracleNode::container( $ids->next(), 'core/group', array(), array( '', '<!-- editorial note: keep these together -->', '' ), array( $a, $b ) );

		$content = serialize_blocks( array( $node->to_parsed_array() ) );
		$this->assertStringContainsString( '<!-- editorial note: keep these together -->', $content );

		// It is not treated as a segment: a generic OffsetExtractor-style walk
		// would see it as a location, but nothing in this model marks it
		// eligible for translation — that classification is explicitly out of
		// scope for OracleNode (see the class docblock and Phase 0's
		// equivalent finding for null-blockName chunks). Confirmed here by
		// checking it stays part of core/group's OWN innerContent — a
		// distinct top-level freeform block, which is how core represents an
		// unrelated comment sitting between two top-level blocks, is not what
		// this is: it is one block's own inter-child content.
		$reparsed = parse_blocks( $content );
		$this->assertSame( 'core/group', $reparsed[0]['blockName'] );
		$this->assertCount( 1, $reparsed, 'The comment must not have become its own separate top-level block.' );
		$this->assertStringContainsString( '<!-- editorial note', $reparsed[0]['innerContent'][1] ?? '' );
	}

	public function test_byte_exact_reconstruction_through_a_realistic_multi_child_shape(): void {
		$ids = new IdGenerator();
		$one = Builders::paragraph( $ids, 'One.' );
		$two = Builders::paragraph( $ids, 'Two.' );
		$three = Builders::paragraph( $ids, 'Three.' );

		$original_bytes = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:paragraph --><p>One.</p><!-- /wp:paragraph -->'
			. "\n\n"
			. '<!-- wp:paragraph --><p>Two.</p><!-- /wp:paragraph -->'
			. '  '
			. '<!-- wp:paragraph --><p>Three.</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:group -->';

		$node = OracleNode::container(
			$ids->next(),
			'core/group',
			array(),
			array( '<div class="wp-block-group">', "\n\n", '  ', '</div>' ),
			array( $one, $two, $three )
		);

		$content = serialize_blocks( array( $node->to_parsed_array() ) );
		$this->assertSame( $original_bytes, $content, 'Every byte, including the two DIFFERENT separators, must reconstruct exactly.' );
	}

	public function test_deterministic_mapping_is_unaffected_by_separator_presence(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$c   = Builders::paragraph( $ids, 'C.' );
		$node = OracleNode::container( $ids->next(), 'core/group', array(), array( 'x', 'y', 'z', 'w' ), array( $a, $b, $c ) );
		$tree = new OracleTree( array( $node ) );

		$paths = $tree->snapshot_paths();

		// Ids map to structural paths exactly as they would for an
		// all-empty-separator container — separators carry no identity and
		// must not perturb id-to-path mapping.
		$this->assertSame( '0', $paths[ $node->id ] );
		$this->assertSame( '0.0', $paths[ $a->id ] );
		$this->assertSame( '0.1', $paths[ $b->id ] );
		$this->assertSame( '0.2', $paths[ $c->id ] );
	}

	public function test_undo_redo_restores_byte_exact_content_including_separators(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$node = OracleNode::container( $ids->next(), 'core/buttons', array(), array( '<div>', "\n\n", '</div>' ), array( $a, $b ) );
		$tree = new OracleTree( array( $node ) );

		$original_content = $tree->to_content();
		$this->assertStringContainsString( "\n\n", $original_content );

		$tree->checkpoint();
		$tree->edit_text( $a->id, 'A, edited.' );

		$edited_content = $tree->to_content();
		$this->assertNotSame( $original_content, $edited_content );
		$this->assertStringContainsString( "\n\n", $edited_content, 'The separator must survive an edit to a sibling leaf untouched.' );

		$tree->undo();

		$this->assertSame( $original_content, $tree->to_content(), 'Undo must restore the exact original bytes, separators included — not just the text edit.' );
	}

	public function test_undo_redo_after_delete_and_insert_on_a_separator_bearing_container(): void {
		$ids = new IdGenerator();
		$a   = Builders::paragraph( $ids, 'A.' );
		$b   = Builders::paragraph( $ids, 'B.' );
		$c   = Builders::paragraph( $ids, 'C.' );
		$node = OracleNode::container( $ids->next(), 'core/group', array(), array( 'P>', '<S1>', '<S2>', '<X' ), array( $a, $b, $c ) );
		$tree = new OracleTree( array( $node ) );

		$this->assertCount( 4, $node->separators );

		$tree->checkpoint();
		$tree->delete( $b->id );

		// The container's own separators array must stay internally
		// consistent (count(children)+1) after the removal — this is the
		// "does not crash" guarantee the model amendment must preserve for
		// every existing operation.
		$this->assertCount( 2, $node->children );
		$this->assertCount( 3, $node->separators );

		$tree->undo();

		// undo() swaps $this->roots for the deep-cloned snapshot taken at
		// checkpoint() — a NEW object with the same id, not the same PHP
		// object $node still refers to. Look the current node up fresh
		// rather than assert on the stale pre-clone reference.
		$restored = $tree->find( $node->id );

		$this->assertNotNull( $restored );
		$this->assertCount( 3, $restored->children );
		$this->assertCount( 4, $restored->separators );
		$this->assertSame( array( 'P>', '<S1>', '<S2>', '<X' ), $restored->separators, 'Undo must restore the exact original separators array, not just the child count.' );
	}
}
