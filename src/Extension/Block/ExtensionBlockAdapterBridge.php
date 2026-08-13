<?php
/**
 * Internal bridge from public ExtensionBlockAdapter to Strategy F TranslatableBlockAdapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension\Block;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SanitizationSpec;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Block\TranslatableBlockAdapter;
use AIMultilingual\Block\TranslatableField;
use AIMultilingual\Block\ValidationResult;
use AIMultilingual\Translation\Store;

/**
 * Owns b: UUID identity, validation artifacts, and sanitization internals.
 */
final class ExtensionBlockAdapterBridge implements TranslatableBlockAdapter {

	/**
	 * Wraps one public block adapter for internal registration.
	 *
	 * @param ExtensionBlockAdapter $adapter Public adapter.
	 */
	public function __construct(
		private ExtensionBlockAdapter $adapter,
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_block_names(): array {
		return $this->adapter->get_block_names();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 */
	public function is_translatable_instance( array $block ): bool {
		return $this->adapter->is_translatable_instance( $block );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_fields(): array {
		return $this->adapter->get_supported_fields();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 */
	public function extract_fields( array $block ): array {
		$fields = array();
		foreach ( $this->adapter->get_supported_fields() as $field_id ) {
			$source = $this->adapter->extract_field( $block, $field_id );
			if ( null === $source || '' === trim( $source ) ) {
				continue;
			}
			$format   = $this->normalize_format( $this->adapter->get_text_format( $field_id ) );
			$fields[] = new TranslatableField( $field_id, $source, $format );
		}
		return $fields;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block           Parsed block from {@see parse_blocks()}.
	 * @param string               $field_id        Field identifier.
	 * @param string               $translated_text Translated field value.
	 */
	public function apply_translation( array $block, string $field_id, string $translated_text ): array {
		return $this->adapter->apply_field( $block, $field_id, $translated_text );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block from {@see parse_blocks()}.
	 */
	public function validate_block_structure( array $block ): ValidationResult {
		if ( ! $this->adapter->is_translatable_instance( $block ) ) {
			return ValidationResult::invalid( array( 'invalid_block_shape' ) );
		}
		foreach ( $this->adapter->get_supported_fields() as $field_id ) {
			$source = $this->adapter->extract_field( $block, $field_id );
			if ( null !== $source && '' !== trim( $source ) ) {
				return ValidationResult::valid();
			}
		}
		return ValidationResult::invalid( array( 'empty_source' ) );
	}

	/**
	 * {@inheritDoc}
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
	 * Normalizes declared text format to Store constants.
	 *
	 * @param string $format Declared format.
	 */
	private function normalize_format( string $format ): string {
		return 'html' === $format ? Store::FORMAT_HTML : Store::FORMAT_PLAIN;
	}
}
