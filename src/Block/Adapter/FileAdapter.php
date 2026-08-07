<?php
/**
 * Core file label adapter (A.0).
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
 * Block-owned fileName / downloadButtonText only.
 *
 * Does not own Media Library attachment metadata.
 */
final class FileAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/file';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return list<string>
	 */
	public function get_supported_fields(): array {
		return array(
			Contract::FIELD_FILE_NAME,
			Contract::FIELD_DOWNLOAD_BUTTON_TEXT,
		);
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

		$inner = $block['innerBlocks'] ?? array();
		if ( is_array( $inner ) && array() !== $inner ) {
			return false;
		}

		return '' !== trim( $this->file_name_source( $block ) )
			|| '' !== trim( $this->download_button_source( $block ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return list<\AIMultilingual\Block\TranslatableField>
	 */
	public function extract_fields( array $block ): array {
		$fields = array();

		$file_name = $this->file_name_source( $block );
		if ( '' !== trim( $file_name ) ) {
			$fields[] = new TranslatableField(
				Contract::FIELD_FILE_NAME,
				$file_name,
				Store::FORMAT_PLAIN
			);
		}

		$button = $this->download_button_source( $block );
		if ( '' !== trim( $button ) ) {
			$fields[] = new TranslatableField(
				Contract::FIELD_DOWNLOAD_BUTTON_TEXT,
				$button,
				Store::FORMAT_PLAIN
			);
		}

		return $fields;
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
		if ( ! is_array( $block['attrs'] ?? null ) ) {
			$block['attrs'] = array();
		}

		if ( Contract::FIELD_FILE_NAME === $field_id ) {
			$block['attrs']['fileName'] = $translated_text;
			$html                       = InnerHtmlReplacer::replace_file_name_label(
				(string) ( $block['innerHTML'] ?? '' ),
				$translated_text
			);
			$block['innerHTML']         = $html;
			if ( is_array( $block['innerContent'] ?? null ) && array() !== $block['innerContent'] ) {
				$block['innerContent'] = array( $html );
			}

			return $block;
		}

		if ( Contract::FIELD_DOWNLOAD_BUTTON_TEXT === $field_id ) {
			$block['attrs']['downloadButtonText'] = $translated_text;
			$html                                 = InnerHtmlReplacer::replace_file_download_label(
				(string) ( $block['innerHTML'] ?? '' ),
				$translated_text
			);
			$block['innerHTML']                   = $html;
			if ( is_array( $block['innerContent'] ?? null ) && array() !== $block['innerContent'] ) {
				$block['innerContent'] = array( $html );
			}

			return $block;
		}

		return $block;
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
	 * Resolves the file name from attrs or HTML.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function file_name_source( array $block ): string {
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		if ( isset( $attrs['fileName'] ) && '' !== trim( (string) $attrs['fileName'] ) ) {
			return (string) $attrs['fileName'];
		}

		return InnerHtmlReplacer::file_name_label( (string) ( $block['innerHTML'] ?? '' ) );
	}

	/**
	 * Resolves the download button label from attrs or HTML.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function download_button_source( array $block ): string {
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		if ( isset( $attrs['downloadButtonText'] ) && '' !== trim( (string) $attrs['downloadButtonText'] ) ) {
			return (string) $attrs['downloadButtonText'];
		}

		return InnerHtmlReplacer::file_download_label( (string) ( $block['innerHTML'] ?? '' ) );
	}
}
