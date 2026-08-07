<?php
/**
 * Core details summary adapter (A.0).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\InnerHtmlReplacer;
use AIMultilingual\Block\TranslatableField;
use AIMultilingual\Block\ValidationResult;
use AIMultilingual\Translation\Store;

/**
 * Extracts and renders the block-local details summary only.
 */
final class DetailsAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/details';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return list<string>
	 */
	public function get_supported_fields(): array {
		return array( Contract::FIELD_SUMMARY );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	public function is_translatable_instance( array $block ): bool {
		if ( $this->block_name() !== (string) ( $block['blockName'] ?? '' ) ) {
			return false;
		}

		return '' !== trim( $this->summary_source( $block ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return list<\AIMultilingual\Block\TranslatableField>
	 */
	public function extract_fields( array $block ): array {
		$source = $this->summary_source( $block );
		if ( '' === trim( $source ) ) {
			return array();
		}

		return array(
			new TranslatableField(
				Contract::FIELD_SUMMARY,
				$source,
				Store::FORMAT_HTML
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $field_id        Field identifier.
	 * @param string               $translated_text Translated field value.
	 * @return array<string, mixed>
	 */
	public function apply_translation( array $block, string $field_id, string $translated_text ): array {
		if ( Contract::FIELD_SUMMARY !== $field_id ) {
			return $block;
		}

		return InnerHtmlReplacer::replace_tag_in_host_block( $block, 'summary', $translated_text );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	public function validate_block_structure( array $block ): ValidationResult {
		if ( ! $this->is_translatable_instance( $block ) ) {
			return ValidationResult::invalid( array( 'invalid_block_shape' ) );
		}

		return ValidationResult::valid();
	}

	/**
	 * Returns summary markup/text from host string slots.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function summary_source( array $block ): string {
		return InnerHtmlReplacer::first_tag_inner_html( $this->host_markup( $block ), 'summary' );
	}

	/**
	 * Concatenates host markup string slots (excludes nested child HTML).
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function host_markup( array $block ): string {
		$parts = $block['innerContent'] ?? null;
		if ( is_array( $parts ) ) {
			$html = '';
			foreach ( $parts as $part ) {
				if ( is_string( $part ) ) {
					$html .= $part;
				}
			}
			if ( '' !== $html ) {
				return $html;
			}
		}

		return (string) ( $block['innerHTML'] ?? '' );
	}
}
