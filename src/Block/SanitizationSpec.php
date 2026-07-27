<?php
/**
 * Frontend sanitization requirements for a block adapter.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Declares how frontend output should strip leaked Strategy F metadata.
 *
 * Sanitization must be DOM-aware or block-specific — never broad regex over
 * arbitrary HTML (Strategy F production plan §12).
 */
final class SanitizationSpec {

	/**
	 * Builds a sanitization specification.
	 *
	 * @param string[] $strip_data_attributes Data attribute names to remove from rendered HTML.
	 * @param string[] $strip_block_names     Block names requiring sanitizer attention.
	 */
	public function __construct(
		public readonly array $strip_data_attributes = array(),
		public readonly array $strip_block_names = array(),
	) {
	}
}
