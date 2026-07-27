<?php
/**
 * Strategy F button block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\InnerHtmlReplacer;

/**
 * Extracts translatable content from core/button blocks.
 */
final class ButtonAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/button';
	}

	/**
	 * Replaces button label text while preserving anchor attributes and wrapper markup.
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $translated_text Translated source text.
	 */
	protected function apply_translated_source( array $block, string $translated_text ): string {
		return InnerHtmlReplacer::replace_button_label(
			(string) ( $block['innerHTML'] ?? '' ),
			$translated_text
		);
	}
}
