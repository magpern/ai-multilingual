<?php
/**
 * Narrow Woo product category selection adapter (MSEO.4 A1).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Routing;

use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Observes Woo's permalink decision — does not clone deepest-category logic.
 */
final class WooProductCategoryAuthority {

	/**
	 * Resolves the Woo-selected product_cat chain for a product.
	 *
	 * Invokes get_permalink while capturing wc_product_post_type_link_product_cat.
	 * Capture filter is always removed (try/finally).
	 *
	 * @param WP_Post $product Product post.
	 * @return array{selected: ?WP_Term, chain: list<WP_Term>, source_path: CanonicalPath, placeholder: bool}|WP_Error
	 */
	public function resolve( WP_Post $product ) {
		if ( 'product' !== $product->post_type ) {
			return new WP_Error( 'aiml_woo_not_product', 'Not a product.' );
		}

		$captured = null;
		$filter   = static function ( $term, $terms, $post ) use ( &$captured, $product ) {
			unset( $terms );
			if ( $post instanceof WP_Post && (int) $post->ID === (int) $product->ID && $term instanceof WP_Term ) {
				$captured = $term;
			}

			return $term;
		};

		add_filter( 'wc_product_post_type_link_product_cat', $filter, 99999, 3 );

		try {
			$permalink = get_permalink( $product );
			if ( ! is_string( $permalink ) || '' === $permalink ) {
				return new WP_Error( 'aiml_woo_no_permalink', 'Woo product permalink unavailable.' );
			}

			$source = $this->path_from_url( $permalink );
			if ( $source instanceof WP_Error ) {
				return $source;
			}

			$placeholder = false;
			$chain       = array();
			$selected    = null;

			if ( $captured instanceof WP_Term ) {
				$selected = $captured;
				$chain    = $this->ancestor_chain_including_self( $captured );
			} else {
				// No Woo category capture — typically uncategorized placeholder.
				$placeholder = true;
			}

			return array(
				'source_path' => $source,
				'selected'    => $selected,
				'chain'       => $chain,
				'placeholder' => $placeholder,
			);
		} finally {
			remove_filter( 'wc_product_post_type_link_product_cat', $filter, 99999 );
		}
	}

	/**
	 * Whether two resolve() passes select the same term id (determinism probe).
	 *
	 * @param WP_Post $product Product.
	 */
	public function is_selection_deterministic( WP_Post $product ): bool {
		$a = $this->resolve( $product );
		$b = $this->resolve( $product );
		if ( $a instanceof WP_Error || $b instanceof WP_Error ) {
			return false;
		}
		$id_a = $a['selected'] instanceof WP_Term ? (int) $a['selected']->term_id : 0;
		$id_b = $b['selected'] instanceof WP_Term ? (int) $b['selected']->term_id : 0;

		return $id_a === $id_b && (string) $a['source_path']->to_string() === (string) $b['source_path']->to_string();
	}

	/**
	 * Builds root-first product_cat ancestor chain including the selected term.
	 *
	 * @param WP_Term $term Selected leaf term.
	 * @return list<WP_Term> Root-first chain.
	 */
	private function ancestor_chain_including_self( WP_Term $term ): array {
		$ids   = array_reverse( get_ancestors( (int) $term->term_id, 'product_cat' ) );
		$ids[] = (int) $term->term_id;
		$out   = array();
		foreach ( $ids as $id ) {
			$t = get_term( (int) $id, 'product_cat' );
			if ( $t instanceof WP_Term && ! is_wp_error( $t ) ) {
				$out[] = $t;
			}
		}

		return $out;
	}

	/**
	 * Converts an absolute permalink URL into a site-relative CanonicalPath.
	 *
	 * @param string $url Absolute URL.
	 * @return CanonicalPath|WP_Error
	 */
	private function path_from_url( string $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$home = trim( $home, '/' );
		if ( '' !== $home ) {
			$normalized = '/' . ltrim( $path, '/' );
			if ( 0 === strpos( $normalized, '/' . $home ) ) {
				$path = substr( $normalized, strlen( $home ) + 1 );
			}
		}

		try {
			return ( new PathCanonicalizer() )->canonicalize( '/' . ltrim( (string) $path, '/' ) );
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_woo_invalid_path', $e->getMessage() );
		}
	}
}
