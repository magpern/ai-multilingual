<?php
/**
 * Spike S5 prototype — one node of a ground-truth oracle tree: a real block
 * shape (block name, attrs, nesting) plus a stable logical id that lives only
 * in this PHP object, never in any attribute or serialized string.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

/**
 * A node is either a LEAF (carries one translatable text span, no children)
 * or a CONTAINER (carries children, no text span of its own). This is
 * narrower than what a real document can contain — Phase 0's
 * OffsetExtractionTest already proved the general case of a container with
 * its OWN text interleaved before/after nested children is located and
 * spliced correctly at the byte level — but the oracle's job is to prove
 * logical-id continuity through editor operations, not to re-litigate offset
 * walking, and every block shape in the corpus checklist (group, columns,
 * column, etc.) is authored as a pure container with no sibling text of its
 * own, so this narrower model matches what the real corpus will look like.
 *
 * `id` is intentionally never written into `attrs`, and `to_parsed_array()`
 * never emits it into the shape handed to core's serialize_block() — that is
 * the concrete mechanism behind "IDs live outside post_content".
 */
final class OracleNode {

	public int $id;
	public ?string $block_name;
	public array $attrs;

	/** @var OracleNode[] */
	public array $children = array();

	// Leaf-only. Null on a container node.
	public ?string $prefix = null;
	public ?string $text   = null;
	public ?string $suffix = null;

	// Container-only. Null on a leaf node.
	public ?string $wrapper_prefix = null;
	public ?string $wrapper_suffix = null;

	private function __construct( int $id, ?string $block_name, array $attrs ) {
		$this->id         = $id;
		$this->block_name = $block_name;
		$this->attrs      = $attrs;
	}

	public static function leaf( int $id, ?string $block_name, array $attrs, string $prefix, string $text, string $suffix ): self {
		$node          = new self( $id, $block_name, $attrs );
		$node->prefix  = $prefix;
		$node->text    = $text;
		$node->suffix  = $suffix;
		return $node;
	}

	/**
	 * @param OracleNode[] $children
	 */
	public static function container( int $id, ?string $block_name, array $attrs, string $wrapper_prefix, array $children, string $wrapper_suffix ): self {
		$node                 = new self( $id, $block_name, $attrs );
		$node->wrapper_prefix = $wrapper_prefix;
		$node->children       = $children;
		$node->wrapper_suffix = $wrapper_suffix;
		return $node;
	}

	public function is_leaf(): bool {
		return null !== $this->text;
	}

	/**
	 * Deep clone. Same ids throughout, INCLUDING this node's own id — cloning
	 * a tree (e.g. to snapshot "before" state for later comparison) must not
	 * fabricate new identities for content that has not changed.
	 */
	public function clone_deep(): self {
		if ( $this->is_leaf() ) {
			return self::leaf( $this->id, $this->block_name, $this->attrs, (string) $this->prefix, (string) $this->text, (string) $this->suffix );
		}

		$children = array_map( static fn( OracleNode $c ) => $c->clone_deep(), $this->children );

		return self::container( $this->id, $this->block_name, $this->attrs, (string) $this->wrapper_prefix, $children, (string) $this->wrapper_suffix );
	}

	/**
	 * Deep clone that assigns a FRESH id to this node and every descendant,
	 * via the supplied generator. Used by duplicate/copy operations: a copy
	 * is logically new content, never the same block as its source.
	 */
	public function clone_with_fresh_ids( IdGenerator $ids ): self {
		if ( $this->is_leaf() ) {
			return self::leaf( $ids->next(), $this->block_name, $this->attrs, (string) $this->prefix, (string) $this->text, (string) $this->suffix );
		}

		$children = array_map( static fn( OracleNode $c ) => $c->clone_with_fresh_ids( $ids ), $this->children );

		return self::container( $ids->next(), $this->block_name, $this->attrs, (string) $this->wrapper_prefix, $children, (string) $this->wrapper_suffix );
	}

	/**
	 * Builds the plain array shape core's serialize_block()/parse_blocks()
	 * use — the mechanism that keeps `id` out of any serialized markup: this
	 * method simply never reads or writes it.
	 *
	 * @return array{blockName: string|null, attrs: array, innerBlocks: array, innerHTML: string, innerContent: array}
	 */
	public function to_parsed_array(): array {
		if ( $this->is_leaf() ) {
			$inner_html = $this->prefix . $this->text . $this->suffix;

			return array(
				'blockName'    => $this->block_name,
				'attrs'        => $this->attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => $inner_html,
				'innerContent' => array( $inner_html ),
			);
		}

		$inner_content = array();
		$inner_blocks  = array();

		if ( '' !== $this->wrapper_prefix ) {
			$inner_content[] = $this->wrapper_prefix;
		}

		foreach ( $this->children as $child ) {
			$inner_content[] = null;
			$inner_blocks[]  = $child->to_parsed_array();
		}

		if ( '' !== $this->wrapper_suffix ) {
			$inner_content[] = $this->wrapper_suffix;
		}

		return array(
			'blockName'    => $this->block_name,
			'attrs'        => $this->attrs,
			'innerBlocks'  => $inner_blocks,
			// innerHTML is not read by serialize_block(); left empty rather
			// than computed, so nothing here relies on a value that would
			// only be approximate once children have been rearranged.
			'innerHTML'    => '',
			'innerContent' => $inner_content,
		);
	}
}
