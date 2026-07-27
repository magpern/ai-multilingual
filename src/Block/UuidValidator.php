<?php
/**
 * Strategy F UUID format validation.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Validates UUID strings only — does not generate identifiers.
 */
final class UuidValidator {

	/**
	 * Whether a value is a valid RFC 4122 version-4 UUID string.
	 *
	 * @param mixed $value Candidate value.
	 */
	public static function is_valid( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		if ( strlen( $value ) > Contract::UUID_MAX_LENGTH ) {
			return false;
		}

		return 1 === preg_match( Contract::UUID_V4_PATTERN, $value );
	}

	/**
	 * Whether a value is a non-empty valid UUID.
	 *
	 * @param mixed $value Candidate value.
	 */
	public static function is_valid_non_empty( $value ): bool {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}

		return self::is_valid( $value );
	}

	/**
	 * Returns a normalized UUID string or null when invalid.
	 *
	 * @param mixed $value Candidate value.
	 */
	public static function normalize( $value ): ?string {
		if ( ! self::is_valid_non_empty( $value ) ) {
			return null;
		}

		return $value;
	}
}
