<?php
/**
 * Core pullquote adapter (A.0).
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
 * Pullquote body/citation when block-local; nested children stay separate.
 *
 * Classic leaf pullquotes own `<p>` body as {@see Contract::FIELD_CONTENT} and
 * `<cite>` as {@see Contract::FIELD_CITATION}. Nested-child pullquotes extract
 * citation only when present in host markup.
 */
final class PullquoteAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/pullquote';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return list<string>
	 */
	public function get_supported_fields(): array {
		return array(
			Contract::FIELD_CONTENT,
			Contract::FIELD_CITATION,
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

		if ( $this->has_children( $block ) ) {
			return '' !== trim( $this->citation_source( $block ) );
		}

		return '' !== trim( $this->body_source( $block ) )
			|| '' !== trim( $this->citation_source( $block ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return list<\AIMultilingual\Block\TranslatableField>
	 */
	public function extract_fields( array $block ): array {
		$fields = array();

		if ( ! $this->has_children( $block ) ) {
			$body = $this->body_source( $block );
			if ( '' !== trim( $body ) ) {
				$fields[] = new TranslatableField(
					Contract::FIELD_CONTENT,
					$body,
					Store::FORMAT_HTML
				);
			}
		}

		$citation = $this->citation_source( $block );
		if ( '' !== trim( $citation ) ) {
			$fields[] = new TranslatableField(
				Contract::FIELD_CITATION,
				$citation,
				Store::FORMAT_HTML
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
		if ( Contract::FIELD_CONTENT === $field_id && ! $this->has_children( $block ) ) {
			return InnerHtmlReplacer::replace_tag_in_host_block( $block, 'p', $translated_text );
		}

		if ( Contract::FIELD_CITATION === $field_id ) {
			return InnerHtmlReplacer::replace_tag_in_host_block( $block, 'cite', $translated_text );
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
	 * Reads a block-local source value.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function has_children( array $block ): bool {
		$inner = $block['innerBlocks'] ?? array();

		return is_array( $inner ) && array() !== $inner;
	}

	/**
	 * Reads a block-local source value.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function body_source( array $block ): string {
		return InnerHtmlReplacer::first_tag_inner_html( $this->host_markup( $block ), 'p' );
	}

	/**
	 * Reads a block-local source value.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 */
	private function citation_source( array $block ): string {
		return InnerHtmlReplacer::first_tag_inner_html( $this->host_markup( $block ), 'cite' );
	}

	/**
	 * Reads a block-local source value.
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
