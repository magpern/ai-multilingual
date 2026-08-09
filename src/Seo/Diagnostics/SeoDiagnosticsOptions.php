<?php
/**
 * SEO diagnostics scan options (A.SEOf).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Seo\Diagnostics;

/**
 * Bounded scan configuration for the shared diagnostics core.
 */
final class SeoDiagnosticsOptions {

	public const MAX_REDIRECT_DEPTH = 10;

	public const MAX_HTTP_FETCHES = 5;

	/**
	 * Builds scan options.
	 *
	 * @param string $url            Absolute URL to validate (optional).
	 * @param string $path           Unprefixed path fallback (e.g. /about/).
	 * @param bool   $include_http   Whether bounded HTTP emission checks may run.
	 * @param int    $redirect_depth Max redirect hops.
	 */
	public function __construct(
		public readonly string $url = '',
		public readonly string $path = '/',
		public readonly bool $include_http = true,
		public readonly int $redirect_depth = self::MAX_REDIRECT_DEPTH,
	) {
	}

	/**
	 * Normalized unprefixed path.
	 */
	public function normalized_path(): string {
		$path = '/' . ltrim( $this->path, '/' );
		return '' === $path ? '/' : $path;
	}

	/**
	 * Capped redirect depth.
	 */
	public function capped_redirect_depth(): int {
		$depth = $this->redirect_depth;
		if ( $depth < 1 ) {
			return 1;
		}
		return min( $depth, self::MAX_REDIRECT_DEPTH );
	}
}
