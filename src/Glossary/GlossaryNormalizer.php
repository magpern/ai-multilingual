<?php
/**
 * Canonical glossary source-term normalization (ADR-0014).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Glossary;

use Normalizer;

/**
 * Single normalizer for uniqueness, exact match, and whole-word matching.
 */
final class GlossaryNormalizer {

	public const MAX_NORMALIZED_LENGTH = 191;

	/**
	 * Whether NFC via ext-intl is available.
	 */
	public function supports_nfc(): bool {
		return class_exists( Normalizer::class );
	}

	/**
	 * Normalize a source term for identity and matching.
	 *
	 * @param string $term Raw source term.
	 * @return string Canonical source form.
	 * @throws \RuntimeException When ext-intl is unavailable for writes.
	 * @throws \InvalidArgumentException When the term is empty or too long after normalize.
	 */
	public function normalize_source( string $term ): string {
		if ( ! $this->supports_nfc() ) {
			throw new \RuntimeException( 'ext-intl Normalizer is required for glossary writes.' );
		}

		$normalized = Normalizer::normalize( $term, Normalizer::FORM_C );
		if ( false === $normalized ) {
			throw new \InvalidArgumentException( 'glossary_invalid_unicode' );
		}

		return $this->fold_source( $normalized );
	}

	/**
	 * Soft-trim target term without case-folding or destructive changes.
	 *
	 * @param string $term Raw target term.
	 * @return string Prepared target.
	 * @throws \InvalidArgumentException When empty or over 512 chars.
	 */
	public function prepare_target( string $term ): string {
		$trimmed = trim( $term );
		if ( '' === $trimmed ) {
			throw new \InvalidArgumentException( 'glossary_empty_target' );
		}
		if ( mb_strlen( $trimmed, 'UTF-8' ) > 512 ) {
			throw new \InvalidArgumentException( 'glossary_target_too_long' );
		}

		return $trimmed;
	}

	/**
	 * Build a scan string for matching (HTML stripped, whitespace collapsed).
	 *
	 * @param string $text        Source text.
	 * @param string $text_format plain|html.
	 */
	public function prepare_scan_text( string $text, string $text_format = 'plain' ): string {
		$scan = 'html' === $text_format ? wp_strip_all_tags( $text ) : $text;
		if ( $this->supports_nfc() ) {
			$nfc = Normalizer::normalize( $scan, Normalizer::FORM_C );
			if ( false !== $nfc ) {
				$scan = $nfc;
			}
		}
		$scan = preg_replace( '/\s+/u', ' ', $scan ) ?? '';

		return trim( $scan );
	}

	/**
	 * Case-fold and whitespace-collapse a Unicode string (accents preserved).
	 *
	 * @param string $value NFC (or best-effort) input.
	 * @throws \InvalidArgumentException When empty or too long.
	 */
	private function fold_source( string $value ): string {
		$normalized = preg_replace( '/\s+/u', ' ', $value ) ?? '';
		$normalized = trim( $normalized );
		$normalized = mb_strtolower( $normalized, 'UTF-8' );

		if ( '' === $normalized ) {
			throw new \InvalidArgumentException( 'glossary_empty_term' );
		}

		if ( mb_strlen( $normalized, 'UTF-8' ) > self::MAX_NORMALIZED_LENGTH ) {
			throw new \InvalidArgumentException( 'glossary_term_too_long' );
		}

		return $normalized;
	}
}
