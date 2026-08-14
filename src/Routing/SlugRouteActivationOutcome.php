<?php
/**
 * Activation verification outcome taxonomy (MSEO.2 §7–8).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * Result classification for prepared-route activation verification.
 */
final class SlugRouteActivationOutcome {

	public const ADMITTED            = 'admitted';
	public const SKIPPED_UNSUPPORTED = 'skipped_unsupported';
	public const SKIPPED_NOT_PUBLIC  = 'skipped_not_public';
	public const INVALID_DATA        = 'invalid_data';
	public const CONFLICT            = 'conflict';

	/**
	 * Whether the outcome blocks global ON.
	 *
	 * @param string $outcome Outcome constant.
	 */
	public static function is_blocking( string $outcome ): bool {
		return in_array(
			$outcome,
			array( self::INVALID_DATA, self::CONFLICT ),
			true
		);
	}
}
