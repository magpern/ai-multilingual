<?php
/**
 * Extracted translatable field value object.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Immutable translatable field extracted from a parsed block.
 */
final class TranslatableField {

	/**
	 * Builds a translatable field descriptor.
	 *
	 * @param string $field_id    Field identifier (`content`, …).
	 * @param string $source_text Normalized source text input for hashing.
	 * @param string $text_format Source text encoding hint for future adapters.
	 */
	public function __construct(
		public readonly string $field_id,
		public readonly string $source_text,
		public readonly string $text_format = 'html',
	) {
	}
}
