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
use AIMultilingual\Routing\EffectiveUrlService;
use AIMultilingual\Routing\ObjectLanguagePublicEligibility;
use AIMultilingual\Settings;
use AIMultilingual\Surface\AdmittedTaxonomies;
use WP_Post;
use WP_Term;

/**
 * Read-only language relationship graph for a document/request.
 *
 * Downstream SEO waves consume this contract unchanged.
 * MSEO.2 localizes URLs via EffectiveUrl when discoverable (or for canonical stability).
 * MSEO.3 extends discoverability to admitted term archives.
 */
final class LanguageRelationshipService {

	/**
	 * Language registry.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Request language state.
	 *
	 * @var LanguageContext
	 */
	private LanguageContext $context;

	/**
	 * Effective URL authority.
	 *
	 * @var EffectiveUrlService
	 */
	private EffectiveUrlService $effective_url;

	/**
	 * Shared SEO discoverability authority.
	 *
	 * @var ObjectLanguagePublicEligibility
	 */
	private ObjectLanguagePublicEligibility $eligibility;

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Builds the relationship service.
	 *
	 * @param Languages                       $languages     Language registry.
	 * @param LanguageContext                 $context       Request language state.
	 * @param EffectiveUrlService             $effective_url Effective URL authority.
	 * @param ObjectLanguagePublicEligibility $eligibility   Discoverability authority.
	 * @param Settings                        $settings      Plugin settings.
	 */
	public function __construct(
		Languages $languages,
		LanguageContext $context,
		EffectiveUrlService $effective_url,
		ObjectLanguagePublicEligibility $eligibility,
		Settings $settings
	) {
		$this->languages     = $languages;
		$this->context       = $context;
		$this->effective_url = $effective_url;
		$this->eligibility   = $eligibility;
		$this->settings      = $settings;
	}

	/**
	 * Builds the public SEO relationship set for the current request.
	 *
	 * Preview languages are excluded (ADR-0008 / SB9). Localized alternates
	 * are emitted only when is_discoverable; otherwise SA7 source-slug URLs
	 * remain (A.SEOb). Active routes without a discoverable bundle are omitted
	 * from hreflang/sitemap (MSEO.2 §22).
	 *
	 * @return list<LanguageRelationship>
	 */
	public function for_public_request(): array {
		return $this->for_path( $this->current_unprefixed_path(), false, true );
	}

	/**
	 * Builds relationships for an unprefixed site-relative path.
	 *
	 * @param string $unprefixed_path   Path after Router strip (leading slash).
	 * @param bool   $include_preview   When true, include preview languages (capability surfaces only).
	 * @param bool   $seo_advertisement When true, apply hreflang/sitemap omit rules for !discoverable active routes.
	 * @return list<LanguageRelationship>
	 */
	public function for_path( string $unprefixed_path, bool $include_preview = false, bool $seo_advertisement = false ): array {
		$path       = $this->normalize_path( $unprefixed_path );
		$current    = $this->context->current();
		$current_id = null === $current ? 0 : (int) $current->language_id;
		$post       = $this->resolve_post_for_path( $path );
		$term       = null === $post ? $this->resolve_term_for_path( $path ) : null;

		if ( $seo_advertisement && $post instanceof WP_Post && ! $this->is_source_publicly_viewable( $post ) ) {
			return array();
		}

		$out = array();
		foreach ( $this->languages->routable( $include_preview ) as $language ) {
			$language_id  = (int) $language->language_id;
			$discoverable = false;
			if ( $post instanceof WP_Post ) {
				$discoverable = $this->eligibility->is_discoverable( $post, $language_id );
			} elseif ( $term instanceof WP_Term ) {
				$discoverable = $this->eligibility->is_term_discoverable( $term, $language_id );
			}

			if ( $seo_advertisement && $this->settings->is_localized_url_generation_enabled() ) {
				if ( $post instanceof WP_Post && ! $discoverable && $this->eligibility->has_active_route( $post, $language_id ) ) {
					continue;
				}
				if ( $term instanceof WP_Term && ! $discoverable && $this->eligibility->has_active_term_route( $term, $language_id ) ) {
					continue;
				}
			}

			$out[] = new LanguageRelationship(
				(string) $language->code,
				str_replace( '_', '-', (string) $language->locale ),
				$this->url_for_language( $language, $path, $post, $discoverable ),
				! empty( $language->is_default ),
				$language_id === $current_id
			);
		}

		return $out;
	}

	/**
	 * Absolute URL for one language and unprefixed path.
	 *
	 * @param object       $language            Language row.
	 * @param string       $path                Unprefixed path.
	 * @param WP_Post|null $post                Source post when known.
	 * @param bool         $use_localized_path  When true, EffectiveUrl may localize.
	 */
	public function url_for_language( object $language, string $path, ?WP_Post $post = null, bool $use_localized_path = true ): string {
		$path = $this->normalize_path( $path );
		$home = $this->raw_home();

		$effective_path = $path;
		if ( $use_localized_path && $this->settings->is_localized_url_generation_enabled() ) {
			$effective_path = $this->effective_url->unprefixed_effective_path( $path, (int) $language->language_id );
		}

		if ( ! empty( $language->is_default ) ) {
			return $home . ltrim( $effective_path, '/' );
		}

		$code = (string) $language->code;
		if ( '/' === $effective_path ) {
			return $home . $code . '/';
		}

		return $home . $code . $effective_path;
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
	 * Canonical URL for the current request (may localize when ON even if !discoverable).
	 */
	public function current_canonical_url(): ?string {
		$current = $this->context->current();
		if ( null === $current ) {
			return null;
		}

		$path = $this->current_unprefixed_path();
		$post = $this->resolve_post_for_path( $path );

		return $this->url_for_language( $current, $path, $post, true );
	}

	/**
	 * Normalizes a site-relative path to a leading-slash form.
	 *
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

	/**
	 * Resolves a canonical post from an unprefixed path when possible.
	 *
	 * Uses unfiltered home URL so localized home_url admission cannot break
	 * source object resolution (MSEO.2 SB11).
	 *
	 * @param string $path Unprefixed site path.
	 */
	private function resolve_post_for_path( string $path ): ?WP_Post {
		if ( '/' === $path ) {
			return null;
		}

		$post_id = url_to_postid( $this->raw_home() . ltrim( $path, '/' ) );
		if ( $post_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );

		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Resolves a term archive from an unprefixed path when url_to_postid misses.
	 *
	 * @param string $path Unprefixed site path.
	 */
	private function resolve_term_for_path( string $path ): ?WP_Term {
		if ( '/' === $path ) {
			return null;
		}

		$parts = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		if ( array() === $parts ) {
			return null;
		}

		$leaf = (string) end( $parts );
		foreach ( AdmittedTaxonomies::all() as $taxonomy ) {
			$term = get_term_by( 'slug', $leaf, $taxonomy );
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$link = get_term_link( $term );
			if ( $link instanceof \WP_Error ) {
				continue;
			}

			$term_path = (string) wp_parse_url( (string) $link, PHP_URL_PATH );
			$home      = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
			$home      = trim( $home, '/' );
			if ( '' !== $home ) {
				$normalized = '/' . ltrim( $term_path, '/' );
				if ( 0 === strpos( $normalized, '/' . $home ) ) {
					$term_path = substr( $normalized, strlen( $home ) + 1 );
				}
			}
			$term_path = '/' . ltrim( (string) $term_path, '/' );

			if ( $this->normalize_path( $term_path ) === $this->normalize_path( $path ) ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * Whether the source object may appear in public SEO advertisements.
	 *
	 * @param WP_Post $post Source post.
	 */
	private function is_source_publicly_viewable( WP_Post $post ): bool {
		return in_array( (string) $post->post_status, array( 'publish', 'private' ), true );
	}
}
