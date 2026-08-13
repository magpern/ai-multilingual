<?php
/**
 * Post surface adapter — facts and event mapping; delegates extract/assemble/sync.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Settings;
use AIMultilingual\Surface\Meta\RankMathMetaDefinitions;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * Answers post facts and registers save_post + registered-meta dirty marks.
 *
 * Does not own Extractor / SegmentAssembler / Store policy. Sync is performed
 * only by the request-local coordinator shutdown flush.
 */
final class PostSurfaceAdapter implements SurfaceCapability {

	/**
	 * Rank Math SEO text metas — BC alias of RankMathMetaDefinitions (sole authority).
	 *
	 * @var list<string>
	 */
	public const RANK_MATH_SEO_META_KEYS = RankMathMetaDefinitions::SEO_META_KEYS;

	/**
	 * Builds the post surface adapter.
	 *
	 * @param Settings|null               $settings      Optional settings for activation facts.
	 * @param Extractor|null              $extractor     Source extractor (required in production).
	 * @param RegisteredMetaRegistry|null $meta_registry Optional registered-meta catalog.
	 */
	public function __construct(
		private ?Settings $settings = null,
		private ?Extractor $extractor = null,
		private ?RegisteredMetaRegistry $meta_registry = null,
	) {
	}

	/**
	 * Catalog-derived Rank Math SEO meta keys (must match RANK_MATH_SEO_META_KEYS).
	 *
	 * @return list<string>
	 */
	public static function rank_math_seo_meta_keys(): array {
		return RankMathMetaDefinitions::seo_meta_keys();
	}

	/**
	 * {@inheritdoc}
	 */
	public function source_type(): string {
		return Store::SOURCE_POST;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_id Source object id.
	 */
	public function exists( int $source_id ): bool {
		if ( $source_id <= 0 || ! function_exists( 'get_post' ) ) {
			return false;
		}
		$post = get_post( $source_id );
		return $post instanceof WP_Post;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_id Source object id.
	 */
	public function source_subtype( int $source_id ): string {
		$post = $this->post( $source_id );
		return $post instanceof WP_Post ? (string) $post->post_type : '';
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $user_id   User id.
	 * @param int $source_id Source object id.
	 */
	public function user_can_edit_source( int $user_id, int $source_id ): bool {
		if ( $source_id <= 0 || ! function_exists( 'user_can' ) ) {
			return false;
		}
		if ( $user_id > 0 ) {
			return (bool) user_can( $user_id, 'edit_post', $source_id );
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $source_id );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_id Source object id.
	 */
	public function is_visitor_public( int $source_id ): bool {
		$post = $this->post( $source_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return false;
		}
		return 'publish' === $post->post_status;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $capability Capability name.
	 */
	public function supports( string $capability ): bool {
		return in_array( $capability, SurfaceCapabilityNames::all(), true );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $feature Feature token.
	 */
	public function feature_implemented( string $feature ): bool {
		return in_array(
			$feature,
			array( 'block_extraction', 'elementor_extraction', 'rank_math_seo', 'fluentforms' ),
			true
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $feature Feature token.
	 */
	public function feature_activated( string $feature ): bool {
		if ( null === $this->settings ) {
			return false;
		}
		return match ( $feature ) {
			'block_extraction' => $this->settings->block_extraction_enabled(),
			'elementor_extraction' => $this->settings->elementor_extraction_enabled(),
			default => false,
		};
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_id Post id.
	 * @return array<string, array<string, mixed>>
	 */
	public function extract_segments( int $source_id ): array {
		if ( null === $this->extractor ) {
			return array();
		}

		$post = $this->post( $source_id );

		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		// Revisions and autosaves are not the readable object; they own no
		// stored segments, so reconciling against them would be meaningless.
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return array();
		}

		return $this->extractor->extract( $post );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param RequestLocalInvalidationCoordinator $coordinator Coordinator.
	 */
	public function register_invalidation_events( RequestLocalInvalidationCoordinator $coordinator ): void {
		$adapter = $this;

		add_action(
			'save_post',
			static function ( $post_id, $post ) use ( $coordinator, $adapter ): void {
				if ( ! $post instanceof WP_Post ) {
					return;
				}
				if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
					$coordinator->clear_dirty( Store::SOURCE_POST, (int) $post_id );
					return;
				}
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					$coordinator->clear_dirty( Store::SOURCE_POST, (int) $post_id );
					return;
				}
				if ( ! $adapter->is_admitted_post_type( (string) $post->post_type ) ) {
					return;
				}
				$coordinator->mark_dirty( Store::SOURCE_POST, (int) $post_id );
			},
			20,
			2
		);

		add_action(
			'elementor/document/after_save',
			static function ( $document ) use ( $coordinator, $adapter ): void {
				if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
					return;
				}

				$post_id = (int) $document->get_main_id();
				if ( $post_id <= 0 ) {
					return;
				}

				if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
					$coordinator->clear_dirty( Store::SOURCE_POST, $post_id );
					return;
				}

				if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
					$coordinator->clear_dirty( Store::SOURCE_POST, $post_id );
					return;
				}

				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					$coordinator->clear_dirty( Store::SOURCE_POST, $post_id );
					return;
				}

				if ( ! $adapter->exists( $post_id ) ) {
					return;
				}

				$subtype = $adapter->source_subtype( $post_id );
				if ( ! $adapter->is_admitted_post_type( $subtype ) ) {
					return;
				}

				$coordinator->mark_dirty( Store::SOURCE_POST, $post_id );
			},
			20,
			1
		);

		$meta_callback = static function ( $meta_id, $object_id, $meta_key ) use ( $coordinator, $adapter ): void {
			unset( $meta_id );
			if ( ! is_string( $meta_key ) ) {
				return;
			}
			$allow = null !== $adapter->meta_registry
				? $adapter->meta_registry->has( Store::SOURCE_POST, $meta_key )
				: in_array( $meta_key, self::rank_math_seo_meta_keys(), true );
			if ( ! $allow ) {
				return;
			}
			$object_id = (int) $object_id;
			if ( $object_id <= 0 || ! $adapter->exists( $object_id ) ) {
				return;
			}
			$subtype = $adapter->source_subtype( $object_id );
			if ( ! $adapter->is_admitted_post_type( $subtype ) ) {
				return;
			}
			$coordinator->mark_dirty( Store::SOURCE_POST, $object_id );
		};

		add_action( 'updated_post_meta', $meta_callback, 10, 3 );
		add_action( 'added_post_meta', $meta_callback, 10, 3 );
		add_action( 'deleted_post_meta', $meta_callback, 10, 3 );

		add_action(
			'before_delete_post',
			static function ( $post_id ) use ( $coordinator ): void {
				$coordinator->mark_dirty( Store::SOURCE_POST, (int) $post_id );
			},
			10,
			1
		);
	}

	/**
	 * Whether a post type is admitted for stale observation / workspace family.
	 *
	 * @param string $post_type Post type.
	 */
	public function is_admitted_post_type( string $post_type ): bool {
		return AdmittedPostTypes::admits( $post_type, AdmittedPostTypes::CONTEXT_WORKSPACE )
			|| AdmittedPostTypes::admits( $post_type, AdmittedPostTypes::CONTEXT_FRONTEND_OVERLAY );
	}

	/**
	 * Loads a post by id when present.
	 *
	 * @param int $source_id Post id.
	 */
	private function post( int $source_id ): ?WP_Post {
		if ( $source_id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post = get_post( $source_id );
		return $post instanceof WP_Post ? $post : null;
	}
}
