<?php
/**
 * Woo product path authority for %product_cat% shapes (MSEO.4).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Routing;

use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Builds source/localized product paths; never clones Woo category selection.
 */
final class WooProductPathBuilder {

	public const OUTCOME_SYNCHRONIZED                       = 'synchronized';
	public const OUTCOME_SOURCE_FALLBACK_MISSING_COMPONENT  = 'source_fallback_missing_component';
	public const OUTCOME_SOURCE_FALLBACK_COLLISION          = 'source_fallback_collision';
	public const OUTCOME_SOURCE_FALLBACK_AUTHORITY_DISAGREE = 'source_fallback_authority_disagreement';
	public const OUTCOME_SOURCE_FALLBACK_NONDETERMINISTIC   = 'source_fallback_nondeterministic_filter';
	public const OUTCOME_SKIPPED_NOT_PUBLIC                 = 'skipped_not_public';
	public const OUTCOME_SKIPPED_UNSUPPORTED                = 'skipped_unsupported';
	public const OUTCOME_INVALID_DATA                       = 'invalid_data';
	public const OUTCOME_SYSTEM_ERROR                       = 'system_error';

	/**
	 * Constructs the Woo product path builder.
	 *
	 * @param WooProductCategoryAuthority $authority Category capture adapter.
	 * @param SlugRouteRepository         $routes    Active routes.
	 * @param PathCanonicalizer           $paths     Canonicalizer.
	 */
	public function __construct(
		private WooProductCategoryAuthority $authority,
		private SlugRouteRepository $routes,
		private PathCanonicalizer $paths
	) {
	}

	/**
	 * Authoritative Woo source path for a product.
	 *
	 * @param WP_Post $product Product.
	 * @return CanonicalPath|WP_Error
	 */
	public function source_path( WP_Post $product ) {
		$resolved = $this->authority->resolve( $product );
		if ( $resolved instanceof WP_Error ) {
			return $resolved;
		}

		return $resolved['source_path'];
	}

	/**
	 * Builds localized product path or fails closed with outcome.
	 *
	 * @param WP_Post $product     Product.
	 * @param int     $language_id Language id.
	 * @param string  $product_leaf Localized product leaf slug.
	 * @return array{path: CanonicalPath, outcome: string, chain: list<WP_Term>}|WP_Error
	 */
	public function localized_path( WP_Post $product, int $language_id, string $product_leaf ) {
		if ( 'product' !== $product->post_type ) {
			return new WP_Error( 'aiml_woo_not_product', 'Not a product.' );
		}

		$product_leaf = sanitize_title( $product_leaf );
		if ( '' === $product_leaf ) {
			return new WP_Error( 'aiml_woo_empty_leaf', 'Empty product leaf.' );
		}

		if ( ! $this->authority->is_selection_deterministic( $product ) ) {
			return array(
				'path'    => null,
				'outcome' => self::OUTCOME_SOURCE_FALLBACK_NONDETERMINISTIC,
				'chain'   => array(),
			);
		}

		$resolved = $this->authority->resolve( $product );
		if ( $resolved instanceof WP_Error ) {
			return $resolved;
		}

		$source = $resolved['source_path'];
		$chain  = $resolved['chain'];

		// Reconstruct expected source-shape from chain + leaf and compare to Woo.
		$interpreted = $this->interpret_source_shape( $source, $chain, (string) $product->post_name );
		if ( true !== $interpreted ) {
			return array(
				'path'    => null,
				'outcome' => self::OUTCOME_SOURCE_FALLBACK_AUTHORITY_DISAGREE,
				'chain'   => $chain,
			);
		}

		$segments = array_values( array_filter( explode( '/', trim( $source->to_string(), '/' ) ) ) );
		if ( array() === $segments ) {
			return new WP_Error( 'aiml_woo_empty_source', 'Empty source path.' );
		}

		// Replace trailing product leaf.
		$segments[ count( $segments ) - 1 ] = $product_leaf;

		if ( ! $resolved['placeholder'] && array() !== $chain ) {
			$cat_slugs = array();
			foreach ( $chain as $term ) {
				$cat_slugs[] = (string) $term->slug;
			}
			// Find contiguous category slug run ending before leaf in source segments.
			$leaf_index = count( $segments ) - 1;
			$cat_count  = count( $cat_slugs );
			$start      = $leaf_index - $cat_count;
			if ( $start < 0 ) {
				return array(
					'path'    => null,
					'outcome' => self::OUTCOME_SOURCE_FALLBACK_AUTHORITY_DISAGREE,
					'chain'   => $chain,
				);
			}
			for ( $i = 0; $i < $cat_count; $i++ ) {
				if ( (string) $segments[ $start + $i ] !== $cat_slugs[ $i ] ) {
					// Source may already differ if Woo parent_only — fail closed.
					return array(
						'path'    => null,
						'outcome' => self::OUTCOME_SOURCE_FALLBACK_AUTHORITY_DISAGREE,
						'chain'   => $chain,
					);
				}
				$route = $this->routes->find_by_object( Store::SOURCE_TERM, (int) $chain[ $i ]->term_id, $language_id );
				if ( null === $route || 'active' !== (string) ( $route->route_status ?? '' ) ) {
					return array(
						'path'    => null,
						'outcome' => self::OUTCOME_SOURCE_FALLBACK_MISSING_COMPONENT,
						'chain'   => $chain,
					);
				}
				$leaf = (string) ( $route->localized_slug ?? '' );
				if ( '' === $leaf ) {
					$parts = array_values( array_filter( explode( '/', trim( (string) ( $route->localized_path ?? '' ), '/' ) ) ) );
					$last  = end( $parts );
					$leaf  = is_string( $last ) ? $last : '';
				}
				if ( '' === $leaf ) {
					return array(
						'path'    => null,
						'outcome' => self::OUTCOME_SOURCE_FALLBACK_MISSING_COMPONENT,
						'chain'   => $chain,
					);
				}
				$segments[ $start + $i ] = $leaf;
			}
		}

		try {
			$path = $this->paths->canonicalize( '/' . implode( '/', $segments ) );
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_woo_invalid_localized', $e->getMessage() );
		}

		return array(
			'path'    => $path,
			'outcome' => self::OUTCOME_SYNCHRONIZED,
			'chain'   => $chain,
		);
	}

	/**
	 * Confirms category slug run + product leaf match Woo source path.
	 *
	 * @param CanonicalPath $source       Woo source path.
	 * @param array         $chain        Root-first category chain (list of WP_Term).
	 * @param string        $product_slug Canonical product post_name.
	 * @return true|false
	 */
	private function interpret_source_shape( CanonicalPath $source, array $chain, string $product_slug ): bool {
		$segments = array_values( array_filter( explode( '/', trim( $source->to_string(), '/' ) ) ) );
		if ( array() === $segments ) {
			return false;
		}
		$leaf = (string) $segments[ count( $segments ) - 1 ];
		if ( $leaf !== $product_slug && sanitize_title( $product_slug ) !== $leaf ) {
			// Allow Woo slug normalization differences only if leaf equals post_name after sanitize.
			if ( sanitize_title( $product_slug ) !== $leaf ) {
				return false;
			}
		}
		if ( array() === $chain ) {
			return true;
		}
		$cat_slugs = array();
		foreach ( $chain as $term ) {
			$cat_slugs[] = (string) $term->slug;
		}
		$leaf_index = count( $segments ) - 1;
		$cat_count  = count( $cat_slugs );
		$start      = $leaf_index - $cat_count;
		if ( $start < 0 ) {
			return false;
		}
		for ( $i = 0; $i < $cat_count; $i++ ) {
			if ( (string) $segments[ $start + $i ] !== $cat_slugs[ $i ] ) {
				return false;
			}
		}

		return true;
	}
}
