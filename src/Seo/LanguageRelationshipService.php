<?php
/**
 * SB11 reusable language-relationship contract.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Seo;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;

/**
 * Read-only language relationship graph for a document/request.
 *
 * Downstream SEO waves consume this contract unchanged.
 * Depends only on A.SEOa URL rules, Languages, and LanguageContext — never on
 * later SEO-wave implementations.
 */
final class LanguageRelationshipService {

	/**
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * @var LanguageContext
	 */
	private LanguageContext $context;

	/**
	 * @param Languages       $languages Language registry.
	 * @param LanguageContext $context   Request language state.
	 */
	public function __construct( Languages $languages, LanguageContext $context ) {
		$this->languages = $languages;
		$this->context   = $context;
	}

	/**
	 * Builds the public SEO relationship set for the current request.
	 *
	 * Preview languages are excluded (ADR-0008 / SB9).
	 *
	 * @return list<LanguageRelationship>
	 */
	public function for_public_request(): array {
		return $this->for_path( $this->current_unprefixed_path(), false );
	}

	/**
	 * Builds relationships for an unprefixed site-relative path.
	 *
	 * @param string $unprefixed_path Path after Router strip (leading slash).
	 * @param bool   $include_preview When true, include preview languages (capability surfaces only).
	 * @return list<LanguageRelationship>
	 */
	public function for_path( string $unprefixed_path, bool $include_preview = false ): array {
		$path    = $this->normalize_path( $unprefixed_path );
		$current = $this->context->current();
		$current_id = null === $current ? 0 : (int) $current->language_id;

		$out = array();
		foreach ( $this->languages->routable( $include_preview ) as $language ) {
			$out[] = new LanguageRelationship(
				(string) $language->code,
				str_replace( '_', '-', (string) $language->locale ),
				$this->url_for_language( $language, $path ),
				! empty( $language->is_default ),
				(int) $language->language_id === $current_id
			);
		}

		return $out;
	}

	/**
	 * Absolute SA7 URL for one language and unprefixed path.
	 *
	 * @param object $language Language row.
	 * @param string $path     Unprefixed path.
	 */
	public function url_for_language( object $language, string $path ): string {
		$path = $this->normalize_path( $path );
		$home = $this->raw_home();

		if ( ! empty( $language->is_default ) ) {
			return $home . ltrim( $path, '/' );
		}

		$code = (string) $language->code;
		if ( '/' === $path ) {
			return $home . $code . '/';
		}

		return $home . $code . $path;
	}

	/**
	 * Current request path relative to home with language prefix already stripped.
	 */
	public function current_unprefixed_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$home = trim( $home, '/' );

		if ( '' !== $home ) {
			$normalized = '/' . ltrim( $path, '/' );
			if ( 0 === strpos( $normalized, '/' . $home ) ) {
				$path = substr( $normalized, strlen( $home ) + 1 );
			}
		}

		return $this->normalize_path( (string) $path );
	}

	/**
	 * Relationship for the current language, if present in the public set.
	 */
	public function current_public(): ?LanguageRelationship {
		foreach ( $this->for_public_request() as $rel ) {
			if ( $rel->is_current ) {
				return $rel;
			}
		}

		return null;
	}

	/**
	 * Relationship for the default language in the public set.
	 */
	public function default_public(): ?LanguageRelationship {
		foreach ( $this->for_public_request() as $rel ) {
			if ( $rel->is_default ) {
				return $rel;
			}
		}

		return null;
	}

	/**
	 * @param string $path Raw path.
	 */
	private function normalize_path( string $path ): string {
		$path = '/' . ltrim( $path, '/' );

		return '' === $path ? '/' : $path;
	}

	/**
	 * Site home with trailing slash and no language prefix.
	 */
	private function raw_home(): string {
		return trailingslashit( (string) get_option( 'home' ) );
	}
}
