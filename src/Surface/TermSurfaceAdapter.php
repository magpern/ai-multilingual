<?php
/**
 * Term surface adapter — facts and event mapping; delegates extract/adopt/sync.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

use AIMultilingual\Surface\Meta\RankMathMetaDefinitions;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermExtractor;
use WP_Term;

/**
 * Answers taxonomy term facts and registers term dirty marks.
 *
 * Facts only: this class never decides review, publication, jobs or overlay
 * policy, and never mutates a term. Adoption of hosted compatibility rows is
 * the adoption service's job; syncing is the coordinator's.
 */
final class TermSurfaceAdapter implements SurfaceCapability {

	/**
	 * Rank Math term SEO metas — derived from RankMathMetaDefinitions.
	 *
	 * @var list<string>
	 */
	public const RANK_MATH_SEO_META_KEYS = PostSurfaceAdapter::RANK_MATH_SEO_META_KEYS;

	/**
	 * Builds the term surface adapter.
	 *
	 * @param TermExtractor|null          $extractor     Native term field extractor.
	 * @param RegisteredMetaRegistry|null $meta_registry Optional registered-meta catalog.
	 */
	public function __construct(
		private ?TermExtractor $extractor = null,
		private ?RegisteredMetaRegistry $meta_registry = null,
	) {
	}

	/**
	 * Catalog-derived Rank Math SEO meta keys.
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
		return Store::SOURCE_TERM;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_id Term id.
	 */
	public function exists( int $source_id ): bool {
		return null !== $this->term( $source_id );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_id Term id.
	 */
	public function source_subtype( int $source_id ): string {
		$term = $this->term( $source_id );

		return null === $term ? '' : (string) $term->taxonomy;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $user_id   User id.
	 * @param int $source_id Term id.
	 */
	public function user_can_edit_source( int $user_id, int $source_id ): bool {
		if ( $source_id <= 0 ) {
			return false;
		}

		if ( $user_id > 0 ) {
			return function_exists( 'user_can' ) && (bool) user_can( $user_id, 'edit_term', $source_id );
		}

		return function_exists( 'current_user_can' ) && current_user_can( 'edit_term', $source_id );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_id Term id.
	 */
	public function is_visitor_public( int $source_id ): bool {
		$term = $this->term( $source_id );

		if ( null === $term ) {
			return false;
		}

		return $this->is_public_taxonomy( (string) $term->taxonomy );
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
		return 'rank_math_seo' === $feature;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $feature Feature token.
	 */
	public function feature_activated( string $feature ): bool {
		unset( $feature );

		return false;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param int $source_id Term id.
	 * @return array<string, array<string, mixed>>
	 */
	public function extract_segments( int $source_id ): array {
		if ( null === $this->extractor ) {
			return array();
		}

		return $this->extractor->extract( $source_id );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param RequestLocalInvalidationCoordinator $coordinator Coordinator.
	 */
	public function register_invalidation_events( RequestLocalInvalidationCoordinator $coordinator ): void {
		$term_callback = static function ( $term_id, $tt_id = 0, $taxonomy = '' ) use ( $coordinator ): void {
			unset( $tt_id );

			$term_id = (int) $term_id;
			if ( $term_id <= 0 || ! AdmittedTaxonomies::admits( (string) $taxonomy ) ) {
				return;
			}

			$coordinator->mark_dirty( Store::SOURCE_TERM, $term_id );
		};

		add_action( 'created_term', $term_callback, 20, 3 );
		add_action( 'edited_term', $term_callback, 20, 3 );

		add_action(
			'delete_term',
			static function ( $term_id, $tt_id = 0, $taxonomy = '' ) use ( $coordinator ): void {
				unset( $tt_id );

				$term_id = (int) $term_id;
				if ( $term_id <= 0 || ! AdmittedTaxonomies::admits( (string) $taxonomy ) ) {
					return;
				}

				// The term is already gone by flush time, so the coordinator
				// syncs an empty segment set and the rows become true orphans.
				$coordinator->mark_dirty( Store::SOURCE_TERM, $term_id );
			},
			20,
			3
		);

		$adapter = $this;

		$meta_callback = static function ( $meta_id, $object_id, $meta_key ) use ( $coordinator, $adapter ): void {
			unset( $meta_id );

			if ( ! is_string( $meta_key ) ) {
				return;
			}

			$allow = null !== $adapter->meta_registry
				? $adapter->meta_registry->has( Store::SOURCE_TERM, $meta_key )
				: in_array( $meta_key, self::rank_math_seo_meta_keys(), true );
			if ( ! $allow ) {
				return;
			}

			$object_id = (int) $object_id;
			if ( $object_id <= 0 || ! AdmittedTaxonomies::admits( $adapter->source_subtype( $object_id ) ) ) {
				return;
			}

			$coordinator->mark_dirty( Store::SOURCE_TERM, $object_id );
		};

		add_action( 'updated_term_meta', $meta_callback, 10, 3 );
		add_action( 'added_term_meta', $meta_callback, 10, 3 );
		add_action( 'deleted_term_meta', $meta_callback, 10, 3 );
	}

	/**
	 * Loads an admitted term by id.
	 *
	 * @param int $source_id Term id.
	 */
	private function term( int $source_id ): ?WP_Term {
		if ( $source_id <= 0 || ! function_exists( 'get_term' ) ) {
			return null;
		}

		$term = get_term( $source_id );

		if ( ! $term instanceof WP_Term ) {
			return null;
		}

		return AdmittedTaxonomies::admits( (string) $term->taxonomy ) ? $term : null;
	}

	/**
	 * Whether the taxonomy is visible to visitors.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	private function is_public_taxonomy( string $taxonomy ): bool {
		if ( ! function_exists( 'get_taxonomy' ) ) {
			return false;
		}

		$object = get_taxonomy( $taxonomy );
		if ( ! is_object( $object ) ) {
			return false;
		}

		if ( isset( $object->publicly_queryable ) && ! (bool) $object->publicly_queryable ) {
			return false;
		}

		return (bool) ( $object->public ?? false );
	}
}
