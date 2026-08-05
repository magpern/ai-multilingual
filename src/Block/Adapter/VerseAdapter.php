<?php
/**
 * Strategy F verse block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\InnerHtmlReplacer;

/**
 * Extracts translatable content from core/verse blocks.
 */
final class VerseAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/verse';
	}

	/**
	 * Replaces verse text while preserving the pre wrapper.
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $translated_text Translated source text.
	 */
	protected function apply_translated_source( array $block, string $translated_text ): string {
		return InnerHtmlReplacer::replace_tag_content(
			(string) ( $block['innerHTML'] ?? '' ),
			'pre',
			$translated_text
		);
	}
}
