<?php
/**
 * Site Translate localized URL batch generate/publish orchestration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\SiteTranslate;

use AIMultilingual\Routing\RoutePublicationService;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Routing\SlugCandidateService;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Thin orchestration over existing slug/route authorities — no direct route-table writes.
 */
final class SiteTranslateLocalizedUrlBatchService {

	public const OUTCOME_ELIGIBLE_SUCCESS         = 'eligible_success';
	public const OUTCOME_NOT_ADMITTED             = 'not_admitted';
	public const OUTCOME_MISSING_TRANSLATED_TITLE = 'missing_translated_title';
	public const OUTCOME_TITLE_NOT_PUBLISHED      = 'title_not_published';
	public const OUTCOME_TITLE_STALE              = 'title_stale';
	public const OUTCOME_MANUAL_SLUG_LOCKED       = 'manual_slug_locked';
	public const OUTCOME_COLLISION                = 'collision';
	public const OUTCOME_PUBLICATION_INELIGIBLE   = 'publication_ineligible';
	public const OUTCOME_LANGUAGE_NOT_PUBLISHED   = 'language_not_published';
	public const OUTCOME_OTHER_ERROR              = 'other_error';

	/**
	 * Translation store.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Slug candidate authority.
	 *
	 * @var SlugCandidateService
	 */
	private SlugCandidateService $slug_candidates;

	/**
	 * Route publication authority.
	 *
	 * @var RoutePublicationService
	 */
	private RoutePublicationService $route_publication;

	/**
	 * Routing capability registry.
	 *
	 * @var RoutingCapabilityRegistry
	 */
	private RoutingCapabilityRegistry $capabilities;

	/**
	 * Builds the LU batch service.
	 *
	 * @param Store                     $store              Translation store.
	 * @param SlugCandidateService      $slug_candidates    Slug candidate service.
	 * @param RoutePublicationService   $route_publication  Route publication service.
	 * @param RoutingCapabilityRegistry $capabilities       Capability registry.
	 */
	public function __construct(
		Store $store,
		SlugCandidateService $slug_candidates,
		RoutePublicationService $route_publication,
		RoutingCapabilityRegistry $capabilities
	) {
		$this->store             = $store;
		$this->slug_candidates   = $slug_candidates;
		$this->route_publication = $route_publication;
		$this->capabilities      = $capabilities;
	}

	/**
	 * Generates slug candidates and publishes routes for a selection.
	 *
	 * @param int[] $post_ids    Selected post ids.
	 * @param int   $language_id Target language id.
	 * @param int   $user_id     Acting user id.
	 * @return array<string, mixed>
	 */
	public function generate_and_publish( array $post_ids, int $language_id, int $user_id = 0 ): array {
		$outcomes = array();

		foreach ( array_values( array_unique( array_map( 'intval', $post_ids ) ) ) as $post_id ) {
			if ( $post_id <= 0 ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				$outcomes[] = $this->outcome_row( $post_id, '', self::OUTCOME_OTHER_ERROR, __( 'Post not found.', 'universal-multilingual' ) );
				continue;
			}

			if ( ! $this->capabilities->supports_post( $post ) ) {
				$outcomes[] = $this->outcome_row( $post_id, (string) $post->post_type, self::OUTCOME_NOT_ADMITTED, __( 'Routing capability does not support this object.', 'universal-multilingual' ) );
				continue;
			}

			$title_check = $this->automatic_title_eligibility( $post, $language_id );
			if ( is_array( $title_check ) ) {
				$outcomes[] = $this->outcome_row(
					$post_id,
					(string) $post->post_type,
					(string) $title_check['outcome'],
					(string) $title_check['message']
				);
				continue;
			}

			$generated = $this->slug_candidates->generate( $post, $language_id );
			if ( $generated instanceof WP_Error ) {
				$outcomes[] = $this->outcome_row(
					$post_id,
					(string) $post->post_type,
					$this->map_slug_error( $generated ),
					$generated->get_error_message()
				);
				continue;
			}

			$published = $this->route_publication->publish_route( $post, $language_id, $user_id );
			if ( $published instanceof WP_Error ) {
				$outcomes[] = $this->outcome_row(
					$post_id,
					(string) $post->post_type,
					$this->map_route_error( $published ),
					$published->get_error_message()
				);
				continue;
			}

			$outcomes[] = $this->outcome_row(
				$post_id,
				(string) $post->post_type,
				self::OUTCOME_ELIGIBLE_SUCCESS,
				__( 'Localized route published.', 'universal-multilingual' ),
				is_array( $published ) ? $published : array()
			);
		}

		$success = count(
			array_filter(
				$outcomes,
				static fn( array $row ): bool => self::OUTCOME_ELIGIBLE_SUCCESS === (string) ( $row['outcome'] ?? '' )
			)
		);

		return array(
			'language_id'   => $language_id,
			'total'         => count( $outcomes ),
			'success_count' => $success,
			'outcomes'      => $outcomes,
		);
	}

	/**
	 * Validates automatic slug generation title prerequisites.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @return true|array{outcome: string, message: string}
	 */
	private function automatic_title_eligibility( WP_Post $post, int $language_id ) {
		$row  = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, Extractor::FIELD_TITLE );
		$text = is_object( $row ) ? trim( (string) ( $row->translated_text ?? '' ) ) : '';

		if ( '' === $text || ( is_object( $row ) && Store::STATUS_MISSING === (string) ( $row->status ?? '' ) ) ) {
			return array(
				'outcome' => self::OUTCOME_MISSING_TRANSLATED_TITLE,
				'message' => __( 'Translated title is required to generate a slug.', 'universal-multilingual' ),
			);
		}

		$publish_status = is_object( $row ) ? (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED ) : Store::PUBLISH_UNPUBLISHED;
		if ( Store::PUBLISH_PUBLISHED !== $publish_status ) {
			return array(
				'outcome' => self::OUTCOME_TITLE_NOT_PUBLISHED,
				'message' => __( 'Translated title must be published before automatic slug generation.', 'universal-multilingual' ),
			);
		}

		if ( is_object( $row ) && ! empty( $row->is_stale ) ) {
			return array(
				'outcome' => self::OUTCOME_TITLE_STALE,
				'message' => __( 'Published title is stale; retranslate or publish a current title before automatic slug generation.', 'universal-multilingual' ),
			);
		}

		return true;
	}

	/**
	 * Maps slug candidate errors to Site Translate outcomes.
	 *
	 * @param WP_Error $error Domain error.
	 */
	private function map_slug_error( WP_Error $error ): string {
		return match ( $error->get_error_code() ) {
			'aiml_slug_missing_title' => self::OUTCOME_MISSING_TRANSLATED_TITLE,
			'aiml_slug_manual_locked' => self::OUTCOME_MANUAL_SLUG_LOCKED,
			'aiml_slug_route_collision', 'aiml_slug_history_collision', 'aiml_slug_wp_collision', 'aiml_slug_collision_exhausted' => self::OUTCOME_COLLISION,
			default => self::OUTCOME_OTHER_ERROR,
		};
	}

	/**
	 * Maps route publication errors to Site Translate outcomes.
	 *
	 * @param WP_Error $error Domain error.
	 */
	private function map_route_error( WP_Error $error ): string {
		return match ( $error->get_error_code() ) {
			'aiml_slug_language_not_published' => self::OUTCOME_LANGUAGE_NOT_PUBLISHED,
			'aiml_slug_capability_unsupported' => self::OUTCOME_NOT_ADMITTED,
			'aiml_slug_route_collision', 'aiml_slug_history_collision', 'aiml_slug_wp_collision', 'aiml_slug_collision_exhausted' => self::OUTCOME_COLLISION,
			'aiml_slug_manual_locked' => self::OUTCOME_MANUAL_SLUG_LOCKED,
			default => self::OUTCOME_PUBLICATION_INELIGIBLE,
		};
	}

	/**
	 * Builds one outcome row.
	 *
	 * @param int                  $post_id   Post id.
	 * @param string               $post_type Post type.
	 * @param string               $outcome   Outcome code.
	 * @param string               $message   Operator message.
	 * @param array<string, mixed> $details   Optional route payload.
	 * @return array<string, mixed>
	 */
	private function outcome_row( int $post_id, string $post_type, string $outcome, string $message, array $details = array() ): array {
		return array(
			'post_id'   => $post_id,
			'post_type' => $post_type,
			'outcome'   => $outcome,
			'message'   => $message,
			'details'   => $details,
		);
	}
}
