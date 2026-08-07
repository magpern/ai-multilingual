<?php
/**
 * Core video caption adapter (A.0).
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
 * Block-local video figcaption only.
 */
final class VideoAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/video';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return list<string>
	 */
	public function get_supported_fields(): array {
		return array( Contract::FIELD_CAPTION );
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

		return '' !== trim( $this->caption_source( $block ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return list<\AIMultilingual\Block\TranslatableField>
	 */
	public function extract_fields( array $block ): array {
		$source = $this->caption_source( $block );
		if ( '' === trim( $source ) ) {
			return array();
		}

		return array(
			new TranslatableField(
				Contract::FIELD_CAPTION,
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
		if ( Contract::FIELD_CAPTION !== $field_id ) {
			return $block;
		}

		return InnerHtmlReplacer::replace_tag_in_host_block( $block, 'figcaption', $translated_text );
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
	 * Reads a block-local source value.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function caption_source( array $block ): string {
		return InnerHtmlReplacer::first_tag_inner_html(
			(string) ( $block['innerHTML'] ?? '' ),
			'figcaption'
		);
	}
}
