<?php
/**
 * Strategy F list-item block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\InnerHtmlReplacer;

/**
 * Extracts translatable content from core/list-item blocks.
 */
final class ListItemAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/list-item';
	}

	/**
	 * Replaces list-item text while preserving the li wrapper.
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $translated_text Translated source text.
	 */
	protected function apply_translated_source( array $block, string $translated_text ): string {
		return InnerHtmlReplacer::replace_tag_content(
			(string) ( $block['innerHTML'] ?? '' ),
			'li',
			$translated_text
		);
	}
}
