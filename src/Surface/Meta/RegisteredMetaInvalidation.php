<?php
/**
 * Registered-meta invalidation helpers (TSC.2).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface\Meta;

use AIMultilingual\Surface\AdmittedPostTypes;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Translation\Store;

/**
 * Maps meta hooks to coordinator dirty marks for registered keys only.
 */
final class RegisteredMetaInvalidation {

	/**
	 * @param RegisteredMetaRegistry $registry Catalog.
	 */
	public function __construct(
		private RegisteredMetaRegistry $registry,
	) {
	}

	/**
	 * Whether a post meta change should dirty the post surface.
	 *
	 * @param int    $object_id Post id.
	 * @param string $meta_key  Meta key.
	 */
	public function should_dirty_post( int $object_id, string $meta_key ): bool {
		if ( $object_id <= 0 || ! $this->registry->has( Store::SOURCE_POST, $meta_key ) ) {
			return false;
		}
		if ( ! function_exists( 'get_post' ) ) {
			return false;
		}
		$post = get_post( $object_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		$post_type = (string) $post->post_type;
		return AdmittedPostTypes::admits( $post_type, AdmittedPostTypes::CONTEXT_WORKSPACE )
			|| AdmittedPostTypes::admits( $post_type, AdmittedPostTypes::CONTEXT_FRONTEND_OVERLAY );
	}

	/**
	 * Whether a term meta change should dirty the term surface.
	 *
	 * @param int    $object_id Term id.
	 * @param string $meta_key  Meta key.
	 */
	public function should_dirty_term( int $object_id, string $meta_key ): bool {
		if ( $object_id <= 0 || ! $this->registry->has( Store::SOURCE_TERM, $meta_key ) ) {
			return false;
		}
		if ( ! function_exists( 'get_term' ) ) {
			return false;
		}
		$term = get_term( $object_id );
		if ( ! $term instanceof \WP_Term || is_wp_error( $term ) ) {
			return false;
		}
		return AdmittedTaxonomies::admits( (string) $term->taxonomy );
	}

	/**
	 * Register post meta hooks into coordinator.
	 *
	 * @param RequestLocalInvalidationCoordinator $coordinator Coordinator.
	 */
	public function register_post_hooks( RequestLocalInvalidationCoordinator $coordinator ): void {
		$callback = function ( $meta_id, $object_id, $meta_key ) use ( $coordinator ): void {
			unset( $meta_id );
			if ( ! is_string( $meta_key ) || ! is_numeric( $object_id ) ) {
				return;
			}
			$object_id = (int) $object_id;
			if ( $this->should_dirty_post( $object_id, $meta_key ) ) {
				$coordinator->mark_dirty( Store::SOURCE_POST, $object_id );
			}
		};
		add_action( 'updated_post_meta', $callback, 10, 3 );
		add_action( 'added_post_meta', $callback, 10, 3 );
		add_action( 'deleted_post_meta', $callback, 10, 3 );
	}

	/**
	 * Register term meta hooks into coordinator.
	 *
	 * @param RequestLocalInvalidationCoordinator $coordinator Coordinator.
	 */
	public function register_term_hooks( RequestLocalInvalidationCoordinator $coordinator ): void {
		$callback = function ( $meta_id, $object_id, $meta_key ) use ( $coordinator ): void {
			unset( $meta_id );
			if ( ! is_string( $meta_key ) || ! is_numeric( $object_id ) ) {
				return;
			}
			$object_id = (int) $object_id;
			if ( $this->should_dirty_term( $object_id, $meta_key ) ) {
				$coordinator->mark_dirty( Store::SOURCE_TERM, $object_id );
			}
		};
		add_action( 'updated_term_meta', $callback, 10, 3 );
		add_action( 'added_term_meta', $callback, 10, 3 );
		add_action( 'deleted_term_meta', $callback, 10, 3 );
	}
}
