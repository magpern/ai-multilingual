<?php
/**
 * URL path canonicalization for route identity.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * Normalizes URL path components for MSEO route identity (ADR-0023 §3, R4).
 *
 * Accepts a path component only. Query strings, fragments, and request-target
 * reconstruction belong to callers (Router in MSEO.2+).
 */
final class PathCanonicalizer {

	/**
	 * Maximum normalized path length.
	 */
	public const MAX_LENGTH = 2048;

	/**
	 * Canonicalizes a URL path component.
	 *
	 * @param string $path Raw path (may include leading slash).
	 * @throws InvalidPathException When the path cannot be normalized.
	 */
	public function canonicalize( string $path ): CanonicalPath {
		if ( str_contains( $path, "\0" ) ) {
			throw new InvalidPathException( 'Path contains null byte.' );
		}

		if ( str_contains( $path, '\\' ) ) {
			throw new InvalidPathException( 'Path contains backslash.' );
		}

		if ( str_contains( $path, '?' ) || str_contains( $path, '#' ) ) {
			throw new InvalidPathException( 'Path must not contain query or fragment.' );
		}

		if ( ! mb_check_encoding( $path, 'UTF-8' ) ) {
			throw new InvalidPathException( 'Path is not valid UTF-8.' );
		}

		$path = $this->reject_encoded_slashes( $path );
		$path = $this->decode_and_validate_percent_encoding( $path );
		$path = $this->collapse_slashes( $path );
		$path = $this->ensure_leading_slash( $path );
		$path = $this->apply_trailing_slash_policy( $path );

		if ( strlen( $path ) > self::MAX_LENGTH ) {
			throw new InvalidPathException( 'Path exceeds maximum length.' );
		}

		return new CanonicalPath( $path );
	}

	/**
	 * Decodes percent-encoding and rejects malformed sequences.
	 *
	 * @param string $path Path segment under validation.
	 * @throws InvalidPathException When percent-encoding is malformed.
	 */
	private function decode_and_validate_percent_encoding( string $path ): string {
		if ( ! preg_match_all( '/%[0-9A-Fa-f]{0,2}/', $path, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $path;
		}

		foreach ( $matches[0] as $match ) {
			$token = (string) $match[0];
			if ( 3 !== strlen( $token ) ) {
				throw new InvalidPathException( 'Malformed percent-encoding.' );
			}
		}

		$decoded = rawurldecode( $path );
		if ( false === $decoded ) {
			throw new InvalidPathException( 'Malformed percent-encoding.' );
		}

		return $this->normalize_percent_sequences( $decoded );
	}

	/**
	 * Uppercase hex digits in percent-encoded sequences for determinism.
	 *
	 * @param string $path Decoded path.
	 */
	private function normalize_percent_sequences( string $path ): string {
		return (string) preg_replace_callback(
			'/%[0-9a-f]{2}/i',
			static fn( array $m ): string => strtoupper( (string) $m[0] ),
			$path
		);
	}

	/**
	 * Rejects encoded forward slashes in path segments.
	 *
	 * @param string $path Raw path.
	 * @throws InvalidPathException When an encoded slash is present.
	 */
	private function reject_encoded_slashes( string $path ): string {
		if ( preg_match( '/%2F/i', $path ) ) {
			throw new InvalidPathException( 'Encoded slash is not allowed in path.' );
		}

		return $path;
	}

	/**
	 * Collapses duplicate slashes (preserves leading slash semantics).
	 *
	 * @param string $path Path with normalized encoding.
	 */
	private function collapse_slashes( string $path ): string {
		$path = (string) preg_replace( '#/{2,}#', '/', $path );

		return $path;
	}

	/**
	 * Ensures exactly one leading slash.
	 *
	 * @param string $path Path without guaranteed leading slash.
	 */
	private function ensure_leading_slash( string $path ): string {
		$path = ltrim( $path, '/' );

		return '/' . $path;
	}

	/**
	 * WordPress-compatible trailing slash: preserve if input had trailing slash
	 * on non-root paths; root stays '/'.
	 *
	 * @param string $path Path with leading slash.
	 */
	private function apply_trailing_slash_policy( string $path ): string {
		if ( '/' === $path ) {
			return '/';
		}

		// Deterministic policy: no trailing slash except root (WP-compatible).
		$trimmed = rtrim( $path, '/' );

		return '' === $trimmed ? '/' : $trimmed;
	}
}
