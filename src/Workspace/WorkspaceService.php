<?php
/**
 * Translator workspace application facade.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Orchestration entry point for workspace queries and commands.
 */
final class WorkspaceService {

	public const SUPPORTED_POST_TYPES = array( 'post', 'page' );

	/**
	 * Injected dependency.
	 *
	 * @var SegmentAssembler
	 */
	private SegmentAssembler $assembler;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationStatusCalculator
	 */
	private TranslationStatusCalculator $status_calculator;

	/**
	 * Injected dependency.
	 *
	 * @var BatchOperationCoordinator
	 */
	private BatchOperationCoordinator $batch;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationService
	 */
	private TranslationService $translation;

	/**
	 * Injected dependency.
	 *
	 * @var PreviewService
	 */
	private PreviewService $preview;

	/**
	 * Injected dependency.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Injected dependency.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Injected dependency.
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationSuggestionService
	 */
	private TranslationSuggestionService $suggestions;

	/**
	 * Builds the collaborator.
	 *
	 * @param SegmentAssembler             $assembler           Segment assembly.
	 * @param TranslationStatusCalculator  $status_calculator   Status aggregation.
	 * @param TranslationService           $translation         Auto-translate boundary.
	 * @param PreviewService               $preview             Preview URLs.
	 * @param Languages                    $languages           Language registry.
	 * @param Store                        $store               Segment store.
	 * @param Extractor                    $extractor           Source extractor.
	 * @param TranslationSuggestionService $suggestions         Suggestion orchestration.
	 */
	public function __construct(
		SegmentAssembler $assembler,
		TranslationStatusCalculator $status_calculator,
		TranslationService $translation,
		PreviewService $preview,
		Languages $languages,
		Store $store,
		Extractor $extractor,
		TranslationSuggestionService $suggestions
	) {
		$this->assembler         = $assembler;
		$this->status_calculator = $status_calculator;
		$this->translation       = $translation;
		$this->preview           = $preview;
		$this->languages         = $languages;
		$this->store             = $store;
		$this->extractor         = $extractor;
		$this->suggestions       = $suggestions;
		$this->batch             = new BatchOperationCoordinator( $this, $translation );
	}

	/**
	 * Returns the bulk operation coordinator.
	 *
	 * @return BatchOperationCoordinator
	 */
	public function batch_coordinator(): BatchOperationCoordinator {
		return $this->batch;
	}

	/**
	 * Lists posts/pages for the workspace picker.
	 *
	 * @param array<string, mixed> $args Query args: search, page, per_page, language.
	 * @return array<string, mixed>
	 */
	public function list_pages( array $args ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$search   = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$language = $this->resolve_language( (string) ( $args['language'] ?? '' ) );

		$query = new WP_Query(
			array(
				'post_type'              => self::SUPPORTED_POST_TYPES,
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

			$summaries = $this->status_calculator->summaries_for_post( (int) $post->ID );
			$lang_id   = null !== $language ? (int) $language->language_id : 0;
			$summary   = $lang_id > 0 ? ( $summaries[ $lang_id ] ?? array(
				'total' => 0,
				'stale' => 0,
			) ) : array(
				'total' => 0,
				'stale' => 0,
			);

			$items[] = array(
				'post_id'        => (int) $post->ID,
				'post_title'     => (string) $post->post_title,
				'post_type'      => (string) $post->post_type,
				'post_status'    => (string) $post->post_status,
				'modified_gmt'   => (string) $post->post_modified_gmt,
				'language_id'    => $lang_id,
				'total_segments' => (int) ( $summary['total'] ?? 0 ),
				'stale_count'    => (int) ( $summary['stale'] ?? 0 ),
			);
		}

		return array(
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Loads segment DTOs for one post.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @return array<int, array<string, mixed>>
	 */
	public function load_segments( WP_Post $post, int $language_id ): array {
		$this->assert_supported_post( $post );

		$segments = $this->assembler->assemble_for_post( $post, $language_id );

		return $this->attach_suggestions( $segments, $language_id );
	}

	/**
	 * Returns page-level status DTO.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @return array<string, mixed>
	 */
	public function page_status( WP_Post $post, int $language_id ): array {
		$this->assert_supported_post( $post );

		return $this->page_status_for_segments(
			$post,
			$language_id,
			$this->assembler->assemble_for_post( $post, $language_id )
		);
	}

	/**
	 * Page status derived from already-assembled segment DTOs.
	 *
	 * @param WP_Post                          $post        Canonical post.
	 * @param int                              $language_id Target language id.
	 * @param array<int, array<string, mixed>> $segments    Assembled segment DTOs.
	 * @return array<string, mixed>
	 */
	public function page_status_for_segments( WP_Post $post, int $language_id, array $segments ): array {
		$this->assert_supported_post( $post );

		return $this->status_calculator->for_segments( $post, $language_id, $segments );
	}

	/**
	 * Operation handler.
	 *
	 * @param WP_Post $post          Canonical post.
	 * @param string  $language_code Target language code.
	 * @return string|WP_Error
	 */
	public function preview_url( WP_Post $post, string $language_code ) {
		$this->assert_supported_post( $post );

		return $this->preview->preview_url( $post, $language_code );
	}

	/**
	 * Saves one segment with optimistic locking.
	 *
	 * @param WP_Post $post            Canonical post.
	 * @param int     $language_id     Target language id.
	 * @param string  $segment_key     Segment key.
	 * @param string  $translated_text Target text.
	 * @param string  $source_hash     Client source hash.
	 * @param string  $status          Optional workflow status.
	 * @return array<string, mixed>
	 * @throws WorkspaceConflictException When source_hash mismatches.
	 * @throws \InvalidArgumentException When the segment cannot be saved.
	 * @throws \RuntimeException When the saved segment cannot be reloaded.
	 */
	public function save_segment(
		WP_Post $post,
		int $language_id,
		string $segment_key,
		string $translated_text,
		string $source_hash,
		string $status = ''
	): array {
		$this->assert_supported_post( $post );

		$current = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $current ) {
			throw new \InvalidArgumentException( 'Unknown segment key for this post.' );
		}

		if ( ! (bool) ( $current['can_edit'] ?? false ) ) {
			throw new \InvalidArgumentException( 'This segment is not editable.' );
		}

		if ( '' !== $source_hash && (string) ( $current['source_hash'] ?? '' ) !== $source_hash ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- conflict payload is structured data, not exception text.
			throw new WorkspaceConflictException( array( $current ) );
		}

		$save_status = '' !== $status ? $status : Store::STATUS_MANUALLY_EDITED;
		if ( ! in_array( $save_status, Store::statuses(), true ) ) {
			$save_status = Store::STATUS_MANUALLY_EDITED;
		}

		$result = $this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => $language_id,
				'field_key'       => (string) ( $current['field_key'] ?? '' ),
				'segment_key'     => $segment_key,
				'segment_kind'    => (string) ( $current['segment_kind'] ?? Store::KIND_BLOCK ),
				'segment_order'   => (int) ( $current['segment_order'] ?? 0 ),
				'text_format'     => (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN ),
				'source_text'     => (string) ( $current['source_text'] ?? '' ),
				'translated_text' => $translated_text,
				'status'          => $save_status,
			)
		);

		if ( $result instanceof WP_Error ) {
			throw new \InvalidArgumentException( esc_html( $result->get_error_message() ) );
		}

		$refreshed = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $refreshed ) {
			throw new \RuntimeException( 'Saved segment could not be reloaded.' );
		}

		$with_suggestions = $this->attach_suggestions( array( $refreshed ), $language_id );

		return $with_suggestions[0];
	}

	/**
	 * Requests ranked suggestions for one segment (TM + optional AI profile).
	 *
	 * @param WP_Post $post           Canonical post.
	 * @param int     $language_id    Target language id.
	 * @param string  $segment_key    Segment key.
	 * @param string  $prompt_profile Prompt profile id.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException When the segment is unknown.
	 */
	public function request_suggestions(
		WP_Post $post,
		int $language_id,
		string $segment_key,
		string $prompt_profile
	): array {
		$this->assert_supported_post( $post );

		$segment = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $segment ) {
			throw new \InvalidArgumentException( 'Unknown segment key for this post.' );
		}

		$default = $this->languages->default();
		$context = array(
			'source_language_id' => $default ? (int) $default->language_id : 0,
			'target_language_id' => $language_id,
			'prompt_profile'     => $prompt_profile,
			'post'               => $post,
		);

		$suggestions         = $this->suggestions->request_suggestions( $segment, $context );
		$meta                = is_array( $segment['meta'] ?? null ) ? $segment['meta'] : array();
		$meta['suggestions'] = $suggestions;
		$segment['meta']     = $meta;

		return $segment;
	}

	/**
	 * Operation handler.
	 *
	 * @param WP_Post                          $post        Canonical post.
	 * @param int                              $language_id Target language id.
	 * @param array<int, array<string, mixed>> $items       Batch items.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save_batch( WP_Post $post, int $language_id, array $items ) {
		$this->assert_supported_post( $post );

		return $this->batch->save_batch( $post, $language_id, $items );
	}

	/**
	 * Operation handler.
	 *
	 * @param WP_Post            $post         Canonical post.
	 * @param int                $language_id  Target language id.
	 * @param array<int, string> $segment_keys Segment keys.
	 * @param string             $mode         sync|async placeholder.
	 * @return array<string, mixed>|WP_Error
	 */
	public function translate( WP_Post $post, int $language_id, array $segment_keys, string $mode = 'sync' ) {
		$this->assert_supported_post( $post );
		unset( $mode );

		return $this->batch->translate_batch( $post, $language_id, $segment_keys );
	}

	/**
	 * Operation handler.
	 *
	 * @param string $language_code Language code from the request.
	 * @return object|null
	 */
	public function resolve_language( string $language_code ): ?object {
		if ( '' === $language_code ) {
			return null;
		}

		return $this->languages->find_by_code( $language_code );
	}

	/**
	 * Operation handler.
	 *
	 * @param int $post_id Post id.
	 * @return WP_Post|WP_Error
	 */
	public function get_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'aiml_post_not_found',
				__( 'Post not found.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		if ( ! in_array( $post->post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			return new WP_Error(
				'aiml_post_type_unsupported',
				__( 'This post type is not supported in the workspace.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		return $post;
	}

	/**
	 * Attaches ranked suggestions via TranslationSuggestionService (never TMS).
	 *
	 * @param list<array<string, mixed>> $segments    Assembled segment DTOs.
	 * @param int                        $language_id Target language id.
	 * @return list<array<string, mixed>>
	 */
	private function attach_suggestions( array $segments, int $language_id ): array {
		$default = $this->languages->default();
		$context = array(
			'source_language_id' => $default ? (int) $default->language_id : 0,
			'target_language_id' => $language_id,
		);

		$by_key = $this->suggestions->suggestions_for_batch( $segments, $context );

		foreach ( $segments as $index => $segment ) {
			$key                        = (string) ( $segment['segment_key'] ?? '' );
			$meta                       = is_array( $segment['meta'] ?? null ) ? $segment['meta'] : array();
			$meta['suggestions']        = $by_key[ $key ] ?? array();
			$segments[ $index ]['meta'] = $meta;
		}

		return $segments;
	}

	/**
	 * Ensures the post type is supported by the workspace.
	 *
	 * @param WP_Post $post Canonical post.
	 * @throws \InvalidArgumentException When the post type is unsupported.
	 */
	private function assert_supported_post( WP_Post $post ): void {
		if ( ! in_array( $post->post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			throw new \InvalidArgumentException( 'This post type is not supported in the workspace.' );
		}
	}

	/**
	 * Whether the current user may access workspace operations for a post.
	 *
	 * @param WP_Post $post Canonical post.
	 */
	public function current_user_can_access( WP_Post $post ): bool {
		return current_user_can( Plugin::CAPABILITY ) && current_user_can( 'edit_post', (int) $post->ID );
	}
}
