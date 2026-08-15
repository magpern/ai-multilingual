<?php
/**
 * Suspends AIML outbound URL localization for raw WordPress path reads.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * Request-local depth counter used while resolving unprefixed source paths.
 *
 * HierarchyPathBuilder and SB11 identity lookups must observe WordPress URLs
 * without AIML home_url / term_link localization, while leaving third-party
 * and test filters intact.
 */
final class OutboundLocalizationSuspender {

	/**
	 * Nesting depth.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Runs a callback with AIML outbound localization suspended.
	 *
	 * @param callable(): mixed $callback Callback.
	 * @return mixed
	 */
	public static function run( callable $callback ) {
		++self::$depth;
		try {
			return $callback();
		} finally {
			--self::$depth;
		}
	}

	/**
	 * Whether AIML outbound localization should no-op.
	 */
	public static function is_suspended(): bool {
		return self::$depth > 0;
	}
}
