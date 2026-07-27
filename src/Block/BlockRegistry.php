<?php
/**
 * Strategy F supported block registry.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Allowlist of block types eligible for Strategy F identity (F1 scope).
 *
 * Extraction, injection, and rendering are implemented in later milestones.
 */
final class BlockRegistry {

	/**
	 * Initial F1 proof and adapter allowlist.
	 *
	 * @var list<string>
	 */
	public const SUPPORTED_BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/button',
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
	 * Whether a block instance is eligible for Strategy F identity.
	 *
	 * F1 uses the allowlist only; tree-shape rules arrive with F2/F4.
	 *
	 * @param array<string, mixed> $block Parsed block array.
	 */
	public function is_eligible( array $block ): bool {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

		return $this->is_supported( $name );
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
		unset( $block_name );

		return null;
	}
}
