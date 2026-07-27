<?php
/**
 * Strategy F segment key construction and parsing.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Pure helpers for `b:<uuid>:<field>` segment keys.
 *
 * Construction and parsing only — no store integration in F1.
 */
final class SegmentKey {

	/**
	 * Builds a segment key from a UUID and field identifier.
	 *
	 * @param string $uuid  RFC 4122 version-4 UUID.
	 * @param string $field Supported field identifier.
	 * @throws \InvalidArgumentException When inputs fail contract validation.
	 */
	public static function build( string $uuid, string $field ): string {
		if ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
			throw new \InvalidArgumentException( 'Invalid UUID for segment key construction.' );
		}

		if ( ! Contract::is_supported_field( $field ) ) {
			throw new \InvalidArgumentException( 'Unsupported field for segment key construction.' );
		}

		return Contract::SEGMENT_KEY_PREFIX . ':' . $uuid . ':' . $field;
	}

	/**
	 * Parses a segment key into its UUID and field components.
	 *
	 * @param string $segment_key Segment key string.
	 * @return array{uuid: string, field: string}|null Null when the key is malformed.
	 */
	public static function parse( string $segment_key ): ?array {
		if ( ! self::is_valid_format( $segment_key ) ) {
			return null;
		}

		$parts = explode( ':', $segment_key, 3 );

		return array(
			'uuid'  => $parts[1],
			'field' => $parts[2],
		);
	}

	/**
	 * Whether a string matches the Strategy F segment key grammar.
	 *
	 * @param string $segment_key Segment key string.
	 */
	public static function is_valid_format( string $segment_key ): bool {
		$parsed = self::parse_loose( $segment_key );

		if ( null === $parsed ) {
			return false;
		}

		return UuidValidator::is_valid_non_empty( $parsed['uuid'] )
			&& Contract::is_supported_field( $parsed['field'] );
	}

	/**
	 * Attempts a structural parse without UUID field validation.
	 *
	 * @param string $segment_key Segment key string.
	 * @return array{uuid: string, field: string}|null
	 */
	private static function parse_loose( string $segment_key ): ?array {
		if ( '' === $segment_key ) {
			return null;
		}

		$parts = explode( ':', $segment_key, 3 );

		if ( 3 !== count( $parts ) ) {
			return null;
		}

		if ( Contract::SEGMENT_KEY_PREFIX !== $parts[0] ) {
			return null;
		}

		if ( '' === $parts[1] || '' === $parts[2] ) {
			return null;
		}

		return array(
			'uuid'  => $parts[1],
			'field' => $parts[2],
		);
	}
}
