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
	 * Initial proof and adapter allowlist.
	 *
	 * @var list<string>
	 */
	public const SUPPORTED_BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/button',
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
	 * Whether a parsed block instance should receive a persistent UUID.
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
		unset( $block_name );

		return null;
	}
}
