<?php
/**
 * Narrow public Gutenberg block adapter contract (Extension API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension\Block;

/**
 * Public block adapter — explicit fields only; no Strategy F internals exposed.
 */
interface ExtensionBlockAdapter {

	/**
	 * Block type names handled by this adapter.
	 *
	 * @return list<string>
	 */
	public function get_block_names(): array;

	/**
	 * Field identifiers this adapter can translate.
	 *
	 * @return list<string>
	 */
	public function get_supported_fields(): array;

	/**
	 * Whether a parsed block instance should participate in translation.
	 *
	 * @param array<string, mixed> $block Parsed block from parse_blocks().
	 */
	public function is_translatable_instance( array $block ): bool;

	/**
	 * Extracts one field's source text from a parsed block.
	 *
	 * @param array<string, mixed> $block    Parsed block.
	 * @param string               $field_id Field identifier.
	 */
	public function extract_field( array $block, string $field_id ): ?string;

	/**
	 * Applies a translated value to a parsed block field.
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $field_id        Field identifier.
	 * @param string               $translated_text Translated value.
	 * @return array<string, mixed> Updated parsed block.
	 */
	public function apply_field( array $block, string $field_id, string $translated_text ): array;

	/**
	 * Text format for a field (plain|html).
	 *
	 * @param string $field_id Field identifier.
	 */
	public function get_text_format( string $field_id ): string;
}
