<?php
/**
 * Spike S5 prototype — a mutable tree of OracleNodes plus the deterministic
 * transforms that model editor operations on it, and the machinery to prove,
 * empirically, that every tree corresponds to a real WordPress
 * parse_blocks() tree.
 *
 * THROWAWAY CODE. Not autoloaded, not covered by phpcs, not merged to main.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Oracle;

/**
 * Every mutating operation here is a deterministic function of its explicit
 * arguments — no randomness, no wall clock, no hidden state beyond the tree
 * and the shared IdGenerator a caller supplies. Reproducibility follows
 * directly from that: the same call sequence against the same starting tree
 * always produces the same ids in the same places.
 *
 * `id` values are never read from or written into `attrs`, `innerContent`, or
 * any other field `to_parsed_array()`/`to_content()` emits — that is the
 * concrete mechanism behind "logical ids live outside post_content".
 */
final class OracleTree {

	/** @var OracleNode[] */
	private array $roots;

	/** @var array<int, OracleNode[]> */
	private array $history = array();

	/** @var array<int, OracleNode[]> */
	private array $redo_stack = array();

	/**
	 * @param OracleNode[] $roots
	 */
	public function __construct( array $roots ) {
		$this->roots = $roots;
	}

	/**
	 * @return OracleNode[]
	 */
	public function roots(): array {
		return $this->roots;
	}

	// -- Real-parser correspondence --

	/**
	 * Serializes via core's OWN serialize_blocks(), never a hand-rolled
	 * stand-in. This is the one function anything downstream (a would-be
	 * strategy's extractor, or a human) ever sees; it contains no trace of
	 * any node's id.
	 */
	public function to_content(): string {
		$arrays = array_map( static fn( OracleNode $n ) => $n->to_parsed_array(), $this->roots );

		return serialize_blocks( $arrays );
	}

	/**
	 * Round-trips this tree's serialized content through core's REAL
	 * parse_blocks() and reports whether the reparsed shape (block names and
	 * nesting, in document order) matches what the oracle tree describes.
	 * This is the empirical proof that oracle trees correspond to real
	 * parse_blocks() trees, rather than an assumption.
	 *
	 * @return array{ok: bool, expected: array, actual: array}
	 */
	public function verify_round_trip_shape(): array {
		$content = $this->to_content();
		$parsed  = parse_blocks( $content );

		$expected = array_map( static fn( OracleNode $n ) => self::shape( $n ), $this->roots );
		$actual   = array_map( array( self::class, 'shape_of_parsed' ), $parsed );

		return array(
			'ok'       => $expected === $actual,
			'expected' => $expected,
			'actual'   => $actual,
		);
	}

	/**
	 * @return array{name: string|null, text: ?string, children: array}
	 */
	private static function shape( OracleNode $node ): array {
		if ( $node->is_leaf() ) {
			return array(
				'name'     => $node->block_name,
				// Compared against the reparsed block's full innerHTML below,
				// not the bare $node->text — the wrapper (e.g. "<p>"/"</p>")
				// is part of what a real parse actually returns.
				'text'     => $node->prefix . $node->text . $node->suffix,
				'children' => array(),
			);
		}

		return array(
			'name'     => $node->block_name,
			'text'     => null,
			'children' => array_map( array( self::class, 'shape' ), $node->children ),
		);
	}

	/**
	 * @param array<string, mixed> $block Real parse_blocks() output for one block.
	 * @return array{name: string|null, text: ?string, children: array}
	 */
	private static function shape_of_parsed( array $block ): array {
		if ( empty( $block['innerBlocks'] ) ) {
			// Leaf: the translatable text is whatever this block's own
			// innerHTML is, once its own wrapper prefix/suffix strip away —
			// the oracle already knows those, so for the round-trip check it
			// is enough to confirm the FULL innerHTML matches prefix+text+suffix,
			// which to_parsed_array() constructed identically; comparing
			// innerHTML directly is therefore an equivalent, simpler check.
			return array(
				'name'     => $block['blockName'],
				'text'     => $block['innerHTML'],
				'children' => array(),
			);
		}

		return array(
			'name'     => $block['blockName'],
			'text'     => null,
			'children' => array_map( array( self::class, 'shape_of_parsed' ), $block['innerBlocks'] ),
		);
	}

	// -- Lookup --

	public function find( int $id ): ?OracleNode {
		return self::find_in( $this->roots, $id );
	}

	/**
	 * @param OracleNode[] $nodes
	 */
	private static function find_in( array $nodes, int $id ): ?OracleNode {
		foreach ( $nodes as $node ) {
			if ( $node->id === $id ) {
				return $node;
			}

			$found = self::find_in( $node->children, $id );

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Every id currently in the tree mapped to its structural path (document
	 * order, dot-joined indices) — the "where is this logical block right
	 * now" ground truth a strategy's guess is checked against.
	 *
	 * @return array<int, string>
	 */
	public function snapshot_paths(): array {
		$map = array();
		self::collect_paths( $this->roots, '', $map );

		return $map;
	}

	/**
	 * @param OracleNode[]      $nodes
	 * @param array<int,string> $map
	 */
	private static function collect_paths( array $nodes, string $prefix, array &$map ): void {
		foreach ( $nodes as $index => $node ) {
			$path         = '' === $prefix ? (string) $index : $prefix . '.' . $index;
			$map[ $node->id ] = $path;

			self::collect_paths( $node->children, $path, $map );
		}
	}

	/**
	 * Compares two id->path snapshots (typically "before" and "after" one or
	 * more operations) and classifies every id: still present (and whether it
	 * moved), deleted, or newly created. This IS the explicit
	 * old-logical-id-to-new-logical-id oracle: for a surviving id the "new
	 * id" is trivially the same value, and what a strategy must be graded on
	 * is whether it reattaches its own guessed identity to that same logical
	 * block — not whether numeric ids match, which no real strategy can see.
	 *
	 * @param array<int,string> $before
	 * @param array<int,string> $after
	 * @return array{unchanged: array<int,string>, moved: array<int,array{from:string,to:string}>, deleted: array<int,string>, created: array<int,string>}
	 */
	public static function diff_snapshots( array $before, array $after ): array {
		$unchanged = array();
		$moved     = array();
		$deleted   = array();
		$created   = array();

		foreach ( $before as $id => $path ) {
			if ( ! array_key_exists( $id, $after ) ) {
				$deleted[ $id ] = $path;
				continue;
			}

			if ( $after[ $id ] === $path ) {
				$unchanged[ $id ] = $path;
			} else {
				$moved[ $id ] = array(
					'from' => $path,
					'to'   => $after[ $id ],
				);
			}
		}

		foreach ( $after as $id => $path ) {
			if ( ! array_key_exists( $id, $before ) ) {
				$created[ $id ] = $path;
			}
		}

		return array(
			'unchanged' => $unchanged,
			'moved'     => $moved,
			'deleted'   => $deleted,
			'created'   => $created,
		);
	}

	// -- Cloning --

	public function clone_deep(): self {
		return new self( array_map( static fn( OracleNode $n ) => $n->clone_deep(), $this->roots ) );
	}

	// -- History (undo/redo) --

	public function checkpoint(): void {
		$this->history[]  = array_map( static fn( OracleNode $n ) => $n->clone_deep(), $this->roots );
		$this->redo_stack = array();
	}

	public function undo(): void {
		if ( array() === $this->history ) {
			throw new \RuntimeException( 'No checkpoint to undo to.' );
		}

		$this->redo_stack[] = array_map( static fn( OracleNode $n ) => $n->clone_deep(), $this->roots );
		$this->roots         = array_pop( $this->history );
	}

	public function redo(): void {
		if ( array() === $this->redo_stack ) {
			throw new \RuntimeException( 'No redo state available.' );
		}

		$this->history[] = array_map( static fn( OracleNode $n ) => $n->clone_deep(), $this->roots );
		$this->roots      = array_pop( $this->redo_stack );
	}

	// -- Mutating operations --

	/**
	 * Removes a node (with its whole subtree) from wherever it currently
	 * lives and returns it, so callers modelling "delete" can hold onto it
	 * for a later "restore" that must preserve the exact same id.
	 */
	public function delete( int $node_id ): OracleNode {
		$removed = self::remove_node( $this->roots, $node_id );

		if ( null === $removed ) {
			throw new \RuntimeException( "No node with id {$node_id} to delete." );
		}

		return $removed;
	}

	/**
	 * Re-inserts a node (typically one just returned by delete()) at an
	 * explicit location, with its id UNCHANGED — modelling an editor undo or
	 * a revision restore bringing back the exact same logical block, not a
	 * fresh copy of it.
	 */
	public function restore( ?int $parent_id, int $index, OracleNode $node ): void {
		if ( ! self::insert_node( $this->roots, $parent_id, $index, $node ) ) {
			throw new \RuntimeException( "No parent with id {$parent_id} to restore into." );
		}
	}

	public function reorder( ?int $parent_id, int $from_index, int $to_index ): void {
		$siblings = self::siblings_ref( $this->roots, $parent_id );

		if ( null === $siblings ) {
			throw new \RuntimeException( "No parent with id {$parent_id}." );
		}

		$node = $siblings[ $from_index ] ?? null;

		if ( null === $node ) {
			throw new \RuntimeException( "No child at index {$from_index}." );
		}

		array_splice( $siblings, $from_index, 1 );
		array_splice( $siblings, $to_index, 0, array( $node ) );

		self::write_siblings( $this->roots, $parent_id, $siblings );
	}

	public function insert( ?int $parent_id, int $index, OracleNode $new_node ): void {
		if ( ! self::insert_node( $this->roots, $parent_id, $index, $new_node ) ) {
			throw new \RuntimeException( "No parent with id {$parent_id} to insert into." );
		}
	}

	/**
	 * Clones a subtree with entirely FRESH ids and inserts the clone
	 * immediately after the original among the same siblings. The original
	 * keeps its own id unchanged. Duplicated content is logically new
	 * content, never the same block as its source — two segments with
	 * identical text must never be treated as one identity.
	 *
	 * @return int The new clone's root id.
	 */
	public function duplicate( int $node_id, IdGenerator $ids ): int {
		$original = $this->find( $node_id );

		if ( null === $original ) {
			throw new \RuntimeException( "No node with id {$node_id} to duplicate." );
		}

		$clone = $original->clone_with_fresh_ids( $ids );

		$location = self::locate( $this->roots, $node_id );

		if ( null === $location ) {
			throw new \RuntimeException( "Could not locate id {$node_id}." );
		}

		[$parent_id, $index] = $location;

		self::insert_node( $this->roots, $parent_id, $index + 1, $clone );

		return $clone->id;
	}

	/**
	 * Clones a subtree with fresh ids and inserts it at an arbitrary target
	 * location within the SAME tree — copy/paste within a document.
	 *
	 * @return int The new clone's root id.
	 */
	public function copy_paste( int $node_id, IdGenerator $ids, ?int $target_parent_id, int $target_index ): int {
		$original = $this->find( $node_id );

		if ( null === $original ) {
			throw new \RuntimeException( "No node with id {$node_id} to copy." );
		}

		$clone = $original->clone_with_fresh_ids( $ids );

		if ( ! self::insert_node( $this->roots, $target_parent_id, $target_index, $clone ) ) {
			throw new \RuntimeException( "No parent with id {$target_parent_id} to paste into." );
		}

		return $clone->id;
	}

	/**
	 * Clones a subtree from a DIFFERENT OracleTree, with fresh ids drawn from
	 * the SAME shared generator the source tree used — so the pasted node's
	 * id can never collide with anything already in either tree — and
	 * inserts it into this tree. Copy/paste from another document.
	 *
	 * @return int The new clone's root id.
	 */
	public function copy_from( self $source, int $source_node_id, IdGenerator $ids, ?int $target_parent_id, int $target_index ): int {
		$original = $source->find( $source_node_id );

		if ( null === $original ) {
			throw new \RuntimeException( "Source tree has no node with id {$source_node_id}." );
		}

		$clone = $original->clone_with_fresh_ids( $ids );

		if ( ! self::insert_node( $this->roots, $target_parent_id, $target_index, $clone ) ) {
			throw new \RuntimeException( "No parent with id {$target_parent_id} to paste into." );
		}

		return $clone->id;
	}

	/**
	 * Replaces a leaf node's translatable text in place. The id is
	 * unchanged, whether this is a one-word tweak or a full rewrite — both
	 * are "the same logical block, its content changed", the case the whole
	 * identity model exists to get right cheaply.
	 */
	public function edit_text( int $node_id, string $new_text ): void {
		$node = $this->find( $node_id );

		if ( null === $node ) {
			throw new \RuntimeException( "No node with id {$node_id} to edit." );
		}

		if ( ! $node->is_leaf() ) {
			throw new \RuntimeException( "Node {$node_id} is a container; it has no text to edit." );
		}

		$node->text = $new_text;
	}

	/**
	 * Changes a leaf node's block type (e.g. paragraph -> heading) in place.
	 * The id is unchanged: from the oracle's point of view this is still the
	 * same logical content the editor re-typed, even though a real
	 * key-matching strategy keyed on block name would see it as a different
	 * segment and (per the accepted design) is allowed to orphan it rather
	 * than guess.
	 */
	public function convert_type( int $node_id, string $new_block_name, array $new_attrs = array() ): void {
		$node = $this->find( $node_id );

		if ( null === $node ) {
			throw new \RuntimeException( "No node with id {$node_id} to convert." );
		}

		if ( ! $node->is_leaf() ) {
			throw new \RuntimeException( "Node {$node_id} is a container; type conversion is only modelled for leaves." );
		}

		$node->block_name = $new_block_name;
		$node->attrs       = $new_attrs;
	}

	/**
	 * Moves an EXISTING node (its id unchanged) to a new parent/position —
	 * nested movement in, out of, or across parents. Distinct from
	 * duplicate()/copy_paste(), which create a new id; this is the same
	 * logical block relocating.
	 */
	public function move( int $node_id, ?int $new_parent_id, int $new_index ): void {
		$node = self::remove_node( $this->roots, $node_id );

		if ( null === $node ) {
			throw new \RuntimeException( "No node with id {$node_id} to move." );
		}

		if ( ! self::insert_node( $this->roots, $new_parent_id, $new_index, $node ) ) {
			throw new \RuntimeException( "No parent with id {$new_parent_id} to move into." );
		}
	}

	// -- Internal tree surgery --

	/**
	 * @param OracleNode[] $siblings
	 */
	private static function remove_node( array &$siblings, int $id ): ?OracleNode {
		foreach ( $siblings as $i => $node ) {
			if ( $node->id === $id ) {
				$removed = $node;
				array_splice( $siblings, $i, 1 );

				return $removed;
			}
		}

		foreach ( $siblings as $node ) {
			$found = self::remove_node( $node->children, $id );

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * @param OracleNode[] $siblings
	 */
	private static function insert_node( array &$siblings, ?int $parent_id, int $index, OracleNode $new_node ): bool {
		if ( null === $parent_id ) {
			array_splice( $siblings, $index, 0, array( $new_node ) );

			return true;
		}

		foreach ( $siblings as $node ) {
			if ( $node->id === $parent_id ) {
				array_splice( $node->children, $index, 0, array( $new_node ) );

				return true;
			}
		}

		foreach ( $siblings as $node ) {
			if ( self::insert_node( $node->children, $parent_id, $index, $new_node ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param OracleNode[] $siblings
	 * @return OracleNode[]|null A COPY of the children list at $parent_id (or
	 *                            the root list), for reorder() to splice
	 *                            against before writing it back via
	 *                            write_siblings().
	 */
	private static function siblings_ref( array $siblings, ?int $parent_id ): ?array {
		if ( null === $parent_id ) {
			return $siblings;
		}

		foreach ( $siblings as $node ) {
			if ( $node->id === $parent_id ) {
				return $node->children;
			}
		}

		foreach ( $siblings as $node ) {
			$found = self::siblings_ref( $node->children, $parent_id );

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * @param OracleNode[] $siblings
	 * @param OracleNode[] $new_list
	 */
	private static function write_siblings( array &$siblings, ?int $parent_id, array $new_list ): void {
		if ( null === $parent_id ) {
			$siblings = $new_list;
			return;
		}

		foreach ( $siblings as $node ) {
			if ( $node->id === $parent_id ) {
				$node->children = $new_list;
				return;
			}

			self::write_siblings( $node->children, $parent_id, $new_list );
		}
	}

	/**
	 * @param OracleNode[] $siblings
	 * @return array{0: ?int, 1: int}|null [parent_id (null if root), index within its siblings]
	 */
	private static function locate( array $siblings, int $id, ?int $parent_id = null ): ?array {
		foreach ( $siblings as $index => $node ) {
			if ( $node->id === $id ) {
				return array( $parent_id, $index );
			}
		}

		foreach ( $siblings as $node ) {
			$found = self::locate( $node->children, $id, $node->id );

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}
}
