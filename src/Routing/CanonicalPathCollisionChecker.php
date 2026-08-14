<?php
/**
 * Canonical path collision checks before prepared-route activation.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Translation\Store;
use WP_Error;

/**
 * Verifies AIML route/history and bounded WordPress collisions.
 */
final class CanonicalPathCollisionChecker {

	public const MAX_SUFFIX_ATTEMPTS = 20;

	/**
	 * Constructs the service.
	 *
	 * @param SlugRouteRepository    $routes  Route repo.
	 * @param RouteHistoryRepository $history History repo.
	 * @param PathCanonicalizer      $paths   Path canonicalizer.
	 */
	public function __construct(
		private SlugRouteRepository $routes,
		private RouteHistoryRepository $history,
		private PathCanonicalizer $paths
	) {
	}

	/**
	 * Whether the path is free for this source (or owned by it).
	 *
	 * @param int           $language_id Language id.
	 * @param CanonicalPath $path        Candidate localized path.
	 * @param string        $source_type Source type.
	 * @param int           $source_id   Source id.
	 * @return true|WP_Error
	 */
	public function assert_available( int $language_id, CanonicalPath $path, string $source_type, int $source_id ) {
		$route = $this->routes->find_by_localized_path( $language_id, $path );
		if ( null !== $route ) {
			$same = (string) ( $route->source_type ?? '' ) === $source_type
				&& (int) ( $route->source_id ?? 0 ) === $source_id;
			if ( ! $same ) {
				return new WP_Error( 'aiml_slug_route_collision', __( 'Localized path collides with another route.', 'ai-multilingual' ), array( 'status' => 409 ) );
			}
		}

		$hist = $this->history->find_by_historical_path( $language_id, $path );
		if ( null !== $hist ) {
			$same = (string) ( $hist->source_type ?? '' ) === $source_type
				&& (int) ( $hist->source_id ?? 0 ) === $source_id;
			if ( ! $same ) {
				return new WP_Error( 'aiml_slug_history_collision', __( 'Localized path is reserved in history by another object.', 'ai-multilingual' ), array( 'status' => 409 ) );
			}
		}

		$wp_collision = $this->wordpress_collision( $path, $source_type, $source_id );
		if ( $wp_collision instanceof WP_Error ) {
			return $wp_collision;
		}

		return true;
	}

	/**
	 * Resolves an effective leaf for generated candidates (suffix -2…-N).
	 *
	 * @param string $source_path_str Unprefixed source path.
	 * @param string $candidate_leaf  Editorial leaf.
	 * @param int    $language_id     Language id.
	 * @param string $source_type     Source type.
	 * @param int    $source_id       Source id.
	 * @param string $slug_origin     generated|manual.
	 * @return array{leaf: string, path: CanonicalPath}|WP_Error
	 */
	public function resolve_effective(
		string $source_path_str,
		string $candidate_leaf,
		int $language_id,
		string $source_type,
		int $source_id,
		string $slug_origin
	) {
		$attempt = $candidate_leaf;
		$max     = 'manual' === $slug_origin ? 1 : self::MAX_SUFFIX_ATTEMPTS;

		for ( $i = 0; $i < $max; $i++ ) {
			if ( $i > 0 ) {
				$attempt = $candidate_leaf . '-' . ( $i + 1 );
			}

			$path = $this->replace_leaf( $source_path_str, $attempt );
			if ( $path instanceof WP_Error ) {
				return $path;
			}

			$check = $this->assert_available( $language_id, $path, $source_type, $source_id );
			if ( true === $check ) {
				return array(
					'leaf' => $attempt,
					'path' => $path,
				);
			}

			if ( 'manual' === $slug_origin ) {
				return $check;
			}
		}

		return new WP_Error( 'aiml_slug_collision_exhausted', __( 'Could not resolve a free localized slug.', 'ai-multilingual' ), array( 'status' => 409 ) );
	}

	/**
	 * Helper.
	 *
	 * @param string $source_path_str Source path.
	 * @param string $leaf            New leaf.
	 * @return CanonicalPath|WP_Error
	 */
	public function replace_leaf( string $source_path_str, string $leaf ) {
		$trimmed = rtrim( $source_path_str, '/' );
		if ( '' === $trimmed || '/' === $trimmed ) {
			$next = '/' . ltrim( $leaf, '/' );
		} else {
			$pos = strrpos( $trimmed, '/' );
			if ( false === $pos ) {
				$next = '/' . ltrim( $leaf, '/' );
			} else {
				$next = substr( $trimmed, 0, $pos + 1 ) . ltrim( $leaf, '/' );
			}
		}

		try {
			return $this->paths->canonicalize( $next );
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_slug_invalid_path', $e->getMessage() );
		}
	}

	/**
	 * Helper.
	 *
	 * @param CanonicalPath $path        Path.
	 * @param string        $source_type Source type.
	 * @param int           $source_id   Source id.
	 * @return true|WP_Error
	 */
	private function wordpress_collision( CanonicalPath $path, string $source_type, int $source_id ) {
		if ( ! function_exists( 'url_to_postid' ) || ! function_exists( 'home_url' ) ) {
			return true;
		}

		$url = home_url( $path->to_string() );
		$id  = (int) url_to_postid( $url );
		if ( $id <= 0 ) {
			return true;
		}

		if ( Store::SOURCE_POST === $source_type && $id === $source_id ) {
			return true;
		}

		return new WP_Error( 'aiml_slug_wp_collision', __( 'Localized path collides with a WordPress object.', 'ai-multilingual' ), array( 'status' => 409 ) );
	}
}
