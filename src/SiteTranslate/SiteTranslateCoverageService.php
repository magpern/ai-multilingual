<?php
/**
 * Site Translate coverage read model per object × language.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\SiteTranslate;

use AIMultilingual\SiteTranslate\SiteTranslateAdmissionService;
use AIMultilingual\Surface\AdmittedPostTypes;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use WP_Post;
use WP_Query;

/**
 * Computes coverage from current extraction/admission authorities.
 */
final class SiteTranslateCoverageService {

	public const REASON_ZERO_ELIGIBLE                  = 'zero_eligible';
	public const REASON_BODY_ELEMENTOR                 = 'body_elementor';
	public const REASON_BODY_BLOCKS_WITHOUT_STRATEGY_F = SiteTranslateAdmissionService::REASON_BODY_BLOCKS_WITHOUT_STRATEGY_F;

	/**
	 * Segment assembler.
	 *
	 * @var SegmentAssembler
	 */
	private SegmentAssembler $assembler;

	/**
	 * Source extractor.
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Registered-meta provider admission.
	 *
	 * @var RegisteredMetaRegistry|null
	 */
	private ?RegisteredMetaRegistry $meta_registry;

	/**
	 * Strategy F admission helper.
	 *
	 * @var SiteTranslateAdmissionService
	 */
	private SiteTranslateAdmissionService $admission;

	/**
	 * Builds the coverage service.
	 *
	 * @param SegmentAssembler              $assembler     Segment assembler.
	 * @param Extractor                     $extractor     Source extractor.
	 * @param SiteTranslateAdmissionService $admission     Strategy F admission.
	 * @param RegisteredMetaRegistry|null   $meta_registry Registered-meta catalog.
	 */
	public function __construct(
		SegmentAssembler $assembler,
		Extractor $extractor,
		SiteTranslateAdmissionService $admission,
		?RegisteredMetaRegistry $meta_registry = null
	) {
		$this->assembler     = $assembler;
		$this->extractor     = $extractor;
		$this->admission     = $admission;
		$this->meta_registry = $meta_registry;
	}

	/**
	 * Lists admitted Site Translate objects with coverage summaries.
	 *
	 * @param array<string, mixed> $args Query args: page, per_page, search, post_type, language_id, coverage_filter.
	 * @return array<string, mixed>
	 */
	public function list_objects( array $args ): array {
		$page            = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page        = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$search          = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$language_id     = (int) ( $args['language_id'] ?? 0 );
		$post_type = sanitize_key( (string) ( $args['post_type'] ?? '' ) );

		$post_types = array( 'page', 'post', 'product' );
		if ( '' !== $post_type && in_array( $post_type, $post_types, true ) ) {
			$post_types = array( $post_type );
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				's'                      => $search,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( ! AdmittedPostTypes::admits( (string) $post->post_type, AdmittedPostTypes::CONTEXT_WORKSPACE ) ) {
				continue;
			}

			$coverage = $language_id > 0 ? $this->coverage_for_post( $post, $language_id ) : $this->empty_coverage( $post );

			$items[] = array(
				'post_id'      => (int) $post->ID,
				'post_title'   => get_the_title( $post ) ?: __( '(no title)', 'universal-multilingual' ),
				'post_type'    => (string) $post->post_type,
				'post_status'  => (string) $post->post_status,
				'modified_gmt' => (string) $post->post_modified_gmt,
				'body_surface' => $this->admission->body_surface( $post ),
				'coverage'     => $coverage,
			);
		}

		return array(
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'language_id' => $language_id,
		);
	}

	/**
	 * Coverage for explicit post ids.
	 *
	 * @param list<int> $post_ids    Post ids.
	 * @param int       $language_id Target language id.
	 * @return list<array<string, mixed>>
	 */
	public function coverage_for_ids( array $post_ids, int $language_id ): array {
		$rows = array();
		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$rows[] = array(
				'post_id'      => (int) $post->ID,
				'post_title'   => get_the_title( $post ) ?: __( '(no title)', 'universal-multilingual' ),
				'post_type'    => (string) $post->post_type,
				'body_surface' => $this->admission->body_surface( $post ),
				'coverage'     => $this->coverage_for_post( $post, $language_id ),
			);
		}

		return $rows;
	}

	/**
	 * Coverage for one post and language.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @return array<string, mixed>
	 */
	public function coverage_for_post( WP_Post $post, int $language_id ): array {
		$segments = $this->assembler->assemble_for_post( $post, $language_id );

		$eligible_total = 0;
		$missing        = 0;
		$translated     = 0;
		$unpublished    = 0;
		$published      = 0;
		$stale          = 0;

		foreach ( $segments as $segment ) {
			if ( ! $this->is_eligible_segment( $segment ) ) {
				continue;
			}

			++$eligible_total;

			$text           = trim( (string) ( $segment['translated_text'] ?? '' ) );
			$status         = (string) ( $segment['status'] ?? Store::STATUS_MISSING );
			$has_translation = Store::STATUS_MISSING !== $status && '' !== $text;

			if ( ! $has_translation ) {
				++$missing;
				continue;
			}

			++$translated;
			if ( ! empty( $segment['is_stale'] ) ) {
				++$stale;
			}

			$publish_status = (string) ( $segment['publish_status'] ?? Store::PUBLISH_UNPUBLISHED );
			if ( Store::PUBLISH_PUBLISHED === $publish_status ) {
				++$published;
			} else {
				++$unpublished;
			}
		}

		$blocked = $this->blocked_reasons( $post, $eligible_total );

		return array(
			'eligible_total'         => $eligible_total,
			'missing'                => $missing,
			'translated'             => $translated,
			'unpublished'            => $unpublished,
			'published'              => $published,
			'stale'                  => $stale,
			'blocked_or_unsupported' => $blocked,
			'translation_complete'   => 0 === $missing && 0 === $stale && $eligible_total > 0,
			'no_extractable_work'    => 0 === $eligible_total,
		);
	}

	/**
	 * Whether a merged segment is in the coverage denominator.
	 *
	 * @param array<string, mixed> $segment Assembled segment DTO.
	 */
	private function is_eligible_segment( array $segment ): bool {
		if ( empty( $segment['can_edit'] ) ) {
			return false;
		}

		if ( Store::FORMAT_SLUG === (string) ( $segment['text_format'] ?? '' ) ) {
			return false;
		}

		$segment_key = (string) ( $segment['segment_key'] ?? '' );
		$field_key   = (string) ( $segment['field_key'] ?? '' );
		if ( Extractor::FIELD_SLUG === $segment_key || Extractor::FIELD_SLUG === $field_key ) {
			return false;
		}

		if ( '' === trim( (string) ( $segment['source_text'] ?? '' ) ) ) {
			return false;
		}

		if ( null !== $this->meta_registry ) {
			$allowed = $this->meta_registry->provider_allowed_for_segment( Store::SOURCE_POST, $segment_key );
			if ( false === $allowed ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Structured blocked/unsupported reason codes for one object.
	 *
	 * @param WP_Post $post           Canonical post.
	 * @param int     $eligible_total Eligible segment count.
	 * @return list<string>
	 */
	private function blocked_reasons( WP_Post $post, int $eligible_total ): array {
		$reasons = array();
		$surface = $this->admission->body_surface( $post );

		if ( Extractor::BODY_BLOCKS === $surface && ! $this->admission->is_strategy_f_fully_valid() ) {
			$reasons[] = self::REASON_BODY_BLOCKS_WITHOUT_STRATEGY_F;
		}

		if ( Extractor::BODY_ELEMENTOR === $surface ) {
			$reasons[] = self::REASON_BODY_ELEMENTOR;
		}

		if ( 0 === $eligible_total ) {
			$reasons[] = self::REASON_ZERO_ELIGIBLE;
		}

		return array_values( array_unique( $reasons ) );
	}

	/**
	 * Empty coverage shell when no language is selected.
	 *
	 * @param WP_Post $post Canonical post.
	 * @return array<string, mixed>
	 */
	private function empty_coverage( WP_Post $post ): array {
		return array(
			'eligible_total'         => 0,
			'missing'                => 0,
			'translated'             => 0,
			'unpublished'            => 0,
			'published'              => 0,
			'stale'                  => 0,
			'blocked_or_unsupported' => $this->blocked_reasons( $post, 0 ),
			'translation_complete'   => false,
			'no_extractable_work'    => true,
		);
	}
}
