<?php
/**
 * Strategy F paragraph block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\InnerHtmlReplacer;

/**
 * Extracts translatable content from core/paragraph blocks.
 */
final class ParagraphAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/paragraph';
	}

	/**
	 * Replaces paragraph text while preserving wrapper markup.
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $translated_text Translated source text.
	 */
	protected function apply_translated_source( array $block, string $translated_text ): string {
		return InnerHtmlReplacer::replace_tag_content(
			(string) ( $block['innerHTML'] ?? '' ),
			'p',
			$translated_text
		);
	}
}
