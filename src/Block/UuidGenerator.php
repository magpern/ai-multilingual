<?php
/**
 * Strategy F UUID generation.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Generates RFC 4122 version-4 UUIDs for block identity.
 */
final class UuidGenerator {

	/**
	 * Creates a new RFC 4122 version-4 UUID string.
	 */
	public static function v4(): string {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );

		$hex = bin2hex( $bytes );

		$uuid = sprintf(
			'%s-%s-%s-%s-%s',
			substr( $hex, 0, 8 ),
			substr( $hex, 8, 4 ),
			substr( $hex, 12, 4 ),
			substr( $hex, 16, 4 ),
			substr( $hex, 20, 12 )
		);

		if ( ! UuidValidator::is_valid( $uuid ) ) {
			throw new \RuntimeException( 'Generated UUID failed validation.' );
		}

		return $uuid;
	}
}
