<?php
/**
 * Shared Strategy F block adapter helpers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SanitizationSpec;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\TranslatableBlockAdapter;
use AIMultilingual\Block\TranslatableField;
use AIMultilingual\Block\ValidationResult;
use AIMultilingual\Translation\Store;

/**
 * Base implementation for static leaf block adapters.
 */
abstract class AbstractBlockAdapter implements TranslatableBlockAdapter {

	/**
	 * Block type name owned by this adapter.
	 */
	abstract protected function block_name(): string;

	/**
	 * {@inheritDoc}
	 */
	public function get_block_names(): array {
		return array( $this->block_name() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_fields(): array {
		return array( Contract::FIELD_CONTENT );
	}

	/**
	 * Whether a parsed block instance should participate in extraction.
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 */
	public function is_translatable_instance( array $block ): bool {
		if ( $this->block_name() !== (string) ( $block['blockName'] ?? '' ) ) {
			return false;
		}

		// Leaf-local guard (A.4): non-empty innerBlocks rejects this instance only.
		// Nested descendant leaves remain independently translatable when empty.
		$inner = $block['innerBlocks'] ?? array();

		return ! is_array( $inner ) || array() === $inner;
	}

	/**
	 * Extracts translatable fields from a parsed block.
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 * @return list<\AIMultilingual\Block\TranslatableField>
	 */
	public function extract_fields( array $block ): array {
		$source = $this->canonical_source_text( $block );

		if ( '' === trim( $source ) ) {
			return array();
		}

		return array(
			new TranslatableField(
				Contract::FIELD_CONTENT,
				$source,
				Store::FORMAT_HTML
			),
		);
	}

	/**
	 * Applies a translated value to a parsed block field.
	 *
	 * @param array<string, mixed> $block           Parsed block from {@see parse_blocks()}.
	 * @param string               $field_id        Field identifier.
	 * @param string               $translated_text Translated field value.
	 * @return array<string, mixed> Updated parsed block.
	 */
	public function apply_translation( array $block, string $field_id, string $translated_text ): array {
		if ( Contract::FIELD_CONTENT !== $field_id ) {
			return $block;
		}

		$block['innerHTML'] = $this->apply_translated_source( $block, $translated_text );

		if ( is_array( $block['innerContent'] ?? null ) && array() !== $block['innerContent'] ) {
			$block['innerContent'] = array( $block['innerHTML'] );
		}

		return $block;
	}

	/**
	 * Validates that a parsed block remains structurally sound.
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 */
	public function validate_block_structure( array $block ): ValidationResult {
		if ( ! $this->is_translatable_instance( $block ) ) {
			return ValidationResult::invalid( array( 'invalid_block_shape' ) );
		}

		if ( '' === trim( $this->canonical_source_text( $block ) ) ) {
			return ValidationResult::invalid( array( 'empty_source' ) );
		}

		return ValidationResult::valid();
	}

	/**
	 * Builds the segment key for a UUID and field owned by this adapter.
	 *
	 * @param string $uuid     Block UUID.
	 * @param string $field_id Field identifier.
	 */
	public function get_segment_key( string $uuid, string $field_id ): string {
		return SegmentKey::build( $uuid, $field_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_frontend_sanitization_requirements(): SanitizationSpec {
		return new SanitizationSpec();
	}

	/**
	 * Returns canonical source text for hashing and extraction.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	protected function canonical_source_text( array $block ): string {
		return (string) ( $block['innerHTML'] ?? '' );
	}

	/**
	 * Replaces translatable inner HTML while preserving wrapper markup.
	 *
	 * Rendering uses this in F6; F4 extraction does not call it.
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $translated_text Translated source text.
	 */
	protected function apply_translated_source( array $block, string $translated_text ): string {
		return $translated_text;
	}
}
