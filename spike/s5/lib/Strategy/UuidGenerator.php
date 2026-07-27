<?php
/**
 * Spike S5 — RFC 4122 version-4 UUID generator for Strategy F.
 *
 * THROWAWAY CODE. Branch spike/s5 only.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Spike\S5\Strategy;

final class UuidGenerator {

	public static function v4(): string {
		$bytes = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );

		$hex = bin2hex( $bytes );

		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $hex, 0, 8 ),
			substr( $hex, 8, 4 ),
			substr( $hex, 12, 4 ),
			substr( $hex, 16, 4 ),
			substr( $hex, 20, 12 )
		);
	}
}
