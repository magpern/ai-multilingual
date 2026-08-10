<?php
/**
 * Extracts structural constraints from segment source text (F11 WP4 / TI.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use AIMultilingual\Translation\Store;

/**
 * Analyzes source text for placeholders, HTML tags, numbers, and absolute URLs.
 *
 * Feeds ResponseValidator (provider pipeline) — not QAEngine.
 */
final class SegmentConstraintAnalyzer {

	/**
	 * Dangerous HTML tags that must not be invented in machine output (TI.1 TS5).
	 *
	 * @var list<string>
	 */
	public const DANGEROUS_TAGS = array( 'script', 'iframe', 'object', 'embed' );

	/**
	 * Analyzes source text for structural tokens that must be preserved.
	 *
	 * @param string $source_text Source text.
	 * @param string $text_format Store text format.
	 * @return array{
	 *     placeholders: list<string>,
	 *     html_tags: list<string>,
	 *     numbers: list<string>,
	 *     urls: list<string>,
	 *     constraints: list<string>
	 * }
	 */
	public function analyze( string $source_text, string $text_format = Store::FORMAT_PLAIN ): array {
		$placeholders = $this->extract_placeholders( $source_text );
		$html_tags    = Store::FORMAT_HTML === $text_format
			? $this->extract_html_tags( $source_text )
			: array();
		$numbers      = $this->extract_numbers( $source_text );
		$urls         = $this->extract_absolute_urls( $source_text );

		$constraints = array( 'non_empty' );
		if ( array() !== $placeholders ) {
			$constraints[] = 'placeholders';
		}
		if ( array() !== $html_tags ) {
			$constraints[] = 'html';
		}
		if ( array() !== $numbers ) {
			$constraints[] = 'numbers';
		}
		if ( array() !== $urls ) {
			$constraints[] = 'urls';
		}

		return array(
			'placeholders' => $placeholders,
			'html_tags'    => $html_tags,
			'numbers'      => $numbers,
			'urls'         => $urls,
			'constraints'  => $constraints,
		);
	}

	/**
	 * Extracts placeholder tokens ({name}, %s, %1$s, {{mustache}}, [shortcode]).
	 *
	 * @param string $text Source text.
	 * @return list<string>
	 */
	public function extract_placeholders( string $text ): array {
		$found = array();

		if ( preg_match_all( '/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', $text, $m ) ) {
			$found = array_merge( $found, $m[0] );
		}
		if ( preg_match_all( '/\{\{[^{}]+\}\}/', $text, $m ) ) {
			$found = array_merge( $found, $m[0] );
		}
		if ( preg_match_all( '/%(?:\d+\$)?[sd]/', $text, $m ) ) {
			$found = array_merge( $found, $m[0] );
		}
		if ( preg_match_all( '/\[[a-zA-Z][^\]]*\]/', $text, $m ) ) {
			$found = array_merge( $found, $m[0] );
		}

		return array_values( array_unique( $found ) );
	}

	/**
	 * Extracts HTML tag names (lowercase, opening tags only).
	 *
	 * @param string $text HTML source.
	 * @return list<string>
	 */
	public function extract_html_tags( string $text ): array {
		if ( ! preg_match_all( '/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/', $text, $m ) ) {
			return array();
		}

		$tags = array_map( 'strtolower', $m[1] );
		sort( $tags );

		return array_values( array_unique( $tags ) );
	}

	/**
	 * Extracts standalone numbers for preservation checks.
	 *
	 * @param string $text Source text.
	 * @return list<string>
	 */
	public function extract_numbers( string $text ): array {
		if ( ! preg_match_all( '/\d+(?:[.,]\d+)?/', $text, $m ) ) {
			return array();
		}

		return array_values( array_unique( $m[0] ) );
	}

	/**
	 * Extracts absolute http(s) URLs for inventory preservation (TI.1 TS6).
	 *
	 * @param string $text Source text.
	 * @return list<string>
	 */
	public function extract_absolute_urls( string $text ): array {
		if ( ! preg_match_all( '#https?://[^\s<>"\']+#i', $text, $m ) ) {
			return array();
		}

		$urls = array_map(
			static function ( string $url ): string {
				return rtrim( $url, '.,);]' );
			},
			$m[0]
		);

		return array_values( array_unique( $urls ) );
	}
}
