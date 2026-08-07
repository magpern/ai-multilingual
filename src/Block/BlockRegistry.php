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
	 * Adapter allowlist (F14 leaves + A.0 admissions).
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
		'core/quote',
		'core/details',
		'core/pullquote',
		'core/image',
		'core/file',
		'core/audio',
		'core/video',
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
	 * Child-traversal hosts that may wrap supported leaves.
	 *
	 * A.0 may also admit parent-owned fields on quote/details/pullquote via
	 * dedicated adapters; cover/media-text remain child-only.
	 *
	 * @var list<string>
	 */
	public const CHILD_TRAVERSAL_HOST_BLOCKS = array(
		'core/quote',
		'core/details',
		'core/cover',
		'core/media-text',
		'core/pullquote',
	);

	/**
	 * Supported blocks that may receive a UUID while retaining innerBlocks.
	 *
	 * Parent-field hosts extract only explicit parent fields (citation/summary);
	 * nested children remain independently addressed.
	 *
	 * @var list<string>
	 */
	public const PARENT_FIELD_HOST_BLOCKS = array(
		'core/quote',
		'core/details',
		'core/pullquote',
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
	 * Whether a block type is a child-traversal host.
	 *
	 * @param string $block_name Block type name.
	 */
	public function is_child_traversal_host( string $block_name ): bool {
		return in_array( $block_name, self::CHILD_TRAVERSAL_HOST_BLOCKS, true );
	}

	/**
	 * Whether a supported block may own parent fields while having children.
	 *
	 * @param string $block_name Block type name.
	 */
	public function is_parent_field_host( string $block_name ): bool {
		return in_array( $block_name, self::PARENT_FIELD_HOST_BLOCKS, true );
	}

	/**
	 * Whether a parsed block instance should receive a persistent UUID.
	 *
	 * Eligibility is leaf-local for ordinary leaves: ancestry does not suppress a
	 * supported leaf. Non-empty {@see innerBlocks} rejects ordinary leaves.
	 * Parent-field hosts (A.0) may remain eligible with children when their
	 * adapter reports a translatable parent field.
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

		$inner         = $block['innerBlocks'] ?? array();
		$has_children  = is_array( $inner ) && array() !== $inner;
		$parent_fields = $this->is_parent_field_host( $name );

		if ( $has_children && ! $parent_fields ) {
			return false;
		}

		if ( $parent_fields ) {
			$adapter = $this->get_adapter( $name );
			if ( null === $adapter || ! $adapter->is_translatable_instance( $block ) ) {
				return false;
			}

			return true;
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
		return in_array( $field, $this->get_supported_fields( $block_name ), true );
	}

	/**
	 * Returns supported field identifiers for a block type.
	 *
	 * Prefers the adapter allowlist when an adapter registry is injected.
	 *
	 * @param string $block_name Block type name.
	 * @return list<string>
	 */
	public function get_supported_fields( string $block_name ): array {
		if ( ! $this->is_supported( $block_name ) ) {
			return array();
		}

		$adapter = $this->get_adapter( $block_name );
		if ( null !== $adapter ) {
			return $adapter->get_supported_fields();
		}

		return array( Contract::FIELD_CONTENT );
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
