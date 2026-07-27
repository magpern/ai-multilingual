<?php
/**
 * Strategy F heading block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block\Adapter;

use AIMultilingual\Block\InnerHtmlReplacer;

/**
 * Extracts translatable content from core/heading blocks.
 */
final class HeadingAdapter extends AbstractBlockAdapter {

	/**
	 * {@inheritDoc}
	 */
	protected function block_name(): string {
		return 'core/heading';
	}

	/**
	 * Replaces heading text while preserving the heading level wrapper.
	 *
	 * @param array<string, mixed> $block           Parsed block.
	 * @param string               $translated_text Translated source text.
	 */
	protected function apply_translated_source( array $block, string $translated_text ): string {
		$level = (int) ( is_array( $block['attrs'] ?? null ) ? ( $block['attrs']['level'] ?? 2 ) : 2 );
		$level = max( 1, min( 6, $level ) );
		$tag   = 'h' . $level;

		return InnerHtmlReplacer::replace_tag_content(
			(string) ( $block['innerHTML'] ?? '' ),
			$tag,
			$translated_text
		);
	}
}
