<?php
/**
 * Effective URL authority (MSEO.2 + MSEO.3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Language\Languages;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;
use WP_Post;
use WP_Term;

/**
 * Sole effective URL authority (ADR-0023 §16).
 */
final class EffectiveUrlService {

	/**
	 * Request-local cache keyed by language_id + source_path.
	 *
	 * @var array<string, string>
	 */
	private array $cache = array();

	/**
	 * Builds the effective URL service.
	 *
	 * @param Settings                   $settings     Plugin settings.
	 * @param SlugRouteRepository        $routes       Route repository.
	 * @param RoutingCapabilityRegistry  $capabilities Capability registry.
	 * @param PathCanonicalizer          $paths        Path canonicalizer.
	 * @param Languages                  $languages    Language registry.
	 * @param RoutingCapabilityAdmission $admission     Public admission authority.
	 */
	public function __construct(
		private Settings $settings,
		private SlugRouteRepository $routes,
		private RoutingCapabilityRegistry $capabilities,
		private PathCanonicalizer $paths,
		private Languages $languages,
		private ?RoutingCapabilityAdmission $admission = null
	) {
	}

	/**
	 * Returns the unprefixed effective path for a source path and language.
	 *
	 * @param string $source_path Unprefixed source/canonical path.
	 * @param int    $language_id Target language id.
	 */
	public function unprefixed_effective_path( string $source_path, int $language_id ): string {
		if ( ! $this->settings->is_localized_url_generation_enabled() ) {
			return $source_path;
		}

		if ( $this->is_default_language( $language_id ) ) {
			return $source_path;
		}

		$cache_key = $language_id . ':' . $source_path;
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		try {
			$canonical = $this->paths->canonicalize( $source_path );
		} catch ( InvalidPathException $e ) {
			unset( $e );

			return $source_path;
		}

		$route = $this->routes->find_active_by_source_path( $language_id, $canonical );
		if ( null === $route ) {
			$this->cache[ $cache_key ] = $source_path;

			return $source_path;
		}

		$source_type = (string) ( $route->source_type ?? '' );
		$source_id   = (int) ( $route->source_id ?? 0 );

		if ( Store::SOURCE_POST === $source_type ) {
			$post = get_post( $source_id );
			if ( ! $post instanceof WP_Post || ! $this->capabilities->supports_post( $post ) ) {
				$this->cache[ $cache_key ] = $source_path;

				return $source_path;
			}
			if ( null !== $this->admission && ! $this->admission->is_post_publicly_localizable( $post ) ) {
				$this->cache[ $cache_key ] = $source_path;

				return $source_path;
			}
		} elseif ( Store::SOURCE_TERM === $source_type ) {
			$term = get_term( $source_id );
			if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
				$this->cache[ $cache_key ] = $source_path;

				return $source_path;
			}
			if ( ! $this->capabilities->supports_term_object( $term ) ) {
				$this->cache[ $cache_key ] = $source_path;

				return $source_path;
			}
			if ( null === $this->admission || ! $this->admission->is_term_publicly_localizable( (string) $term->taxonomy ) ) {
				$this->cache[ $cache_key ] = $source_path;

				return $source_path;
			}
		} else {
			$this->cache[ $cache_key ] = $source_path;

			return $source_path;
		}

		$localized = (string) ( $route->localized_path ?? '' );
		if ( '' === $localized ) {
			$this->cache[ $cache_key ] = $source_path;

			return $source_path;
		}

		$this->cache[ $cache_key ] = $localized;

		return $localized;
	}

	/**
	 * Clears request-local cache (after route mutations in-request).
	 */
	public function clear_request_cache(): void {
		$this->cache = array();
	}

	/**
	 * Whether the language is the site default.
	 *
	 * @param int $language_id Language id.
	 */
	private function is_default_language( int $language_id ): bool {
		$language = $this->languages->find( $language_id );
		if ( null === $language ) {
			return false;
		}

		return ! empty( $language->is_default );
	}
}
