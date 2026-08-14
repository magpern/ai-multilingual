<?php
/**
 * Inert routing capability registry (MSEO.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use WP_Post;

/**
 * Model B capability facts for prepared-route publication gating.
 */
final class RoutingCapabilityRegistry {

	public const POST_FLAT                  = 'post_flat';
	public const PAGE_TOP_LEVEL             = 'page_top_level';
	public const PAGE_HIERARCHICAL          = 'page_hierarchical';
	public const PRODUCT_PLAIN_PERMALINK    = 'product_plain_permalink';
	public const PRODUCT_CATEGORY_PERMALINK = 'product_category_permalink';
	public const TERM_ARCHIVE               = 'term_archive';

	/**
	 * Whether route publication is supported for the object.
	 *
	 * @param WP_Post $post Source post.
	 */
	public function supports_post( WP_Post $post ): bool {
		return null !== $this->capability_for_post( $post )
			&& in_array(
				$this->capability_for_post( $post ),
				array( self::POST_FLAT, self::PAGE_TOP_LEVEL, self::PRODUCT_PLAIN_PERMALINK ),
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
			return self::PRODUCT_PLAIN_PERMALINK;
		}

		if ( 'post' === $type || ! is_post_type_hierarchical( $type ) ) {
			return self::POST_FLAT;
		}

		return self::PAGE_HIERARCHICAL;
	}

	/**
	 * Terms are deferred to MSEO.3.
	 */
	public function supports_term(): bool {
		return false;
	}
}
