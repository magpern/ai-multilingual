<?php
/**
 * Locale-tolerant digit signature helpers for QD9 (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

/**
 * Normalizes number tokens so legitimate SV localization is not treated as corruption.
 */
final class NumberNormalizer {

	/**
	 * Same extraction pattern as SegmentConstraintAnalyzer::extract_numbers().
	 */
	private const NUMBER_PATTERN = '/\d+(?:[.,]\d+)?/';

	/**
	 * Strips spaces and unifies decimal/thousands separators for digit-sequence comparison.
	 *
	 * @param string $raw Matched number token (e.g. "1,5", "1.000", "10.00").
	 */
	public static function normalize_digits( string $raw ): string {
		$s = preg_replace( '/\s+/u', '', $raw );
		if ( ! is_string( $s ) || '' === $s ) {
			return '';
		}

		if ( str_contains( $s, '.' ) && str_contains( $s, ',' ) ) {
			$last_dot   = (int) strrpos( $s, '.' );
			$last_comma = (int) strrpos( $s, ',' );
			$dec_pos    = max( $last_dot, $last_comma );
			$int_part   = str_replace( array( '.', ',' ), '', substr( $s, 0, $dec_pos ) );
			$frac       = substr( $s, $dec_pos + 1 );

			return $int_part . '.' . $frac;
		}

		if ( preg_match( '/^(\d+)[.,](\d+)$/', $s, $m ) ) {
			$left  = $m[1];
			$right = $m[2];
			// Exactly three fractional/group digits ⇒ thousands grouping (1,000 / 1.000).
			if ( 3 === strlen( $right ) ) {
				return $left . $right;
			}

			return $left . '.' . $right;
		}

		return $s;
	}

	/**
	 * Normalized digit signatures present in text.
	 *
	 * Collapses spaces between digit groups before extraction so "1 000" aligns with "1,000".
	 *
	 * @param string $text Source or target text.
	 * @return list<string>
	 */
	public static function digit_signatures( string $text ): array {
		$prepared = preg_replace( '/(?<=\d)\s+(?=\d)/u', '', $text );
		if ( ! is_string( $prepared ) ) {
			$prepared = $text;
		}

		if ( ! preg_match_all( self::NUMBER_PATTERN, $prepared, $m ) ) {
			return array();
		}

		$sigs = array();
		foreach ( $m[0] as $raw ) {
			$norm = self::normalize_digits( (string) $raw );
			if ( '' !== $norm ) {
				$sigs[] = $norm;
			}
		}

		return array_values( array_unique( $sigs ) );
	}

	/**
	 * Source signatures absent from the target (true number corruption).
	 *
	 * @param string $source Source text.
	 * @param string $target Target text.
	 * @return list<string>
	 */
	public static function missing_signatures( string $source, string $target ): array {
		$target_set = array_fill_keys( self::digit_signatures( $target ), true );
		$missing    = array();

		foreach ( self::digit_signatures( $source ) as $sig ) {
			if ( ! isset( $target_set[ $sig ] ) ) {
				$missing[] = $sig;
			}
		}

		return $missing;
	}
}
