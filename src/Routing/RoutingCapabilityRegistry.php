<?php
/**
 * Routing capability registry (MSEO.1–MSEO.3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Surface\AdmittedTaxonomies;
use WP_Post;
use WP_Term;

/**
 * Model B capability facts for prepared-route publication gating (implemented).
 *
 * Public visitor use additionally requires {@see RoutingCapabilityAdmission}.
 */
final class RoutingCapabilityRegistry {

	public const POST_FLAT                  = 'post_flat';
	public const PAGE_TOP_LEVEL             = 'page_top_level';
	public const PAGE_HIERARCHICAL          = 'page_hierarchical';
	public const PRODUCT_PLAIN_PERMALINK    = 'product_plain_permalink';
	public const PRODUCT_CATEGORY_PERMALINK = 'product_category_permalink';
	public const TERM_ARCHIVE               = 'term_archive';

	/**
	 * Whether route publication is implemented for the post shape.
	 *
	 * @param WP_Post $post Source post.
	 */
	public function supports_post( WP_Post $post ): bool {
		$cap = $this->capability_for_post( $post );

		return null !== $cap
			&& in_array(
				$cap,
				array( self::POST_FLAT, self::PAGE_TOP_LEVEL, self::PAGE_HIERARCHICAL, self::PRODUCT_PLAIN_PERMALINK ),
				true
			);
	}

	/**
	 * Capability id or null when unsupported.
	 *
	 * @param WP_Post $post Source post.
	 */
	public function capability_for_post( WP_Post $post ): ?string {
		$type = (string) $post->post_type;

		if ( 'page' === $type ) {
			return (int) $post->post_parent > 0 ? self::PAGE_HIERARCHICAL : self::PAGE_TOP_LEVEL;
		}

		if ( 'product' === $type ) {
			return $this->is_plain_product_permalink()
				? self::PRODUCT_PLAIN_PERMALINK
				: self::PRODUCT_CATEGORY_PERMALINK;
		}

		if ( 'post' === $type || ! is_post_type_hierarchical( $type ) ) {
			return self::POST_FLAT;
		}

		return self::PAGE_HIERARCHICAL;
	}

	/**
	 * Whether term archive routing is implemented for any admitted taxonomy.
	 */
	public function supports_term(): bool {
		return true;
	}

	/**
	 * Whether term archive routing is implemented for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function supports_term_taxonomy( string $taxonomy ): bool {
		$taxonomy = (string) $taxonomy;
		if ( '' === $taxonomy ) {
			return false;
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax || empty( $tax->public ) ) {
			return false;
		}

		$admitted = AdmittedTaxonomies::admits( $taxonomy );

		return $admitted;
	}

	/**
	 * Whether a concrete term is implementable for routing.
	 *
	 * @param WP_Term $term Source term.
	 */
	public function supports_term_object( WP_Term $term ): bool {
		return $this->supports_term_taxonomy( (string) $term->taxonomy );
	}

	/**
	 * Whether WooCommerce product permalinks omit the category base placeholder.
	 */
	public function is_plain_product_permalink(): bool {
		if ( function_exists( 'wc_get_permalink_structure' ) ) {
			$structure = wc_get_permalink_structure();
			if ( is_object( $structure ) && isset( $structure->product_base ) ) {
				return ! str_contains( (string) $structure->product_base, '%product_cat%' );
			}
		}

		$permalinks = get_option( 'woocommerce_permalink_structure', array() );
		if ( ! is_array( $permalinks ) ) {
			return true;
		}

		$base = (string) ( $permalinks['product_base'] ?? '' );

		return '' === $base || ! str_contains( $base, '%product_cat%' );
	}
}
