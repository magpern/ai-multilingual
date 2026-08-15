<?php
/**
 * Sole hierarchical / term path construction authority (MSEO.3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Builds source and localized full paths without duplicating ancestor walks.
 */
final class HierarchyPathBuilder {

	/**
	 * Constructs the builder.
	 *
	 * @param SlugRouteRepository $routes Route repository.
	 * @param PathCanonicalizer   $paths  Path canonicalizer.
	 */
	public function __construct(
		private SlugRouteRepository $routes,
		private PathCanonicalizer $paths
	) {
	}

	/**
	 * Unprefixed source path for a post from WordPress permalink facts.
	 *
	 * @param WP_Post $post Source post.
	 * @return CanonicalPath|WP_Error
	 */
	public function source_path_for_post( WP_Post $post ) {
		if ( 'page' === $post->post_type ) {
			$uri = (string) get_page_uri( $post );
			try {
				return $this->paths->canonicalize( '/' . ltrim( $uri, '/' ) );
			} catch ( InvalidPathException $e ) {
				return new WP_Error( 'aiml_hierarchy_invalid_source', $e->getMessage() );
			}
		}

		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return new WP_Error( 'aiml_hierarchy_no_permalink', __( 'Source permalink is unavailable.', 'ai-multilingual' ) );
		}

		$path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$home = trim( $home, '/' );
		if ( '' !== $home ) {
			$normalized = '/' . ltrim( $path, '/' );
			if ( 0 === strpos( $normalized, '/' . $home ) ) {
				$path = substr( $normalized, strlen( $home ) + 1 );
			}
		}

		try {
			return $this->paths->canonicalize( '/' . ltrim( (string) $path, '/' ) );
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_hierarchy_invalid_source', $e->getMessage() );
		}
	}

	/**
	 * Unprefixed source path for a term from WordPress/Woo get_term_link.
	 *
	 * Suspends AIML outbound localization so source paths are not language-
	 * prefixed or re-entered via term_link (v1.5.1 D1). Third-party term_link
	 * filters remain active.
	 *
	 * @param WP_Term $term Source term.
	 * @return CanonicalPath|WP_Error
	 */
	public function source_path_for_term( WP_Term $term ) {
		$link = OutboundLocalizationSuspender::run(
			static function () use ( $term ) {
				return get_term_link( $term );
			}
		);

		if ( $link instanceof WP_Error ) {
			return $link;
		}

		$path = (string) wp_parse_url( (string) $link, PHP_URL_PATH );
		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$home = trim( $home, '/' );
		if ( '' !== $home ) {
			$normalized = '/' . ltrim( $path, '/' );
			if ( 0 === strpos( $normalized, '/' . $home ) ) {
				$path = substr( $normalized, strlen( $home ) + 1 );
			}
		}

		try {
			return $this->paths->canonicalize( '/' . ltrim( (string) $path, '/' ) );
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_hierarchy_invalid_term_source', $e->getMessage() );
		}
	}

	/**
	 * Localized full path for a post: ancestor localized leaves + own leaf.
	 *
	 * @param WP_Post $post        Source post.
	 * @param int     $language_id Language id.
	 * @param string  $own_leaf    Localized leaf slug for this object.
	 * @return CanonicalPath|WP_Error
	 */
	public function localized_path_for_post( WP_Post $post, int $language_id, string $own_leaf ) {
		$source = $this->source_path_for_post( $post );
		if ( $source instanceof WP_Error ) {
			return $source;
		}

		if ( (int) $post->post_parent <= 0 || 'page' !== $post->post_type ) {
			return $this->replace_leaf( $source, $own_leaf );
		}

		$segments  = array();
		$ancestors = array_reverse( get_post_ancestors( $post ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_post( (int) $ancestor_id );
			if ( ! $ancestor instanceof WP_Post ) {
				continue;
			}
			$segments[] = $this->effective_leaf_for_post( $ancestor, $language_id );
		}
		$segments[] = sanitize_title( $own_leaf );

		try {
			return $this->paths->canonicalize( '/' . implode( '/', array_filter( $segments ) ) );
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_hierarchy_invalid_localized', $e->getMessage() );
		}
	}

	/**
	 * Localized full path for a term within the WP canonical structure.
	 *
	 * Substitutes admitted localized leaves into the source path derived from
	 * get_term_link, preserving untranslated rewrite bases.
	 *
	 * @param WP_Term $term        Source term.
	 * @param int     $language_id Language id.
	 * @param string  $own_leaf    Localized leaf slug.
	 * @return CanonicalPath|WP_Error
	 */
	public function localized_path_for_term( WP_Term $term, int $language_id, string $own_leaf ) {
		$source = $this->source_path_for_term( $term );
		if ( $source instanceof WP_Error ) {
			return $source;
		}

		$source_str  = $source->to_string();
		$leaf        = sanitize_title( $own_leaf );
		$source_leaf = sanitize_title( (string) $term->slug );

		// Replace trailing source leaf with localized leaf; keep base prefixes.
		if ( '' !== $source_leaf && str_ends_with( rtrim( $source_str, '/' ), $source_leaf ) ) {
			$prefix = substr( rtrim( $source_str, '/' ), 0, -strlen( $source_leaf ) );
			$built  = rtrim( $prefix, '/' ) . '/' . $leaf;
		} else {
			$built = $this->replace_leaf( $source, $leaf );
			if ( $built instanceof WP_Error ) {
				return $built;
			}

			return $built;
		}

		// Hierarchical taxonomies: substitute ancestor leaves when parents appear in URL.
		if ( is_taxonomy_hierarchical( (string) $term->taxonomy ) && (int) $term->parent > 0 ) {
			$built = $this->substitute_term_ancestor_leaves( $term, $language_id, $built );
		}

		try {
			return $this->paths->canonicalize( $built );
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_hierarchy_invalid_term_localized', $e->getMessage() );
		}
	}

	/**
	 * Effective leaf for an ancestor post (localized if active route, else source).
	 *
	 * @param WP_Post $post        Ancestor post.
	 * @param int     $language_id Language id.
	 */
	private function effective_leaf_for_post( WP_Post $post, int $language_id ): string {
		$route = $this->routes->find_by_object( Store::SOURCE_POST, (int) $post->ID, $language_id );
		if ( null !== $route && 'active' === (string) ( $route->route_status ?? '' ) ) {
			$slug = (string) ( $route->localized_slug ?? '' );
			if ( '' !== $slug ) {
				return sanitize_title( $slug );
			}
			$loc   = (string) ( $route->localized_path ?? '' );
			$parts = array_values( array_filter( explode( '/', trim( $loc, '/' ) ) ) );
			if ( array() !== $parts ) {
				return sanitize_title( (string) end( $parts ) );
			}
		}

		return sanitize_title( (string) $post->post_name );
	}

	/**
	 * Replaces the last path segment.
	 *
	 * @param CanonicalPath $source Source path.
	 * @param string        $leaf   New leaf.
	 * @return CanonicalPath|WP_Error
	 */
	private function replace_leaf( CanonicalPath $source, string $leaf ) {
		$leaf = sanitize_title( $leaf );
		if ( '' === $leaf ) {
			return new WP_Error( 'aiml_hierarchy_empty_leaf', __( 'Localized leaf is empty.', 'ai-multilingual' ) );
		}

		$parts = array_values( array_filter( explode( '/', trim( $source->to_string(), '/' ) ) ) );
		if ( array() === $parts ) {
			$parts = array( $leaf );
		} else {
			$parts[ count( $parts ) - 1 ] = $leaf;
		}

		try {
			return $this->paths->canonicalize( '/' . implode( '/', $parts ) );
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_hierarchy_invalid_localized', $e->getMessage() );
		}
	}

	/**
	 * Substitutes localized ancestor term leaves inside a built path string.
	 *
	 * @param WP_Term $term        Leaf term.
	 * @param int     $language_id Language id.
	 * @param string  $path        Path being built.
	 */
	private function substitute_term_ancestor_leaves( WP_Term $term, int $language_id, string $path ): string {
		$taxonomy  = (string) $term->taxonomy;
		$parent_id = (int) $term->parent;
		while ( $parent_id > 0 ) {
			$parent = get_term( $parent_id, $taxonomy );
			if ( ! $parent instanceof WP_Term || is_wp_error( $parent ) ) {
				break;
			}

			$source_leaf = sanitize_title( (string) $parent->slug );
			$route       = $this->routes->find_by_object( Store::SOURCE_TERM, (int) $parent->term_id, $language_id );
			$localized   = $source_leaf;
			if ( null !== $route && 'active' === (string) ( $route->route_status ?? '' ) ) {
				$slug = (string) ( $route->localized_slug ?? '' );
				if ( '' !== $slug ) {
					$localized = sanitize_title( $slug );
				}
			}

			if ( $localized !== $source_leaf && '' !== $source_leaf ) {
				$path = preg_replace(
					'#/' . preg_quote( $source_leaf, '#' ) . '(/|$)#',
					'/' . $localized . '$1',
					$path,
					1
				) ?? $path;
			}

			$parent_id = (int) $parent->parent;
		}

		return $path;
	}
}
