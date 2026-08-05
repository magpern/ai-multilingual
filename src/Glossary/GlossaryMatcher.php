<?php
/**
 * Literal Unicode whole-word glossary matching.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Glossary;

/**
 * Deterministic matcher: no stemming, morphology, or fuzzy match.
 */
final class GlossaryMatcher {

	/**
	 * Construct matcher.
	 *
	 * @param GlossaryNormalizer $normalizer Canonical normalizer.
	 */
	public function __construct(
		private readonly GlossaryNormalizer $normalizer
	) {
	}

	/**
	 * Find whole-word glossary matches in source text.
	 *
	 * @param string $source_text Source segment text.
	 * @param array  $terms       Active glossary rows for the language pair.
	 * @param string $text_format plain|html.
	 * @return list<GlossaryTermMatch>
	 */
	public function match( string $source_text, array $terms, string $text_format = 'plain' ): array {
		$scan = $this->normalizer->prepare_scan_text( $source_text, $text_format );
		if ( '' === $scan || array() === $terms ) {
			return array();
		}

		$scan_folded  = mb_strtolower( $scan, 'UTF-8' );
		$segment_norm = null;
		try {
			if ( $this->normalizer->supports_nfc() ) {
				$segment_norm = $this->normalizer->normalize_source( $scan );
			} else {
				$segment_norm = mb_strtolower( $scan, 'UTF-8' );
			}
		} catch ( \InvalidArgumentException | \RuntimeException $e ) {
			unset( $e );
			$segment_norm = mb_strtolower( $scan, 'UTF-8' );
		}

		$candidates = array();
		foreach ( $terms as $term ) {
			$norm = (string) $term->source_term_normalized;
			if ( '' === $norm ) {
				continue;
			}
			$candidates[] = array(
				'term' => $term,
				'norm' => $norm,
				'len'  => mb_strlen( $norm, 'UTF-8' ),
			);
		}

		usort(
			$candidates,
			static function ( array $a, array $b ): int {
				if ( $a['len'] !== $b['len'] ) {
					return $b['len'] <=> $a['len'];
				}
				$lex = $a['norm'] <=> $b['norm'];
				if ( 0 !== $lex ) {
					return $lex;
				}

				return (int) $a['term']->glossary_id <=> (int) $b['term']->glossary_id;
			}
		);

		$occupied = array();
		$matches  = array();
		$seen_ids = array();

		foreach ( $candidates as $candidate ) {
			$term = $candidate['term'];
			$id   = (int) $term->glossary_id;
			$norm = $candidate['norm'];
			$len  = $candidate['len'];

			if ( null !== $segment_norm && $segment_norm === $norm ) {
				if ( isset( $seen_ids[ $id ] ) ) {
					continue;
				}
				$seen_ids[ $id ] = true;
				$matches[]       = new GlossaryTermMatch(
					$id,
					(string) $term->source_term,
					(string) $term->target_term,
					$norm,
					GlossaryTermMatch::KIND_EXACT_SEGMENT,
					0,
					mb_strlen( $scan, 'UTF-8' ),
					(string) ( $term->context ?? '' )
				);
				continue;
			}

			$offset   = 0;
			$scan_len = mb_strlen( $scan_folded, 'UTF-8' );
			while ( $offset <= $scan_len - $len ) {
				$pos = mb_strpos( $scan_folded, $norm, $offset );
				if ( false === $pos ) {
					break;
				}

				if ( ! $this->has_word_boundaries( $scan_folded, $pos, $len ) ) {
					$offset = $pos + 1;
					continue;
				}

				$overlap = false;
				for ( $i = $pos; $i < $pos + $len; $i++ ) {
					if ( isset( $occupied[ $i ] ) ) {
						$overlap = true;
						break;
					}
				}
				if ( $overlap ) {
					$offset = $pos + 1;
					continue;
				}

				if ( isset( $seen_ids[ $id ] ) ) {
					// Keep first occurrence only for match set; still mark occupied for longest-wins.
					for ( $i = $pos; $i < $pos + $len; $i++ ) {
						$occupied[ $i ] = $id;
					}
					$offset = $pos + $len;
					continue;
				}

				$seen_ids[ $id ] = true;
				for ( $i = $pos; $i < $pos + $len; $i++ ) {
					$occupied[ $i ] = $id;
				}

				$matches[] = new GlossaryTermMatch(
					$id,
					(string) $term->source_term,
					(string) $term->target_term,
					$norm,
					GlossaryTermMatch::KIND_EMBEDDED,
					$pos,
					$len,
					(string) ( $term->context ?? '' )
				);
				$offset    = $pos + $len;
			}
		}

		usort(
			$matches,
			static function ( GlossaryTermMatch $a, GlossaryTermMatch $b ): int {
				if ( $a->char_offset !== $b->char_offset ) {
					return $a->char_offset <=> $b->char_offset;
				}

				return $a->glossary_id <=> $b->glossary_id;
			}
		);

		return $matches;
	}

	/**
	 * Whether a candidate span has Unicode letter/number boundaries.
	 *
	 * @param string $scan_folded Folded scan text.
	 * @param int    $pos         Start offset.
	 * @param int    $len         Match length.
	 */
	private function has_word_boundaries( string $scan_folded, int $pos, int $len ): bool {
		$before = $pos > 0 ? mb_substr( $scan_folded, $pos - 1, 1, 'UTF-8' ) : '';
		$after  = mb_substr( $scan_folded, $pos + $len, 1, 'UTF-8' );

		if ( '' !== $before && preg_match( '/[\p{L}\p{N}]/u', $before ) ) {
			return false;
		}
		if ( '' !== $after && preg_match( '/[\p{L}\p{N}]/u', $after ) ) {
			return false;
		}

		return true;
	}
}
