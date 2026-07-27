<?php
/**
 * Strategy F block source normalization.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Translation\Store;

/**
 * Deterministic normalization pipeline for block adapter source text.
 *
 * All block extraction and hashing delegates here so rules stay in one place.
 *
 * Pipeline (HTML block fields such as innerHTML):
 * - Line endings: CR (`\\r`) and CRLF (`\\r\\n`) normalize to LF (`\\n`).
 * - HTML entities: not decoded; literal entity sequences in source HTML are
 *   preserved because decoding would change hash meaning for authored markup.
 * - Whitespace: runs are never collapsed for HTML; inter-tag spacing is
 *   significant. Leading and trailing whitespace on the whole fragment is
 *   trimmed after line-ending and NBSP canonicalization.
 * - Non-breaking spaces: `&nbsp;`, `&#160;`, `&#xA0;`, and U+00A0 bytes
 *   canonicalize to the U+00A0 codepoint (not converted to ordinary spaces).
 *
 * Plain-text fields (future adapters) use {@see Store::normalize()} plain rules:
 * line endings and NBSP collapse, whitespace runs collapse to one space, trim.
 */
final class SourceNormalizer {

	/**
	 * Normalizes source text for deterministic comparison and hashing.
	 *
	 * @param string $text   Raw source text from an adapter.
	 * @param string $format One of the {@see Store::FORMAT_*} constants.
	 */
	public static function normalize( string $text, string $format = Store::FORMAT_HTML ): string {
		return Store::normalize( $text, $format );
	}

	/**
	 * Computes the source hash for normalized block source text.
	 *
	 * @param string $text   Raw source text from an adapter.
	 * @param string $format One of the {@see Store::FORMAT_*} constants.
	 */
	public static function source_hash( string $text, string $format = Store::FORMAT_HTML ): string {
		return Store::source_hash( $text, $format );
	}
}
