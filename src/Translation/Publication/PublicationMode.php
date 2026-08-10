<?php
/**
 * Closed TI.7 auto-publication modes (ADR-0020).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Publication;

/**
 * Closed auto-publication mode vocabulary.
 */
final class PublicationMode {

	public const MANUAL          = 'manual';
	public const APPROVED_ONLY   = 'approved_only';
	public const CONTROLLED_AUTO = 'controlled_auto';

	/**
	 * Returns all closed vocabulary values.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::MANUAL,
			self::APPROVED_ONLY,
			self::CONTROLLED_AUTO,
		);
	}

	/**
	 * Whether the value is a valid mode.
	 *
	 * @param string $value Candidate mode.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::all(), true );
	}
}
