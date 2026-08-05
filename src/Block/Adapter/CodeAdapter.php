<?php
/**
 * Strategy F code block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\InnerHtmlReplacer;

/**
 * Extracts translatable content from core/code blocks.
 */
final class CodeAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/code';
	}

	/**
	 * Replaces code text while preserving pre/code wrappers.
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $translated_text Translated source text.
	 */
	protected function apply_translated_source( array $block, string $translated_text ): string {
		return InnerHtmlReplacer::replace_tag_content(
			(string) ( $block['innerHTML'] ?? '' ),
			'code',
			$translated_text
		);
	}
}
