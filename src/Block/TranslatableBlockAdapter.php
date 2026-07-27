<?php
/**
 * Strategy F block adapter contract.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Per-block translation contract for Strategy F.
 *
 * Adapters declare supported block names, extract translatable fields, apply
 * translated values back to parsed block structures, and validate structural
 * integrity. Production implementations ship in F4; F1 defines the contract only.
 */
interface TranslatableBlockAdapter {

	/**
	 * Block type names handled by this adapter.
	 *
	 * @return list<string>
	 */
	public function get_block_names(): array;

	/**
	 * Whether a parsed block instance should participate in translation.
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 */
	public function is_translatable_instance( array $block ): bool;

	/**
	 * Field identifiers this adapter can translate.
	 *
	 * @return list<string>
	 */
	public function get_supported_fields(): array;

	/**
	 * Extracts translatable fields from a parsed block.
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 * @return list<TranslatableField>
	 */
	public function extract_fields( array $block ): array;

	/**
	 * Applies a translated value to a parsed block field.
	 *
	 * @param array<string, mixed> $block           Parsed block from {@see parse_blocks()}.
	 * @param string               $field_id        Field identifier.
	 * @param string               $translated_text Translated field value.
	 * @return array<string, mixed> Updated parsed block.
	 */
	public function apply_translation( array $block, string $field_id, string $translated_text ): array;

	/**
	 * Validates that a parsed block remains structurally sound.
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 */
	public function validate_block_structure( array $block ): ValidationResult;

	/**
	 * Builds the segment key for a UUID and field owned by this adapter.
	 *
	 * @param string $uuid     Block UUID.
	 * @param string $field_id Field identifier.
	 */
	public function get_segment_key( string $uuid, string $field_id ): string;

	/**
	 * Declares frontend sanitization requirements for rendered output.
	 */
	public function get_frontend_sanitization_requirements(): SanitizationSpec;
}
