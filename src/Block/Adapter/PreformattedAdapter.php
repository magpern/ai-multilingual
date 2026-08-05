<?php
/**
 * Strategy F preformatted block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\InnerHtmlReplacer;

/**
 * Extracts translatable content from core/preformatted blocks.
 */
final class PreformattedAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/preformatted';
	}

	/**
	 * Replaces preformatted text while preserving the pre wrapper.
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
