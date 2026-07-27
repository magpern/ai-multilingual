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
 * or a CONTAINER (carries children plus arbitrary byte-exact content around
 * and between them, no text span of its own).
 *
 * Model amendment: the automated corpus (spike/s5/corpus/authored/) revealed
 * that real multi-child Gutenberg containers carry parser-visible content
 * BETWEEN siblings (a "\n\n" between two core/button or core/list-item
 * children is the common case), not only before-all/after-all. An earlier
 * version of this class modelled a container as wrapper_prefix + children +
 * wrapper_suffix — exactly two slots — which cannot represent that. It is
 * now `separators`: an array of exactly `count(children) + 1` strings,
 * `separators[i]` being whatever sits immediately before `children[i]`
 * (`separators[0]` before the first child, `separators[N]` after the last).
 * A single-child container is separators=[prefix, suffix], unchanged from
 * before; a zero-child container is separators=[whatever content the block
 * has, with no children at all].
 *
 * Every byte of every separator is preserved verbatim: never trimmed, never
 * normalized, never merged into a child's own text, never treated as
 * translatable, never inferred where absent. `to_parsed_array()` only skips
 * emitting a literal empty-string entry, matching core's own parser, which
 * never produces one either — this changes no byte of serialized output
 * (concatenating "" contributes nothing) and keeps `verify_round_trip_shape()`
 * comparable against real `parse_blocks()` output.
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

	/**
	 * Container-only. Empty array on a leaf node. Length is always exactly
	 * count($children) + 1 — enforced in container(), never inferred.
	 *
	 * @var string[]
	 */
	public array $separators = array();

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
	 * @param string[]     $separators Exactly count($children) + 1 entries.
	 *                                 separators[i] is the verbatim content
	 *                                 immediately before children[i];
	 *                                 separators[count($children)] is
	 *                                 whatever follows the last child (or the
	 *                                 block's entire content, if $children is
	 *                                 empty).
	 */
	public static function container( int $id, ?string $block_name, array $attrs, array $separators, array $children ): self {
		if ( count( $separators ) !== count( $children ) + 1 ) {
			throw new \InvalidArgumentException(
				sprintf(
					'A container needs exactly count(children)+1 separators; got %d separators for %d children.',
					count( $separators ),
					count( $children )
				)
			);
		}

		$node             = new self( $id, $block_name, $attrs );
		$node->children   = array_values( $children );
		$node->separators = array_values( $separators );

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

		return self::container( $this->id, $this->block_name, $this->attrs, $this->separators, $children );
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

		return self::container( $ids->next(), $this->block_name, $this->attrs, $this->separators, $children );
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
		$n             = count( $this->children );

		for ( $i = 0; $i < $n; $i++ ) {
			if ( '' !== $this->separators[ $i ] ) {
				$inner_content[] = $this->separators[ $i ];
			}

			$inner_content[] = null;
			$inner_blocks[]  = $this->children[ $i ]->to_parsed_array();
		}

		if ( '' !== $this->separators[ $n ] ) {
			$inner_content[] = $this->separators[ $n ];
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
