<?php
/**
 * Strategy F supported block registry.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Allowlist and eligibility policy for Strategy F block identity.
 */
final class BlockRegistry {

	/**
	 * Optional adapter registry for lookup delegation.
	 *
	 * @var AdapterRegistry|null
	 */
	private ?AdapterRegistry $adapters = null;

	/**
	 * Builds the block registry.
	 *
	 * @param AdapterRegistry|null $adapters Adapter registry for lookup.
	 */
	public function __construct( ?AdapterRegistry $adapters = null ) {
		$this->adapters = $adapters;
	}

	/**
	 * Initial proof and adapter allowlist.
	 *
	 * @var list<string>
	 */
	public const SUPPORTED_BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/button',
		'core/list-item',
		'core/preformatted',
		'core/verse',
		'core/code',
	);

	/**
	 * Dynamic blocks whose saved innerHTML is not authoritative.
	 *
	 * @var list<string>
	 */
	public const DYNAMIC_BLOCK_NAMES = array(
		'core/latest-posts',
		'core/block',
		'core/query',
		'core/post-title',
		'core/navigation',
		'core/template-part',
	);

	/**
	 * Structural containers that never own translation units (A.4).
	 *
	 * Traversal remains recursive via {@see BlockTreeWalker}; these names are
	 * transparent hosts for supported descendant leaves.
	 *
	 * @var list<string>
	 */
	public const STRUCTURAL_TRANSPARENT_BLOCKS = array(
		'core/group',
		'core/columns',
		'core/column',
		'core/list',
	);

	/**
	 * Child-traversal hosts that may wrap supported leaves without parent-field
	 * admission in A.4 (citation/summary/pullquote remain deferred).
	 *
	 * @var list<string>
	 */
	public const CHILD_TRAVERSAL_HOST_BLOCKS = array(
		'core/quote',
		'core/details',
		'core/cover',
		'core/media-text',
	);

	/**
	 * Whether a block type name is on the Strategy F allowlist.
	 *
	 * @param string $block_name Block type name.
	 */
	public function is_supported( string $block_name ): bool {
		return in_array( $block_name, self::SUPPORTED_BLOCKS, true );
	}

	/**
	 * Whether a block type is dynamic and therefore ineligible for UUID injection.
	 *
	 * @param string $block_name Block type name.
	 */
	public function is_dynamic( string $block_name ): bool {
		return in_array( $block_name, self::DYNAMIC_BLOCK_NAMES, true );
	}

	/**
	 * Whether a block type is a structural-transparent container (A.4).
	 *
	 * Structural containers are never translation units. Supported descendants
	 * remain independently eligible when they satisfy {@see is_eligible()}.
	 *
	 * @param string $block_name Block type name.
	 */
	public function is_structural_transparent( string $block_name ): bool {
		return in_array( $block_name, self::STRUCTURAL_TRANSPARENT_BLOCKS, true );
	}

	/**
	 * Whether a block type is a child-traversal host without A.4 parent fields.
	 *
	 * @param string $block_name Block type name.
	 */
	public function is_child_traversal_host( string $block_name ): bool {
		return in_array( $block_name, self::CHILD_TRAVERSAL_HOST_BLOCKS, true );
	}

	/**
	 * Whether a parsed block instance should receive a persistent UUID.
	 *
	 * Eligibility is leaf-local: ancestry does not suppress a supported leaf.
	 * Non-empty {@see innerBlocks} rejects the *current* node only (parents that
	 * own nested children are not auto-admitted). Structural/host containers are
	 * never eligible because they are not on {@see SUPPORTED_BLOCKS}.
	 *
	 * @param array<string, mixed> $block Parsed block array.
	 */
	public function is_eligible( array $block ): bool {
		if ( null === ( $block['blockName'] ?? null ) ) {
			return false;
		}

		$name = (string) $block['blockName'];

		if ( ! $this->is_supported( $name ) ) {
			return false;
		}

		if ( $this->is_dynamic( $name ) ) {
			return false;
		}

		// Leaf guard: do not globally delete this check. Having children does not
		// make a parent extractable; nested *descendant* leaves remain eligible
		// when visited independently by BlockTreeWalker.
		$inner = $block['innerBlocks'] ?? array();
		if ( is_array( $inner ) && array() !== $inner ) {
			return false;
		}

		if ( '' === trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a block type supports a given translatable field in this rollout.
	 *
	 * @param string $block_name Block type name.
	 * @param string $field      Field identifier.
	 */
	public function supports_field( string $block_name, string $field ): bool {
		return $this->is_supported( $block_name ) && Contract::is_supported_field( $field );
	}

	/**
	 * Returns supported field identifiers for a block type.
	 *
	 * @param string $block_name Block type name.
	 * @return list<string>
	 */
	public function get_supported_fields( string $block_name ): array {
		if ( ! $this->is_supported( $block_name ) ) {
			return array();
		}

		return Contract::SUPPORTED_FIELDS;
	}

	/**
	 * Returns the adapter for a block type, when registered.
	 *
	 * @param string $block_name Block type name.
	 */
	public function get_adapter( string $block_name ): ?TranslatableBlockAdapter {
		if ( null === $this->adapters ) {
			return null;
		}

		return $this->adapters->get( $block_name );
	}
}
