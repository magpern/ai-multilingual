<?php
/**
 * Effective URL authority (MSEO.2).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Language\Languages;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;
use WP_Post;

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
	 * @param Settings                    $settings     Plugin settings.
	 * @param SlugRouteRepository         $routes       Route repository.
	 * @param RoutingCapabilityRegistry   $capabilities Capability registry.
	 * @param PathCanonicalizer           $paths        Path canonicalizer.
	 * @param Languages                   $languages    Language registry.
	 */
	public function __construct(
		private Settings $settings,
		private SlugRouteRepository $routes,
		private RoutingCapabilityRegistry $capabilities,
		private PathCanonicalizer $paths,
		private Languages $languages
	) {
	}

	/**
	 * Returns the unprefixed effective path for a source path and language.
	 *
	 * @param string $source_path Unprefixed source/canonical path.
	 * @param int    $language_id Target language id.
	 */
	public function unprefixed_effective_path( string $source_path, int $language_id ): string {
		if ( $this->is_default_language( $language_id ) ) {
			return $source_path;
		}

		if ( ! $this->settings->is_localized_url_generation_enabled() ) {
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

		if ( Store::SOURCE_POST !== (string) ( $route->source_type ?? '' ) ) {
			$this->cache[ $cache_key ] = $source_path;

			return $source_path;
		}

		$post = get_post( (int) ( $route->source_id ?? 0 ) );
		if ( ! $post instanceof WP_Post || ! $this->capabilities->supports_post( $post ) ) {
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
