<?php
/**
 * Pure language resolution.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Language;

/**
 * Decides which language a request path belongs to, and what the path looks
 * like once its language prefix is removed.
 *
 * This class is deliberately pure: it calls no WordPress function, reads no
 * superglobal and holds no mutable state. Everything it needs — the path, the
 * available languages and whether the viewer may see preview languages — is
 * passed in. That is what makes the routing rules testable without a
 * WordPress bootstrap, and it is why resolution is kept apart from
 * LanguageContext, which is mutable request state with a different lifecycle
 * (ADR-0008).
 *
 * Milestone 1 resolution priority is exactly two entries long: URL prefix, then
 * the default language. There is no cookie and no Accept-Language sniffing —
 * the URL is the sole authority, so responses stay free of Set-Cookie headers
 * and remain cacheable at the edge.
 */
final class LanguageResolver {

	/**
	 * Resolves a request path to a language.
	 *
	 * The default language is never prefixed in Milestone 1, so a path opening
	 * with the default language's own code is not treated as a prefix; it falls
	 * through unchanged and WordPress resolves (or 404s) it normally.
	 *
	 * @param string   $path               Request path, with or without leading slash.
	 * @param object[] $languages          Language rows.
	 * @param bool     $viewer_can_preview Whether the viewer may see preview languages.
	 * @return array{language: object|null, path: string, prefixed: bool}
	 */
	public function resolve( string $path, array $languages, bool $viewer_can_preview = false ): array {
		$default = $this->default_language( $languages );
		$normal  = $this->normalize_path( $path );

		$segment = $this->first_segment( $normal );

		if ( '' !== $segment ) {
			foreach ( $languages as $language ) {
				if ( (string) $language->code !== $segment ) {
					continue;
				}

				// The default language owns the unprefixed root; an explicit
				// prefix for it is not a route in Milestone 1.
				if ( ! empty( $language->is_default ) ) {
					break;
				}

				if ( ! $this->is_routable( $language, $viewer_can_preview ) ) {
					break;
				}

				return array(
					'language' => $language,
					'path'     => $this->strip_first_segment( $normal ),
					'prefixed' => true,
				);
			}
		}

		return array(
			'language' => $default,
			'path'     => $normal,
			'prefixed' => false,
		);
	}

	/**
	 * Whether a language may serve a front-end request.
	 *
	 * Published languages are public. Preview languages resolve only for a
	 * viewer holding the translation capability, so unfinished work is never
	 * exposed. Disabled languages are not routes at all.
	 *
	 * @param object $language           Language row.
	 * @param bool   $viewer_can_preview Whether the viewer may see preview languages.
	 */
	public function is_routable( object $language, bool $viewer_can_preview = false ): bool {
		$status = (string) ( $language->status ?? '' );

		if ( Languages::STATUS_PUBLISHED === $status ) {
			return true;
		}

		if ( Languages::STATUS_PREVIEW === $status ) {
			return $viewer_can_preview;
		}

		return false;
	}

	/**
	 * Returns the default language from a set, or null when none is marked.
	 *
	 * @param object[] $languages Language rows.
	 */
	public function default_language( array $languages ): ?object {
		foreach ( $languages as $language ) {
			if ( ! empty( $language->is_default ) ) {
				return $language;
			}
		}

		return null;
	}

	/**
	 * Ensures a path has exactly one leading slash and no query string.
	 *
	 * @param string $path Raw path.
	 */
	private function normalize_path( string $path ): string {
		$path = (string) strtok( $path, '?' );
		$path = (string) strtok( $path, '#' );

		if ( '' === $path ) {
			return '/';
		}

		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Returns the first path segment, lowercased, or an empty string.
	 *
	 * @param string $path Normalized path.
	 */
	private function first_segment( string $path ): string {
		$trimmed = trim( $path, '/' );

		if ( '' === $trimmed ) {
			return '';
		}

		$parts = explode( '/', $trimmed );

		return strtolower( $parts[0] );
	}

	/**
	 * Removes the first path segment, preserving the trailing-slash style.
	 *
	 * @param string $path Normalized path.
	 */
	private function strip_first_segment( string $path ): string {
		$trimmed = trim( $path, '/' );
		$parts   = explode( '/', $trimmed );

		array_shift( $parts );

		if ( array() === $parts ) {
			return '/';
		}

		$rest = implode( '/', $parts );

		if ( '' === $rest ) {
			return '/';
		}

		// Preserve whether the original path ended in a slash.
		$suffix = ( '/' === substr( $path, -1 ) ) ? '/' : '';

		return '/' . $rest . $suffix;
	}
}
