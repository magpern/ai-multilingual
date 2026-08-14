<?php
/**
 * Prepared route publication lifecycle (MSEO.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Database\Schema;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Sole FORMAT_SLUG publication + prepared-route authority.
 */
final class RoutePublicationService {

	public const HISTORY_MAX = 5;

	/**
	 * Constructs the service.
	 *
	 * @param Store                           $store         Store.
	 * @param PublicationService              $publication   Publication service.
	 * @param SlugRouteRepository             $routes        Routes.
	 * @param RouteHistoryRepository          $history       History.
	 * @param PathCanonicalizer               $paths         Paths.
	 * @param CanonicalPathCollisionChecker   $collisions    Collisions.
	 * @param ObjectLanguagePublicEligibility $eligibility   Eligibility.
	 * @param RoutingCapabilityRegistry       $capabilities  Capabilities.
	 */
	public function __construct(
		private Store $store,
		private PublicationService $publication,
		private SlugRouteRepository $routes,
		private RouteHistoryRepository $history,
		private PathCanonicalizer $paths,
		private CanonicalPathCollisionChecker $collisions,
		private ObjectLanguagePublicEligibility $eligibility,
		private RoutingCapabilityRegistry $capabilities
	) {
	}

	/**
	 * Atomically publishes the slug candidate into a prepared active route.
	 *
	 * @param WP_Post $post        Source post.
	 * @param int     $language_id Language id.
	 * @param int     $user_id     Acting user.
	 * @return array<string, mixed>|WP_Error
	 */
	public function publish_route( WP_Post $post, int $language_id, int $user_id = 0 ) {
		$eligible = $this->eligibility->is_route_publishable( $post, $language_id );
		if ( $eligible instanceof WP_Error ) {
			return $eligible;
		}

		$boundary  = $this->store->begin_route_boundary();
		$committed = false;

		try {
			$candidate = $this->store->lock_segment_for_update(
				Store::SOURCE_POST,
				(int) $post->ID,
				$language_id,
				Extractor::FIELD_SLUG,
				Extractor::FIELD_SLUG
			);
			if ( null === $candidate ) {
				return new WP_Error( 'aiml_slug_candidate_missing', __( 'Slug candidate is missing.', 'ai-multilingual' ) );
			}

			$current = $this->routes->lock_by_object( Store::SOURCE_POST, (int) $post->ID, $language_id );

			$leaf   = trim( (string) ( $candidate->translated_text ?? '' ) );
			$origin = (string) ( $candidate->slug_origin ?? '' );
			if ( '' === $leaf || Store::STATUS_MISSING === (string) ( $candidate->status ?? '' ) ) {
				return new WP_Error( 'aiml_slug_candidate_missing', __( 'Slug candidate is missing.', 'ai-multilingual' ) );
			}

			$source_path_str = $this->unprefixed_permalink_path( $post );
			if ( $source_path_str instanceof WP_Error ) {
				return $source_path_str;
			}

			$publish_status = (string) ( $candidate->publish_status ?? Store::PUBLISH_UNPUBLISHED );
			$idempotent     = Store::PUBLISH_PUBLISHED === $publish_status
				&& null !== $current
				&& 'active' === (string) ( $current->route_status ?? '' );

			if ( $idempotent ) {
				$pub = $this->publication->publish_under_route_authority(
					Store::SOURCE_POST,
					(int) $post->ID,
					$language_id,
					Extractor::FIELD_SLUG,
					$candidate,
					$user_id
				);
				if ( $pub instanceof WP_Error ) {
					return $pub;
				}

				$this->store->commit_route_boundary( $boundary );
				$committed = true;

				return $this->result_payload( $post, $language_id, true );
			}

			$resolved = $this->collisions->resolve_effective(
				$source_path_str,
				$leaf,
				$language_id,
				Store::SOURCE_POST,
				(int) $post->ID,
				'' !== $origin ? $origin : 'generated'
			);
			if ( $resolved instanceof WP_Error ) {
				return $resolved;
			}

			$effective_leaf = $resolved['leaf'];
			$effective_path = $resolved['path'];

			$reuse = $this->reconcile_own_history( $language_id, $effective_path, Store::SOURCE_POST, (int) $post->ID );
			if ( $reuse instanceof WP_Error ) {
				return $reuse;
			}

			$check = $this->collisions->assert_available(
				$language_id,
				$effective_path,
				Store::SOURCE_POST,
				(int) $post->ID
			);
			if ( true !== $check ) {
				return $check;
			}

			$pub = $this->publication->publish_under_route_authority(
				Store::SOURCE_POST,
				(int) $post->ID,
				$language_id,
				Extractor::FIELD_SLUG,
				$candidate,
				$user_id
			);
			if ( $pub instanceof WP_Error ) {
				return $pub;
			}

			if ( null !== $current ) {
				$prior_path = (string) ( $current->localized_path ?? '' );
				if ( '' !== $prior_path && $prior_path !== $effective_path->to_string() ) {
					try {
						$hist_path = $this->paths->canonicalize( $prior_path );
					} catch ( InvalidPathException $e ) {
						return new WP_Error( 'aiml_slug_invalid_prior_path', $e->getMessage() );
					}
					$inserted = $this->history->insert(
						new HistoryRecord(
							$language_id,
							$hist_path,
							Store::SOURCE_POST,
							(int) $post->ID,
							(string) $post->post_type
						)
					);
					if ( $inserted instanceof WP_Error ) {
						return $inserted;
					}
				}
			}

			try {
				$source_path = $this->paths->canonicalize( $source_path_str );
			} catch ( InvalidPathException $e ) {
				return new WP_Error( 'aiml_slug_invalid_source_path', $e->getMessage() );
			}

			$now   = current_time( 'mysql', true );
			$saved = $this->routes->save(
				new RouteRecord(
					$language_id,
					Store::SOURCE_POST,
					(int) $post->ID,
					(string) $post->post_type,
					$source_path,
					$effective_path,
					$effective_leaf,
					'',
					'' !== $origin ? $origin : 'generated',
					'active',
					$now
				)
			);
			if ( $saved instanceof WP_Error ) {
				return $saved;
			}

			$this->history->delete_oldest_beyond( Store::SOURCE_POST, (int) $post->ID, $language_id, self::HISTORY_MAX );

			$this->store->commit_route_boundary( $boundary );
			$committed = true;

			return $this->result_payload( $post, $language_id, false );
		} finally {
			if ( ! $committed ) {
				$this->store->rollback_route_boundary( $boundary );
			}
		}
	}

	/**
	 * Refreshes source_path only for an existing prepared route (A4).
	 *
	 * @param WP_Post $post        Source post.
	 * @param int     $language_id Language id.
	 * @return object|null|WP_Error
	 */
	public function refresh_source_path( WP_Post $post, int $language_id ) {
		if ( ! $this->capabilities->supports_post( $post ) ) {
			return null;
		}

		$boundary  = $this->store->begin_route_boundary();
		$committed = false;

		try {
			$current = $this->routes->lock_by_object( Store::SOURCE_POST, (int) $post->ID, $language_id );
			if ( null === $current ) {
				$this->store->commit_route_boundary( $boundary );
				$committed = true;

				return null;
			}

			$source_path_str = $this->unprefixed_permalink_path( $post );
			if ( $source_path_str instanceof WP_Error ) {
				return $source_path_str;
			}

			try {
				$source_path = $this->paths->canonicalize( $source_path_str );
			} catch ( InvalidPathException $e ) {
				return new WP_Error( 'aiml_slug_invalid_source_path', $e->getMessage() );
			}

			$localized = (string) ( $current->localized_path ?? '' );
			$leaf      = (string) ( $current->localized_slug ?? '' );
			try {
				$localized_path = $this->paths->canonicalize( $localized );
			} catch ( InvalidPathException $e ) {
				return new WP_Error( 'aiml_slug_invalid_localized_path', $e->getMessage() );
			}

			$saved = $this->routes->save(
				new RouteRecord(
					$language_id,
					Store::SOURCE_POST,
					(int) $post->ID,
					(string) $post->post_type,
					$source_path,
					$localized_path,
					$leaf,
					(string) ( $current->route_namespace ?? '' ),
					(string) ( $current->slug_origin ?? 'generated' ),
					(string) ( $current->route_status ?? 'active' ),
					isset( $current->activated_at ) ? (string) $current->activated_at : null
				)
			);

			if ( $saved instanceof WP_Error ) {
				return $saved;
			}

			$this->store->commit_route_boundary( $boundary );
			$committed = true;

			return $saved;
		} finally {
			if ( ! $committed ) {
				$this->store->rollback_route_boundary( $boundary );
			}
		}
	}

	/**
	 * Marks prepared routes inactive on trash.
	 *
	 * @param int $post_id Post id.
	 */
	public function deactivate_for_source( int $post_id ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema table identifier only.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'UPDATE ' . Schema::slug_routes() . "
				SET route_status = 'inactive', updated_at = %s
				WHERE source_type = %s AND source_id = %d",
				current_time( 'mysql', true ),
				Store::SOURCE_POST,
				$post_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Purges routes and history for permanent delete.
	 *
	 * @param int $post_id Post id.
	 */
	public function purge_for_source( int $post_id ): void {
		$this->routes->delete_by_source( Store::SOURCE_POST, $post_id );
		$this->history->delete_by_source( Store::SOURCE_POST, $post_id );
	}

	/**
	 * Builds UI/REST sync facts for a post/language.
	 *
	 * @param WP_Post $post        Post.
	 * @param int     $language_id Language id.
	 * @return array<string, mixed>
	 */
	public function sync_view( WP_Post $post, int $language_id ): array {
		$candidate = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, Extractor::FIELD_SLUG );
		$route     = $this->routes->find_by_object( Store::SOURCE_POST, (int) $post->ID, $language_id );

		$cand_text  = is_object( $candidate ) ? trim( (string) ( $candidate->translated_text ?? '' ) ) : '';
		$origin     = is_object( $candidate ) ? (string) ( $candidate->slug_origin ?? '' ) : '';
		$pub        = is_object( $candidate ) ? (string) ( $candidate->publish_status ?? Store::PUBLISH_UNPUBLISHED ) : Store::PUBLISH_UNPUBLISHED;
		$has_cand   = is_object( $candidate ) && '' !== $cand_text && Store::STATUS_MISSING !== (string) ( $candidate->status ?? '' );
		$active     = null !== $route && 'active' === (string) ( $route->route_status ?? '' );
		$route_slug = null !== $route ? (string) ( $route->localized_slug ?? '' ) : '';

		if ( ! $has_cand && ! $active ) {
			$sync = 'none';
		} elseif ( Store::PUBLISH_PUBLISHED === $pub && $active ) {
			$sync = 'synchronized';
		} elseif ( Store::PUBLISH_PUBLISHED === $pub && ! $active ) {
			$sync = 'inconsistent';
		} elseif ( $has_cand && Store::PUBLISH_PUBLISHED !== $pub ) {
			$sync = 'pending';
		} else {
			$sync = 'none';
		}

		$can_publish = true === $this->eligibility->is_route_publishable( $post, $language_id );
		$blocked     = '';
		if ( ! $can_publish ) {
			$err     = $this->eligibility->is_route_publishable( $post, $language_id );
			$blocked = $err instanceof WP_Error ? $err->get_error_message() : '';
		}

		return array(
			'slug_candidate'                   => $cand_text,
			'slug_origin'                      => $origin,
			'slug_candidate_publish_status'    => $pub,
			'active_route_slug'                => $route_slug,
			'active_route_status'              => null !== $route ? (string) ( $route->route_status ?? '' ) : '',
			'route_prepared'                   => $active,
			'route_sync_state'                 => $sync,
			'collision_adjusted'               => 'synchronized' === $sync && $cand_text !== $route_slug && '' !== $route_slug,
			'can_generate'                     => 'manual' !== $origin,
			'can_edit_slug'                    => true,
			'can_publish_route'                => $can_publish && $has_cand,
			'route_publication_blocked_reason' => $blocked,
		);
	}

	/**
	 * Helper.
	 *
	 * @param int           $language_id Language id.
	 * @param CanonicalPath $path        Effective path.
	 * @param string        $source_type Source type.
	 * @param int           $source_id   Source id.
	 * @return true|WP_Error
	 */
	private function reconcile_own_history( int $language_id, CanonicalPath $path, string $source_type, int $source_id ) {
		$hist = $this->history->find_by_historical_path( $language_id, $path );
		if ( null === $hist ) {
			return true;
		}

		$same = (string) ( $hist->source_type ?? '' ) === $source_type
			&& (int) ( $hist->source_id ?? 0 ) === $source_id;
		if ( ! $same ) {
			return new WP_Error( 'aiml_slug_history_collision', __( 'Localized path is reserved in history by another object.', 'ai-multilingual' ), array( 'status' => 409 ) );
		}

		$this->history->delete_by_id( (int) $hist->history_id );

		return true;
	}

	/**
	 * Helper.
	 *
	 * @param WP_Post $post Post.
	 * @return string|WP_Error
	 */
	private function unprefixed_permalink_path( WP_Post $post ) {
		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return new WP_Error( 'aiml_slug_no_permalink', __( 'Could not resolve permalink.', 'ai-multilingual' ) );
		}

		$path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		if ( '' !== $home && '/' !== $home && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
			if ( '' === $path ) {
				$path = '/';
			}
		}

		try {
			return $this->paths->canonicalize( $path )->to_string();
		} catch ( InvalidPathException $e ) {
			return new WP_Error( 'aiml_slug_invalid_source_path', $e->getMessage() );
		}
	}

	/**
	 * Helper.
	 *
	 * @param WP_Post $post        Post.
	 * @param int     $language_id Language.
	 * @param bool    $idempotent  Whether this was an idempotent re-publish.
	 * @return array<string, mixed>
	 */
	private function result_payload( WP_Post $post, int $language_id, bool $idempotent ): array {
		$view               = $this->sync_view( $post, $language_id );
		$view['idempotent'] = $idempotent;
		$view['status']     = 'published';

		return $view;
	}
}
