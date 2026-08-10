<?php
/**
 * Corpus glossary fragment builder mirroring GlossaryService bounds.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Builds deterministic glossary fragments from corpus glossary.json fixtures.
 */
final class FixtureGlossaryFragmentBuilder {

	public const FRAGMENT_MAX_TERMS         = 40;
	public const FRAGMENT_MAX_CHARS         = 4000;
	public const FRAGMENT_TRUNCATION_MARKER = '# glossary_truncated';

	/**
	 * Builds a bounded fragment for source text using corpus glossary terms.
	 *
	 * @param string              $source_text Source segment text.
	 * @param array<string,mixed> $glossary    Corpus glossary fixture.
	 * @return string
	 */
	public function build( string $source_text, array $glossary ): string {
		$matches = $this->match_terms( $source_text, $glossary );
		if ( array() === $matches ) {
			return '';
		}

		usort(
			$matches,
			static function ( array $a, array $b ): int {
				if ( $a['length'] !== $b['length'] ) {
					return $b['length'] <=> $a['length'];
				}
				if ( $a['offset'] !== $b['offset'] ) {
					return $a['offset'] <=> $b['offset'];
				}
				return strcmp( $a['source'], $b['source'] );
			}
		);

		$lines      = array();
		$char_count = 0;
		$truncated  = false;

		foreach ( $matches as $match ) {
			if ( count( $lines ) >= self::FRAGMENT_MAX_TERMS ) {
				$truncated = true;
				break;
			}
			$source = str_replace( array( "\r", "\n" ), ' ', $match['source'] );
			$target = str_replace( array( "\r", "\n" ), ' ', $match['target'] );
			$line   = $source . ' => ' . $target;
			$next   = $char_count + strlen( $line ) + ( array() === $lines ? 0 : 1 );
			if ( $next > self::FRAGMENT_MAX_CHARS ) {
				$truncated = true;
				break;
			}
			$lines[]    = $line;
			$char_count = $next;
		}

		if ( $truncated ) {
			$marker_len = strlen( self::FRAGMENT_TRUNCATION_MARKER ) + 1;
			while ( array() !== $lines && $char_count + $marker_len > self::FRAGMENT_MAX_CHARS ) {
				$removed     = array_pop( $lines );
				$char_count -= strlen( (string) $removed ) + ( array() === $lines ? 0 : 1 );
			}
			$lines[] = self::FRAGMENT_TRUNCATION_MARKER;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Finds glossary terms present in source text.
	 *
	 * @param string              $source_text Source segment.
	 * @param array<string,mixed> $glossary    Corpus glossary fixture.
	 * @return list<array{source: string, target: string, length: int, offset: int}>
	 */
	private function match_terms( string $source_text, array $glossary ): array {
		$matches = array();
		$seen    = array();

		foreach ( (array) ( $glossary['terms'] ?? array() ) as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}
			$source = (string) ( $term['source'] ?? '' );
			$target = (string) ( $term['target'] ?? '' );
			if ( '' === $source || '' === $target ) {
				continue;
			}
			$key = mb_strtolower( $source, 'UTF-8' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$offset = $this->find_term_offset( $source_text, $source );
			if ( null === $offset ) {
				continue;
			}
			$seen[ $key ] = true;
			$matches[]    = array(
				'source' => $source,
				'target' => $target,
				'length' => mb_strlen( $source, 'UTF-8' ),
				'offset' => $offset,
			);
		}

		return $matches;
	}

	/**
	 * Whole-word-ish case-insensitive match; returns byte offset of first hit.
	 */
	private function find_term_offset( string $haystack, string $needle ): ?int {
		if ( '' === $needle ) {
			return null;
		}
		$pattern = '/(?<!\w)' . preg_quote( $needle, '/' ) . '(?!\w)/iu';
		if ( 1 !== preg_match( $pattern, $haystack, $m, PREG_OFFSET_CAPTURE ) ) {
			// Fallback: plain case-insensitive substring for punctuation-adjacent tokens.
			$pos = mb_stripos( $haystack, $needle, 0, 'UTF-8' );
			return false === $pos ? null : $pos;
		}
		return (int) $m[0][1];
	}
}
